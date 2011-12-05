<?php
/******************************************************************************
 * ical - Feed fuer Termine
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline: - Ueberschrift fuer den Ics-Feed
 *             (Default) Termine
 * mode:     1 - Textausgabe
 *           2 - Download
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/rss.php');
require_once('../../system/classes/table_date.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist bzw. das Modul oeffentlich zugaenglich ist
if ($gPreferences['enable_dates_ical'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_ICAL_DISABLED'));
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Initialize and check the parameters
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('DAT_DATES'));
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1, true);

// alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = '';
$arr_orgas = $gCurrentOrganization->getReferenceOrganizations(true, true);

foreach($arr_orgas as $org_id => $value)
{
    $organizations = $organizations. $org_id. ', ';
}
$organizations = $organizations. $gCurrentOrganization->getValue('org_id');

$hidden = '';
if ($gValidLogin == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $hidden = ' AND cat_hidden = 0 ';
}

// aktuelle Termine aus DB holen die zur Orga passen
$sql = 'SELECT cat.*, dat.*, 
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname 
          FROM '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = dat_usr_id_create
           AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = dat_usr_id_create
           AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = dat_usr_id_change
           AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = dat_usr_id_change
           AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
         WHERE dat_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR (   dat_global  = 1
                  AND cat_org_id IN ('.$organizations.') ))
           AND (  dat_begin >= \''.DATETIME_NOW.'\' 
               OR dat_end   >= \''.DATETIME_NOW.'\' )
               '.$hidden.'
         ORDER BY dat_begin ASC';
$result = $gDb->query($sql);

// ab hier wird der iCal zusammengestellt

// Ein icalfeed-Objekt erstellen
$date = new TableDate($gDb);
$iCal = $date->getIcalHeader();

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $gDb->fetch_array($result))
{
    // ausgelesene Termindaten in Date-Objekt schieben
    $date->clear();
    $date->setArray($row);
    $iCal .= $date->getIcalVEvent($_SERVER['HTTP_HOST']);
}
$iCal .= $date->getIcalFooter();

if($mode == 2)
{
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename='. urlencode($getHeadline). '.ics');
    // noetig fuer IE, da ansonsten der Download mit SSL nicht funktioniert
    header('Cache-Control: private');
    header('Pragma: public');
}    
echo $iCal;
?>