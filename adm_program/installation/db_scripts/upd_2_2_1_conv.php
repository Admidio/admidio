<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.1
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// englische Bezeichnung der Bereiche in Systememails einbauen
$sql = 'SELECT * FROM '.TBL_TEXTS.' ORDER BY txt_id DESC';
$textsStatement = $gDb->query($sql);

while($row_texts = $textsStatement->fetch())
{
    $row_texts['txt_text'] = preg_replace('/#Betreff#/', '#subject#', $row_texts['txt_text']);
    $row_texts['txt_text'] = preg_replace('/#Inhalt#/', '#content#', $row_texts['txt_text']);

    $sql = 'UPDATE '.TBL_TEXTS.' SET txt_text = \''.addslashes($row_texts['txt_text']). '\'
             WHERE txt_id = '.$row_texts['txt_id'];
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

while($row_countries = $countriesStatement->fetch())
{
    foreach($gL10n->getCountries() as $key => $value)
    {
        if($row_countries['usd_value'] === $value)
        {
            $sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = \''.$key.'\'
                     WHERE usd_usf_id = '.$row_countries['usd_usf_id'].'
                       AND usd_value LIKE \''.$value.'\'';
            $gDb->query($sql);
        }
    }
}

// Laenderbezeichnung durch ISOCODES ersetzen, damit die Laender sprachabhaengig angezeigt werden
$sql = 'SELECT distinct dat_country, dat_id FROM '.TBL_DATES.'
         WHERE length(dat_country) > 0 ';
$countriesStatement = $gDb->query($sql);

while($row_countries = $countriesStatement->fetch())
{
    foreach($gL10n->getCountries() as $key => $value)
    {
        if($row_countries['dat_country'] === $value)
        {
            $sql = 'UPDATE '.TBL_DATES.' SET dat_country = \''.$key.'\'
                     WHERE dat_id = '.$row_countries['dat_id'].'
                       AND dat_country LIKE \''.$value.'\'';
            $gDb->query($sql);
        }
    }
}
