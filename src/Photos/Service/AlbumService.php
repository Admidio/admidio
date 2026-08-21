<?php

namespace Admidio\Photos\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Photos\Entity\Album;
use DateTime;
use RuntimeException;

/**
 * Service for creating and updating photo albums independent of the web form/session.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class AlbumService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Save validated album data and keep the corresponding filesystem folder in sync.
     *
     * @param Album $album Album entity to create or update.
     * @param array<string,mixed> $values Album values using the native pho_* field names.
     * @param string $parentAlbumUuid Parent album UUID or "ALL" for a root album.
     * @throws Exception
     */
    public function saveData(Album $album, array $values, string $parentAlbumUuid = 'ALL'): void
    {
        global $gCurrentOrgId, $gCurrentOrganization;

        if (!$album->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $begin = (string)($values['pho_begin'] ?? $album->getValue('pho_begin', 'database'));
        if ($begin === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_START'));
        }

        $beginDate = DateTime::createFromFormat('!Y-m-d', $begin);
        if ($beginDate === false || $beginDate->format('Y-m-d') !== $begin) {
            throw new Exception('SYS_DATE_INVALID', array('SYS_START', 'YYYY-MM-DD'));
        }

        $end = (string)($values['pho_end'] ?? $album->getValue('pho_end', 'database'));
        if ($end === '') {
            $end = $begin;
        }

        $endDate = DateTime::createFromFormat('!Y-m-d', $end);
        if ($endDate === false || $endDate->format('Y-m-d') !== $end) {
            throw new Exception('SYS_DATE_INVALID', array('SYS_END', 'YYYY-MM-DD'));
        }
        if ($end < $begin) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        $values['pho_begin'] = $begin;
        $values['pho_end'] = $end;

        $parentId = null;
        if ($parentAlbumUuid !== '' && $parentAlbumUuid !== 'ALL') {
            $parentAlbum = new Album($this->db);
            if (!$parentAlbum->readDataByUuid($parentAlbumUuid)
                || (int)$parentAlbum->getValue('pho_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            $parentId = (int)$parentAlbum->getValue('pho_id');
            $this->assertValidParent($album, $parentId);
        }

        $values['pho_pho_id_parent'] = $parentId ?? '';

        $isNewRecord = $album->isNewRecord();
        $albumId = (int)$album->getValue('pho_id');
        $oldBegin = $isNewRecord ? '' : (string)$album->getValue('pho_begin', 'Y-m-d');

        foreach ($values as $key => $value) {
            if (str_starts_with($key, 'pho_')) {
                $album->setValue($key, $value);
            }
        }

        if (!$isNewRecord && $oldBegin !== $begin) {
            $oldFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $oldBegin . '_' . $albumId;
            $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $begin . '_' . $albumId;

            try {
                FileSystemUtils::moveDirectory($oldFolder, $newFolder);
            } catch (RuntimeException $exception) {
                throw new Exception(
                    'SYS_FOLDER_WRITE_ACCESS',
                    array(
                        $newFolder,
                        '<a href="mailto:' . $gCurrentOrganization->getValue('org_email_administrator') . '">',
                        '</a>'
                    )
                );
            }
        }

        if ($album->save()) {
            if ($isNewRecord) {
                $error = $album->createFolder();
                if (is_array($error)) {
                    $album->delete();
                    throw new Exception(
                        $error['text'],
                        array(
                            $error['path'],
                            '<a href="mailto:' . $gCurrentOrganization->getValue('org_email_administrator') . '">',
                            '</a>'
                        )
                    );
                }
            }

            $album->sendNotification();
        }
    }

    /**
     * Ensure an album cannot become its own parent or a child of one of its descendants.
     *
     * @throws Exception
     */
    private function assertValidParent(Album $album, int $parentId): void
    {
        $albumId = (int)$album->getValue('pho_id');
        if ($albumId === 0) {
            return;
        }

        while ($parentId > 0) {
            if ($parentId === $albumId) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            $parentId = (int)$this->db->queryPrepared(
                'SELECT COALESCE(pho_pho_id_parent, 0)
                   FROM ' . TBL_PHOTOS . '
                  WHERE pho_id = ?',
                array($parentId)
            )->fetchColumn();
        }
    }
}
