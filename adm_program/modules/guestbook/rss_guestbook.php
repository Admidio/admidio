<?php
/**
 ***********************************************************************************************
 * RSS feed for the guestbook
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Creates a RSS 2.0 feed for guestbook entries with help of the RSS class
 *
 * Spezification of RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline - Headline of RSS feed
 *            (Default) Guestbook
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if (!$gSettingsManager->getBool('enable_rss')) {
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') !== 1) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// die 10 letzten Eintraege aus der DB fischen...
$sql = 'SELECT *
          FROM '.TBL_GUESTBOOK.'
         WHERE gbo_org_id = ? -- $gCurrentOrgId
           AND gbo_locked = false
      ORDER BY gbo_timestamp_create DESC
         LIMIT 10';
$statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

// ab hier wird der RSS-Feed zusammengestellt

// create RSS feed object with channel information
$orgLongname = $gCurrentOrganization->getValue('org_longname');
$rss = new RssFeed(
    $orgLongname . ' - ' . $getHeadline,
    $gCurrentOrganization->getValue('org_homepage'),
    $gL10n->get('GBO_LATEST_GUESTBOOK_ENTRIES_OF_ORGA', array($orgLongname)),
    $orgLongname
);
$guestbook = new TableGuestbook($gDb);

// Dem RssFeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch()) {
    // ausgelesene Gaestebuchdaten in Guestbook-Objekt schieben
    $guestbook->clear();
    $guestbook->setArray($row);

    // add entry to RSS feed
    $rss->addItem(
        $guestbook->getValue('gbo_name'),
        $guestbook->getValue('gbo_text'),
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('id' => (int) $guestbook->getValue('gbo_id'))),
        $guestbook->getValue('gbo_name'),
        \DateTime::createFromFormat('Y-m-d H:i:s', $guestbook->getValue('gbo_timestamp_create', 'Y-m-d H:i:s'))->format('r')
    );
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
