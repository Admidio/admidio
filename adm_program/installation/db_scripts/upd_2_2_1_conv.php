<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.1
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// englische Bezeichnung der Bereiche in Systememails einbauen
$sql = 'SELECT * FROM '.TBL_TEXTS.' ORDER BY txt_id DESC';
$textsStatement = $gDb->query($sql);

while($rowTexts = $textsStatement->fetch())
{
    $rowTexts['txt_text'] = preg_replace('/#Betreff#/', '#subject#', $rowTexts['txt_text']);
    $rowTexts['txt_text'] = preg_replace('/#Inhalt#/',  '#content#', $rowTexts['txt_text']);

    $sql = 'UPDATE '.TBL_TEXTS.' SET txt_text = \''.addslashes($rowTexts['txt_text']). '\'
             WHERE txt_id = '.$rowTexts['txt_id'];
    $gDb->query($sql);
}

// Laenderbezeichnung durch ISOCODES ersetzen, damit die Laender sprachabhaengig angezeigt werden
foreach($gL10n->getCountries() as $key => $value)
{
    if($gPreferences['default_country'] === $value)
    {
        $sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = \''.$key.'\'
                 WHERE prf_name  LIKE \'default_country\'
                   AND prf_value LIKE \''.$value.'\'';
        $gDb->query($sql);
    }
}

// Laenderbezeichnung durch ISOCODES ersetzen, damit die Laender sprachabhaengig angezeigt werden
$sql = 'SELECT DISTINCT usd_value, usd_usf_id
          FROM '.TBL_USER_DATA.'
    INNER JOIN '.TBL_USER_FIELDS.'
            ON usf_id = usd_usf_id
         WHERE usf_name_intern LIKE \'COUNTRY\'
           AND LENGTH(usd_value) > 0 ';
$countriesStatement = $gDb->query($sql);

while($rowCountries = $countriesStatement->fetch())
{
    foreach($gL10n->getCountries() as $key => $value)
    {
        if($rowCountries['usd_value'] === $value)
        {
            $sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = \''.$key.'\'
                     WHERE usd_usf_id = '.$rowCountries['usd_usf_id'].'
                       AND usd_value LIKE \''.$value.'\'';
            $gDb->query($sql);
        }
    }
}

// Laenderbezeichnung durch ISOCODES ersetzen, damit die Laender sprachabhaengig angezeigt werden
$sql = 'SELECT distinct dat_country, dat_id FROM '.TBL_DATES.'
         WHERE length(dat_country) > 0 ';
$countriesStatement = $gDb->query($sql);

while($rowCountries = $countriesStatement->fetch())
{
    foreach($gL10n->getCountries() as $key => $value)
    {
        if($rowCountries['dat_country'] === $value)
        {
            $sql = 'UPDATE '.TBL_DATES.' SET dat_country = \''.$key.'\'
                     WHERE dat_id = '.$rowCountries['dat_id'].'
                       AND dat_country LIKE \''.$value.'\'';
            $gDb->query($sql);
        }
    }
}
