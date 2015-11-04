<?php
/******************************************************************************
 * RSS feed of announcements
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 neuesten Ankuendigungen
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline  - Headline for RSS-Feed
 *             (Default) Announcements
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('ANN_ANNOUNCEMENTS')));

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

//Objekt anlegen
$announcements = new ModuleAnnouncements();

/*** ab hier wird der RSS-Feed zusammengestellt**/

// create RSS feed object with channel information
$rss = new RSSfeed($gCurrentOrganization->getValue('org_longname').' - '.$getHeadline,
            $gCurrentOrganization->getValue('org_homepage'),
            $gL10n->get('ANN_RECENT_ANNOUNCEMENTS_OF_ORGA', $gCurrentOrganization->getValue('org_longname')),
            $gCurrentOrganization->getValue('org_longname'));

//Wenn Ankündigungen vorhanden laden
if($announcements->getDataSetCount()>0)
{
    $announcement = new TableAnnouncement($gDb);
    $rows = $announcements->getDataset(0, 10);
    // Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
    foreach ($rows['recordset'] as $row)
    {
        // ausgelesene Ankuendigungsdaten in Announcement-Objekt schieben
        $announcement->clear();
        $announcement->setArray($row);

        // set data for attributes of this entry
        $title       = $announcement->getValue('ann_headline');
        $description = $announcement->getValue('ann_description');
        $link        = $g_root_path.'/adm_program/modules/announcements/announcements.php?id='.$announcement->getValue('ann_id').'&headline='.$getHeadline;
        $author      = $row['create_name'];
        $pubDate     = date('r', strtotime($announcement->getValue('ann_timestamp_create')));

        // add entry to RSS feed
        $rss->addItem($title, $description, $link, $author, $pubDate);
    }
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();
