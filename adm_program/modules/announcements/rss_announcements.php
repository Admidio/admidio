<?php
/******************************************************************************
 * RSS - Feed fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 neuesten Ankuendigungen
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Ueberschrift fuer den RSS-Feed
 *             (Default) Ankuendigungen
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/rss.php');
require_once('../../system/classes/table_announcement.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
}

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($gPreferences['enable_announcements_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('ANN_ANNOUNCEMENTS'));

// alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = '';
$arr_ref_orgas = $gCurrentOrganization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $key => $value)
{
	$organizations = $organizations. '\''.$value.'\',';
}
$organizations = $organizations. '\''. $gCurrentOrganization->getValue('org_shortname'). '\'';

// die neuesten 10 Ankuendigungen aus der DB fischen...
$sql = 'SELECT ann.*, 
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '. TBL_ANNOUNCEMENTS. ' ann
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = ann_usr_id_create
           AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = ann_usr_id_create
           AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = ann_usr_id_change
           AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = ann_usr_id_change
           AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
         WHERE (  ann_org_shortname = \''. $gCurrentOrganization->getValue('org_shortname').'\'
               OR ( ann_global = 1 AND ann_org_shortname IN ('.$organizations.') ))
         ORDER BY ann_timestamp_create DESC
         LIMIT 10 ';
$result = $gDb->query($sql);

// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed($gCurrentOrganization->getValue('org_homepage'), $gCurrentOrganization->getValue('org_longname'). ' - '. $getHeadline, 
		$gL10n->get('ANN_RECENT_ANNOUNCEMENTS_OF_ORGA', $gCurrentOrganization->getValue('org_longname')));
$announcement = new TableAnnouncement($gDb);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $gDb->fetch_object($result))
{
    // ausgelesene Ankuendigungsdaten in Announcement-Objekt schieben
    $announcement->clear();
    $announcement->setArray($row);

    // Die Attribute fuer das Item zusammenstellen
    $title = $announcement->getValue('ann_headline');
    $link  = $g_root_path.'/adm_program/modules/announcements/announcements.php?id='.$announcement->getValue('ann_id').'&headline='.$getHeadline;
    $description = '<b>'.$announcement->getValue('ann_headline').'</b>';

    // Beschreibung und Link zur Homepage ausgeben
    $description = $description. '<br /><br />'. $announcement->getValue('ann_description').
                   '<br /><br /><a href="'.$link.'">'. $gL10n->get('SYS_LINK_TO', $gCurrentOrganization->getValue('org_homepage')). '</a>';

    // Den Autor und letzten Bearbeiter der Ankuendigung ermitteln und ausgeben
    $description = $description. '<br /><br /><i>'.$gL10n->get('SYS_CREATED_BY', $row->create_firstname. ' '. $row->create_surname, $announcement->getValue('ann_timestamp_create')). '</i>';

    if($row->ann_usr_id_change > 0)
    {
		$description = $description. '<br /><i>'.$gL10n->get('SYS_LAST_EDITED_BY', $row->change_firstname. ' '. $row->change_surname, $announcement->getValue('ann_timestamp_change')). '</i>';
    }
                
    $pubDate = date('r',strtotime($announcement->getValue('ann_timestamp_create')));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}

// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>