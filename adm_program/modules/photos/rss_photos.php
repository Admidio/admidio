<?php
/**
 ***********************************************************************************************
 * RSS feed for photos
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 neuesten Fotoalben
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Ueberschrift fuer den RSS-Feed
 *             (Default) Fotoalben
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if (!$gSettingsManager->getBool('enable_rss'))
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ((int) $gSettingsManager->get('enable_photo_module') === 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require(__DIR__ . '/../../system/login_valid.php');
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('PHO_PHOTO_ALBUMS')));

if ((int) $gSettingsManager->get('system_show_create_edit') === 1)
{
    // show firstname and lastname of create and last change user
    $additionalFields = ' cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USER_DATA .' AS cre_surname
                                ON cre_surname.usd_usr_id = pho_usr_id_create
                               AND cre_surname.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                         LEFT JOIN '. TBL_USER_DATA .' AS cre_firstname
                                ON cre_firstname.usd_usr_id = pho_usr_id_create
                               AND cre_firstname.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')';
    $queryParams = array(
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
    );
}
else
{
    // show username of create and last change user
    $additionalFields = ' cre_username.usr_login_name AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USERS .' AS cre_username
                                ON cre_username.usr_id = pho_usr_id_create ';
    $queryParams = array();
}

// read albums from database
$sql = 'SELECT pho.*, '.$additionalFields.'
          FROM '.TBL_PHOTOS.' AS pho
               '.$additionalTables.'
         WHERE pho_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
           AND pho_locked = 0
      ORDER BY pho_timestamp_create DESC
         LIMIT 10';
$queryParams[] = $gCurrentOrganization->getValue('org_id');
$statement = $gDb->queryPrepared($sql, $queryParams);

$photoAlbum = new TablePhotos($gDb);

// ab hier wird der RSS-Feed zusammengestellt

// create RSS feed object with channel information
$orgLongname = $gCurrentOrganization->getValue('org_longname');
$rss = new RssFeed(
    $orgLongname . ' - ' . $getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('PHO_RECENT_ALBUMS_OF_ORGA', array($orgLongname)),
    $orgLongname
);

// Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch())
{
    // Daten in ein Photo-Objekt uebertragen
    $photoAlbum->clear();
    $photoAlbum->setArray($row);

    // set data for attributes of this entry

    // read folder structure to put them together and write to title
    $parents = '';
    $phoId       = (int) $photoAlbum->getValue('pho_id');
    $phoParentId = (int) $photoAlbum->getValue('pho_pho_id_parent');

    while($phoParentId > 0)
    {
        // Erfassen des Eltern Albums
        $sql = 'SELECT pho_name, pho_pho_id_parent
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_id = ? -- $phoParentId';
        $parentsStatement = $gDb->queryPrepared($sql, array($phoParentId));
        $admPhotoParent = $parentsStatement->fetch();

        // Link zusammensetzen
        $parents = $admPhotoParent['pho_name'].' > '.$parents;

        // Elternveranst
        $phoParentId = $admPhotoParent['pho_pho_id_parent'];
    }

    // Inhalt zusammensetzen
    $description = $gL10n->get('SYS_DATE').': '.$photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));
    // Enddatum nur wenn anders als startdatum
    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin'))
    {
        $description = $gL10n->get('SYS_DATE_FROM_TO', array($description, $photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'))));
    }
    $description .= '<br />'.$gL10n->get('PHO_PHOTOS').': '.$photoAlbum->countImages();
    $description .= '<br />'.$gL10n->get('PHO_PHOTOGRAPHER').': '.$photoAlbum->getValue('pho_photographers');

    // show the last five photos as examples
    if ($photoAlbum->getValue('pho_quantity') > 0)
    {
        $description .= '<br /><br />'.$gL10n->get('SYS_PREVIEW').':<br />';
        for ($photoNr = $photoAlbum->getValue('pho_quantity'); $photoNr >= $photoAlbum->getValue('pho_quantity')-4 && $photoNr > 0; --$photoNr)
        {
            $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $phoId . '/' . $photoNr . '.jpg';

            // show only photo if that photo exists
            if (is_file($photoPath))
            {
                $description .=
                    '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php', array('pho_id' => $phoId, 'photo_nr' => $photoNr)).'"><img
                    src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $phoId, 'photo_nr' => $photoNr,
                    'pho_begin' => $photoAlbum->getValue('pho_begin', 'Y-m-d'), 'thumb' => '1')).'" border="0" /></a>&nbsp;';
            }
        }
    }

    // add entry to RSS feed
    $rss->addItem(
        $parents . $photoAlbum->getValue('pho_name'),
        $description,
        safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php', array('pho_id' => $phoId)),
        $row['create_name'],
        \DateTime::createFromFormat('Y-m-d H:i:s', $photoAlbum->getValue('pho_timestamp_create', 'Y-m-d H:i:s'))->format('r')
    );
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
