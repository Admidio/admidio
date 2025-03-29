<?php
/**
 ***********************************************************************************************
 * RSS feed of albums. List the newest 50 albums
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization_short_name : short name of the organization whose photos should be shown
 * *********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;
use Admidio\Photos\Entity\Album;

require_once(__DIR__ . '/../adm_program/system/common.php');

try {
    $getOrganizationShortName = admFuncVariableIsValid($_GET, 'organization_short_name', 'string');

    // check if module is active
    if (!$gSettingsManager->getBool('enable_rss')) {
        throw new Exception('SYS_RSS_DISABLED');
    }

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    if ($getOrganizationShortName !== '') {
        $organization = new Organization($gDb, $getOrganizationShortName);
        $organizationName = $organization->getValue('org_longname');
        $organizationID = $organization->getValue('org_id');
    } else {
        $organizationName = $gCurrentOrganization->getValue('org_longname');
        $organizationID = $gCurrentOrgId;
    }

    if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
        // show firstname and lastname of create and last change user
        $additionalFields = ' cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
        $additionalTables = '
                         LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                                ON cre_surname.usd_usr_id = pho_usr_id_create
                               AND cre_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                         LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                                ON cre_firstname.usd_usr_id = pho_usr_id_create
                               AND cre_firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')';
        $queryParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        );
    } else {
        // show username of create and last change user
        $additionalFields = ' cre_username.usr_login_name AS create_name ';
        $additionalTables = '
                         LEFT JOIN ' . TBL_USERS . ' AS cre_username
                                ON cre_username.usr_id = pho_usr_id_create ';
        $queryParams = array();
    }

    // read albums from database
    $sql = 'SELECT pho.*, ' . $additionalFields . '
          FROM ' . TBL_PHOTOS . ' AS pho
               ' . $additionalTables . '
         WHERE pho_org_id = ? -- $organizationID
           AND pho_locked = false
      ORDER BY pho_timestamp_create DESC
         LIMIT 50';
    $queryParams[] = $organizationID;
    $statement = $gDb->queryPrepared($sql, $queryParams);

    $photoAlbum = new Album($gDb);

    // add the RSS items to the RssFeed object
    $rss = new RssFeed(
        $organizationName . ' - ' . $gL10n->get('SYS_PHOTO_ALBUMS'),
        $gCurrentOrganization->getValue('org_homepage'),
        $gL10n->get('SYS_RECENT_ALBUMS_OF_ORGA', array($organizationName)),
        $organizationName
    );

    // add the RSS items to the RssFeed object
    while ($row = $statement->fetch()) {
        $photoAlbum->clear();
        $photoAlbum->setArray($row);

        // set data for attributes of this entry

        // read folder structure to put them together and write to title
        $parents = '';
        $phoUuid = $photoAlbum->getValue('pho_uuid');
        $phoParentId = (int)$photoAlbum->getValue('pho_pho_id_parent');

        while ($phoParentId > 0) {
            // Capturing the parent album
            $sql = 'SELECT pho_name, pho_pho_id_parent
                  FROM ' . TBL_PHOTOS . '
                 WHERE pho_id = ? -- $phoParentId';
            $parentsStatement = $gDb->queryPrepared($sql, array($phoParentId));
            $admPhotoParent = $parentsStatement->fetch();

            $parents = $admPhotoParent['pho_name'] . ' > ' . $parents;
            $phoParentId = $admPhotoParent['pho_pho_id_parent'];
        }

        $description = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
        // Show end date only if different from start date
        if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin')) {
            $description = $gL10n->get('SYS_DATE_FROM_TO', array($description, $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'))));
        }
        $description .= '<br />' . $photoAlbum->countImages() . ' ' . $gL10n->get('SYS_PHOTOS_BY_VAR', array($photoAlbum->getPhotographer()));

        if (strlen($photoAlbum->getValue('pho_description')) > 0) {
            $description .= '<br /><br />' . $photoAlbum->getValue('pho_description') . '</p>';
        }

        // show the last five photos as examples
        if ($photoAlbum->getValue('pho_quantity') > 0) {
            $description .= '<br /><br />' . $gL10n->get('SYS_PREVIEW') . ':<br />';
            for ($photoNr = $photoAlbum->getValue('pho_quantity'); $photoNr >= $photoAlbum->getValue('pho_quantity') - 4 && $photoNr > 0; --$photoNr) {
                $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int)$photoAlbum->getValue('pho_id') . '/' . $photoNr . '.jpg';

                // show only photo if that photo exists
                if (is_file($photoPath)) {
                    $description .=
                        '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_presenter.php', array('photo_uuid' => $phoUuid, 'photo_nr' => $photoNr)) . '"><img
                    src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php', array('photo_uuid' => $phoUuid, 'photo_nr' => $photoNr,
                            'pho_begin' => $photoAlbum->getValue('pho_begin', 'Y-m-d'), 'thumb' => '1')) . '" alt="' . $photoNr . '" /></a>&nbsp;';
                }
            }
        }

        // add entry to RSS feed
        $rss->addItem(
            $parents . $photoAlbum->getValue('pho_name'),
            $description,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('photo_uuid' => $phoUuid)),
            $row['create_name'],
            DateTime::createFromFormat('Y-m-d H:i:s', $photoAlbum->getValue('pho_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            '',
            $row['pho_uuid']
        );
    }

    $rss->getRssFeed();
} catch (Exception $e) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($e->getMessage());
}
