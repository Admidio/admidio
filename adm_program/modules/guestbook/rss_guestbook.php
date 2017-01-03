<?php
/**
 ***********************************************************************************************
 * RSS feed for the guestbook
 *
 * @copyright 2004-2017 The Admidio Team
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

require_once('../../system/common.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($gPreferences['enable_rss'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_RSS_DISABLED'));
    // => EXIT
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));

// die 10 letzten Eintraege aus der DB fischen...
$sql = 'SELECT *
          FROM '.TBL_GUESTBOOK.'
         WHERE gbo_org_id = '. $gCurrentOrganization->getValue('org_id').'
           AND gbo_locked = 0
      ORDER BY gbo_timestamp_create DESC
         LIMIT 10';
$statement = $gDb->query($sql);

// ab hier wird der RSS-Feed zusammengestellt

// create RSS feed object with channel information
$rss = new RSSfeed($gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline,
                   $gCurrentOrganization->getValue('org_homepage'),
                   $gL10n->get('GBO_LATEST_GUESTBOOK_ENTRIES_OF_ORGA',
                   $gCurrentOrganization->getValue('org_longname')),
                   $gCurrentOrganization->getValue('org_longname'));
$guestbook = new TableGuestbook($gDb);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $statement->fetch())
{
    // ausgelesene Gaestebuchdaten in Guestbook-Objekt schieben
    $guestbook->clear();
    $guestbook->setArray($row);

    // set data for attributes of this entry
    $title       = $guestbook->getValue('gbo_name');
    $description = $guestbook->getValue('gbo_text');
    $link        = ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php?id='. $guestbook->getValue('gbo_id');
    $author      = $guestbook->getValue('gbo_name');
    $pubDate     = date('r', strtotime($guestbook->getValue('gbo_timestamp_create')));

    // add entry to RSS feed
    $rss->addItem($title, $description, $link, $author, $pubDate);
}

// jetzt nur noch den Feed generieren lassen
$rss->getRssFeed();
