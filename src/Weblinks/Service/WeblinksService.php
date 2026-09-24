<?php

namespace Admidio\Weblinks\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Organizations\Entity\Organization;
use Admidio\Weblinks\Entity\Weblink;

class WeblinksService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Check RSS access using the settings of the organization whose links will be served.
     * @throws Exception
     */
    public function assertRssFeedAccessible(Organization $organization, bool $validLogin): void
    {
        $settings = $organization->getSettingsManager();

        if (!$settings->getBool('enable_rss')) {
            throw new Exception('SYS_RSS_DISABLED');
        }

        $moduleAccess = $settings->getInt('weblinks_module_enabled');
        if ($moduleAccess === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }
        if ($moduleAccess === 2 && !$validLogin) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /** @return array{0: string, 1: array<int, int|string>} */
    private function visibleLinksCondition(int $categoryId, string $uuid): array
    {
        global $gCurrentUser;

        $visibleCategories = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('LNK'));
        $sql = 'cat_id IN (' . Database::getQmForValues($visibleCategories) . ')';
        $params = $visibleCategories;
        if ($uuid !== '') {
            $sql .= ' AND lnk_uuid = ?';
            $params[] = $uuid;
        } elseif ($categoryId > 0) {
            $sql .= ' AND cat_id = ?';
            $params[] = $categoryId;
        }
        return array($sql, $params);
    }

    public function count(int $categoryId = 0, string $uuid = ''): int
    {
        [$condition, $params] = $this->visibleLinksCondition($categoryId, $uuid);
        $sql = 'SELECT COUNT(*) FROM ' . TBL_LINKS . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = lnk_cat_id WHERE ' . $condition;
        return (int)$this->db->queryPrepared($sql, $params)->fetchColumn();
    }

    public function findAll(int $categoryId = 0, string $uuid = '', int $offset = 0, int $limit = 0): array
    {
        [$condition, $params] = $this->visibleLinksCondition($categoryId, $uuid);
        $sql = 'SELECT * FROM ' . TBL_LINKS . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = lnk_cat_id
                WHERE ' . $condition . ' ORDER BY cat_sequence, lnk_sequence, lnk_timestamp_create DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }
        return $this->db->queryPrepared($sql, $params)->fetchAll();
    }

    private function editableLink(string $uuid): Weblink
    {
        global $gCurrentUser;

        $link = new Weblink($this->db);
        if ($uuid !== '') {
            if (!$link->readDataByUuid($uuid) || !$link->isEditable()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        } elseif (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $link;
    }

    public function save(string $uuid): void
    {
        global $gCurrentSession;

        $link = $this->editableLink($uuid);
        $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        foreach ($form->validate($_POST) as $key => $value) {
            if (str_starts_with($key, 'lnk_')) {
                $link->setValue($key, $value);
                if ($key === 'lnk_cat_id') {
                    $sql = 'SELECT COUNT(*) FROM ' . TBL_LINKS . ' WHERE lnk_cat_id = ?';
                    $link->setValue('lnk_sequence', (int)$this->db->queryPrepared($sql, array($link->getValue('lnk_cat_id')))->fetchColumn() + 1);
                }
            }
        }
        if ($link->save()) {
            $link->sendNotification();
        }
    }

    public function delete(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $link = $this->editableLink($uuid);
        if ($uuid === '') {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $sql = 'UPDATE ' . TBL_LINKS . ' SET lnk_sequence = lnk_sequence - 1 WHERE lnk_cat_id = ? AND lnk_sequence > ?';
        $this->db->queryPrepared($sql, array((int)$link->getValue('lnk_cat_id'), (int)$link->getValue('lnk_sequence')));
        $link->delete();
    }

    public function move(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        if ($uuid === '') {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $direction = admFuncVariableIsValid($_POST, 'direction', 'string', array('validValues' => array(MenuEntry::MOVE_UP, MenuEntry::MOVE_DOWN)));
        $this->editableLink($uuid)->moveSequence($direction);
    }

    public function visit(string $uuid): string
    {
        if ($uuid === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $link = new Weblink($this->db);
        $link->readDataByUuid($uuid);
        $url = $link->getValue('lnk_url');
        if (strlen($url) === 0 || !$link->isVisible()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $link->setValue('lnk_counter', (int)$link->getValue('lnk_counter') + 1);
        $link->saveChangesWithoutRights();
        $link->save();
        return $url;
    }
}
