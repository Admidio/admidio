<?php
/**
 ***********************************************************************************************
 * RSS feed for photos
 *
 * @copyright 2004-2017 The Admidio Team
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

require_once('../../system/common.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('PHO_PHOTO_ALBUMS')));

if($gPreferences['system_show_create_edit'] == 1)
{
    // show firstname and lastname of create and last change user
    $additionalFields = '
        cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USER_DATA .' cre_surname
                                ON cre_surname.usd_usr_id = pho_usr_id_create
                               AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                         LEFT JOIN '. TBL_USER_DATA .' cre_firstname
                                ON cre_firstname.usd_usr_id = pho_usr_id_create
                               AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
}
else
{
    // show username of create and last change user
    $additionalFields = ' cre_username.usr_login_name AS create_name ';
    $additionalTables = '
                         LEFT JOIN '. TBL_USERS .' cre_username
                                ON cre_username.usr_id = pho_usr_id_create ';
}

// read albums from database
$sql = 'SELECT pho.*, '.$additionalFields.'
          FROM '.TBL_PHOTOS.' pho
               '.$additionalTables.'
         WHERE (   pho_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               AND pho_locked = 0)
      ORDER BY pho_timestamp_create DESC
         LIMIT 10';
$statement = $gDb->query($sql);

$photo_album = new TablePhotos($gDb);

// ab hier wird der RSS-Feed zusammengestellt

// create RSS feed object with channel information
$rss = new RSSfeed($gCurrentOrganization->getValue('org_longname').' - '.$getHeadline,
            $gCurrentOrganization->getValue('org_homepage'),
            $gL10n->get('PHO_RECENT_ALBUMS_OF_ORGA', $gCurrentOrganization->getValue('org_longname')),
            $gCurrentOrganization->getValue('org_longname'));

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch())
{
    // Daten in ein Photo-Objekt uebertragen
    $photo_album->clear();
    $photo_album->setArray($row);

    // set data for attributes of this entry

    // read folder structure to put them together and write to title
    $parents = '';
    $pho_parent_id = $photo_album->getValue('pho_pho_id_parent');

    while($pho_parent_id > 0)
    {
        // Erfassen des Eltern Albums
        $sql = ' SELECT *
                  FROM '.TBL_PHOTOS.'
                 WHERE pho_id = '.$pho_parent_id;
        $parentsStatement = $gDb->query($sql);
        $adm_photo_parent = $parentsStatement->fetch();

        // Link zusammensetzen
        $parents = $adm_photo_parent['pho_name'].' > '.$parents;

        // Elternveranst
        $pho_parent_id = $adm_photo_parent['pho_pho_id_parent'];
    }

    $title   = $parents.$photo_album->getValue('pho_name');
    $link    = ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='. $photo_album->getValue('pho_id');
    $author  = $row['create_name'];
    $pubDate = date('r', strtotime($photo_album->getValue('pho_timestamp_create')));

    // Inhalt zusammensetzen
    $description = $gL10n->get('SYS_DATE').': '.$photo_album->getValue('pho_begin', $gPreferences['system_date']);
    // Enddatum nur wenn anders als startdatum
    if($photo_album->getValue('pho_end') !== $photo_album->getValue('pho_begin'))
    {
        $description = $gL10n->get('SYS_DATE_FROM_TO', $description, $photo_album->getValue('pho_end', $gPreferences['system_date']));
    }
    $description = $description. '<br /> '.$gL10n->get('PHO_PHOTOS').': '.$photo_album->countImages();
    $description = $description. '<br />'.$gL10n->get('PHO_PHOTOGRAPHER').': '.$photo_album->getValue('pho_photographers');

    // show the last five photos as examples
    if($photo_album->getValue('pho_quantity') >0)
    {
        $description = $description. '<br /><br />'.$gL10n->get('SYS_PREVIEW').':<br />';
        for($photoNr = $photo_album->getValue('pho_quantity'); $photoNr >= $photo_album->getValue('pho_quantity')-4 && $photoNr > 0; --$photoNr)
        {
            $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id') . '/' . $photoNr . '.jpg';

            // show only photo if that photo exists
            if (is_file($photoPath))
            {
                $description = $description.
                    '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php?pho_id='.$photo_album->getValue('pho_id').'&amp;photo_nr='.$photoNr.'"><img
                     src="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php?pho_id='.$photo_album->getValue('pho_id').'&amp;photo_nr='.$photoNr.
                     '&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;thumb=1" border="0" /></a>&nbsp;';
            }
        }
    }

    // add entry to RSS feed
    $rss->addItem($title, $description, $link, $author, $pubDate);
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
