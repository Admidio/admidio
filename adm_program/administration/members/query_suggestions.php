<?php
/******************************************************************************
 * Script mit HTML-Code fuer ein Feld der Eigenen-Liste-Konfiguration
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * query : hier steht der Suchstring drin
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen Querysuggestions empfangen
if (!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

if (isset($_GET['members']) && is_numeric($_GET['members']))
{
    $members = $_GET['members'];
}
else
{
    $members = 1;
}

if (isset($_GET['query']) && strlen($_GET['query']) > 0)
{
    $query = strStripTags($_GET['query']);
}
else
{
    $query = null;
}




$xml='<?xml version="1.0" encoding="utf-8" ?>';

if (!$query)
{
    // kein Query - keine Daten...
    $xml .= '<results></results>';
}
else
{
    if (isset($_SESSION['QuerySuggestions']))
    {
        // in der Session ist die Liste noch vorhanden,
        // das heisst es muss keine neue DB-Abfrage abgesetzt werden
        $querySuggestions = $_SESSION['QuerySuggestions'];
    }
    else
    {
        // erst mal die Benutzerliste aus der DB holen und in der Session speichern
        if($members == true)
        {
            $sql    = 'SELECT DISTINCT last_name.usd_value as last_name, first_name.usd_value as first_name
                         FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_USERS. '
                         LEFT JOIN '. TBL_USER_DATA. ' as last_name
                           ON last_name.usd_usr_id = usr_id
                          AND last_name.usd_usf_id = '. $g_current_user->getProperty('Nachname', 'usf_id'). '
                         LEFT JOIN '. TBL_USER_DATA. ' as first_name
                           ON first_name.usd_usr_id = usr_id
                          AND first_name.usd_usf_id = '. $g_current_user->getProperty('Vorname', 'usf_id'). '
                        WHERE usr_valid = 1
                          AND mem_usr_id = usr_id
                          AND mem_rol_id = rol_id
                          AND mem_begin <= "'.DATE_NOW.'"
                          AND mem_end    > "'.DATE_NOW.'"
                          AND rol_valid  = 1
                          AND rol_cat_id = cat_id
                          AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
                        ORDER BY last_name, first_name ';
        }
        else
        {
            $sql    = 'SELECT last_name.usd_value as last_name, first_name.usd_value as first_name
                         FROM '. TBL_USERS. '
                         LEFT JOIN '. TBL_USER_DATA. ' as last_name
                           ON last_name.usd_usr_id = usr_id
                          AND last_name.usd_usf_id = '. $g_current_user->getProperty('Nachname', 'usf_id'). '
                         LEFT JOIN '. TBL_USER_DATA. ' as first_name
                           ON first_name.usd_usr_id = usr_id
                          AND first_name.usd_usf_id = '. $g_current_user->getProperty('Vorname', 'usf_id'). '
                        WHERE usr_valid = 1
                        ORDER BY last_name, first_name ';
        }
        $result_mgl = $g_db->query($sql);

        // Jetzt das komplette resultSet in ein Array schreiben...
        while($row = $g_db->fetch_object($result_mgl))
        {
            $entry = array('lastName' => $row->last_name, 'firstName' => $row->first_name);
            $querySuggestions[]=$entry;
        }

        // Jetzt noch das Array für zukuenftiges Nutzen in der Session speichern
        $_SESSION['QuerySuggestions'] = $querySuggestions;
    }


    // ab hier werden jetzt die zur Query passenden Eintraege ermittelt...
    $match = array();
    $q = admStrToLower($query);

    foreach ($querySuggestions as $suggest)
    {
        $firstName = admStrToLower($suggest['firstName']);
        $lastName  = admStrToLower($suggest['lastName']);

        if (strpos($lastName, $q)===0
        or  strpos($firstName,$q)===0
        or  strpos($firstName. " ". $lastName,  str_replace(',', '', $q)) === 0
        or  strpos($lastName.  " ". $firstName, str_replace(',', '', $q)) === 0)
        {
            $match[]='<rs>'. $suggest['lastName']. ', '. $suggest['firstName']. '</rs>';
        }
    }
    //sort($match);
    $xml .= "<results>\n".implode("\n",$match).'</results>';
}
header('Content-Type: text/xml');
echo $xml;

?>