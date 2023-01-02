<?php
/**
 ***********************************************************************************************
 * RSS feed of announcements
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_ANNOUNCEMENTS')));

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ((int) $gSettingsManager->get('enable_announcements_module') !== 1) {
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Objekt anlegen
$announcements = new ModuleAnnouncements();

// ab hier wird der RSS-Feed zusammengestellt
$orgLongname = $gCurrentOrganization->getValue('org_longname');

// create RSS feed object with channel information
$rss = new RssFeed(
    $orgLongname.' - '.$getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('SYS_RECENT_ANNOUNCEMENTS_OF_ORGA', array($orgLongname)),
    $orgLongname
);

// Wenn Ankündigungen vorhanden laden
if ($announcements->getDataSetCount() > 0) {
    $announcement = new TableAnnouncement($gDb);
    $rows = $announcements->getDataSet(0, 10);
    // Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
    foreach ($rows['recordset'] as $row) {
        // ausgelesene Ankuendigungsdaten in Announcement-Objekt schieben
        $announcement->clear();
        $announcement->setArray($row);

        // add entry to RSS feed
        $rss->addItem(
            $announcement->getValue('ann_headline'),
            $announcement->getValue('ann_description'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php', array('ann_uuid' => $announcement->getValue('ann_uuid'), 'headline' => $getHeadline)),
            $row['create_name'],
            \DateTime::createFromFormat('Y-m-d H:i:s', $announcement->getValue('ann_timestamp_create', 'Y-m-d H:i:s'))->format('r'),
            $announcement->getValue('cat_name')
        );
    }
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
