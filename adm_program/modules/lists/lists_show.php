<?php
/******************************************************************************
 * Listen anzeigen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   : Ausgabeart   (html, print, csv-ms, csv-ms-2k, csv-oo)
 * lst_id : ID der Listenkonfiguration, die angezeigt werden soll
 *          Wird keine ID uebergeben, wird die Default-Konfiguration angezeigt
 * rol_id : Rolle, fuer die die Funktion dargestellt werden soll
 * start  : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen 
 * show_members : 0 - (Default) aktive Mitglieder der Rolle anzeigen
 *                1 - Ehemalige Mitglieder der Rolle anzeigen
 *                2 - Aktive und ehemalige Mitglieder der Rolle anzeigen
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/list_configuration.php');
require('../../system/classes/table_roles.php');

// lokale Variablen der Uebergabevariablen initialisieren
$arr_mode   = array('csv-ms', 'csv-ms-2k', 'csv-oo', 'html', 'print');
$req_rol_id = 0;
$req_lst_id = 0;
$req_start  = 0;
$show_members = 0;

// Uebergabevariablen pruefen

$req_mode   = strStripTags($_GET['mode']);

if(in_array($req_mode, $arr_mode) == false)
{
    $g_message->show('invalid');
}

if(isset($_GET['lst_id']) && is_numeric($_GET['lst_id']))
{
    $req_lst_id = $_GET['lst_id'];
}
else
{
	// Default-Konfiguration laden
	$sql = 'SELECT lst_id FROM '. TBL_LISTS. '
	         WHERE lst_org_id  = '. $g_current_organization->getValue('org_id'). '
	           AND lst_default = 1 ';
	$g_db->query($sql);
	$row = $g_db->fetch_array();
	$req_lst_id = $row[0];
}

if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show('invalid');
    }
    $req_rol_id = $_GET['rol_id'];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET['start']) == false)
    {
        $g_message->show('invalid');
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['show_members']) && is_numeric($_GET['show_members']))
{
    $show_members = $_GET['show_members'];
}

if($req_rol_id > 0)
{
    $role_ids[] = $req_rol_id;
}
else
{
    $role_ids = $_SESSION['role_ids'];
    $req_rol_id = $role_ids[0];
    unset($_SESSION['role_ids']);
}

//Testen ob Recht zur Listeneinsicht besteht
if(!$g_current_user->viewRole($req_rol_id))
{
    $g_message->show('norights');
}

//SESSION array fuer bilder initialisieren
if($g_preferences['profile_photo_storage'] == 0)
{
	$_SESSION['profilphoto'] = array();
}

if($req_mode == 'csv-ms')
{
    $separator    = "\t"; // Microsoft XP und neuer braucht ein Semicolon
    $value_quotes = '"';
    $req_mode     = 'csv';
}
else if($req_mode == 'csv-ms-2k')
{
    $separator    = ','; // Microsoft 2000 und aelter braucht ein Komma
    $value_quotes = '"';
    $req_mode     = 'csv';
}
else if($req_mode == 'csv-oo')
{
    $separator    = ',';    // fuer CSV-Dateien
    $value_quotes = '"';   // Werte muessen mit Anfuehrungszeichen eingeschlossen sein
    $req_mode     = 'csv';
}
else
{
    $separator    = ',';    // fuer CSV-Dateien
    $value_quotes = '';
}

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('usr_login_name' => 'Benutzername',
                      'usr_photo'      => 'Foto',
                      'mem_begin'      => 'Beginn',
                      'mem_end'        => 'Ende',
                      'mem_leader'     => 'Leiter'
                      );

if($req_mode == 'html')
{
    $class_table           = 'tableList';
    $class_sub_header      = 'tableSubHeader';
    $class_sub_header_font = 'tableSubHeaderFont';
}
else if($req_mode == 'print')
{
    $class_table           = 'tableListPrint';
    $class_sub_header      = 'tableSubHeaderPrint';
    $class_sub_header_font = 'tableSubHeaderFontPrint';
}

$main_sql  = '';   // enthaelt das Haupt-Sql-Statement fuer die Liste
$str_csv   = '';   // enthaelt die komplette CSV-Datei als String
$leiter    = 0;    // Gruppe besitzt Leiter

// Rollenobjekt erzeugen
$role = new TableRoles($g_db, $req_rol_id);
// falls ehemalige Rolle, dann auch nur ehemalige Mitglieder anzeigen
if($role->getValue('rol_valid') == 0)
{
    $show_members = 1;
}

// Listenkonfigurationsobjekt erzeugen und entsprechendes SQL-Statement erstellen
$list = new ListConfiguration($g_db, $req_lst_id);
$main_sql = $list->getSQL($role_ids, $show_members);

// SQL-Statement der Liste ausfuehren und pruefen ob Daten vorhanden sind
$result_list = $g_db->query($main_sql);

$num_members = $g_db->num_rows($result_list);

if($num_members == 0)
{
    // Es sind keine Daten vorhanden !
    $g_message->show('nodata', '', 'Hinweis');
}

if($num_members < $req_start)
{
    $g_message->show('invalid');
}

if($req_mode == 'html' && $req_start == 0)
{
    // Url fuer die Zuruecknavigation merken, aber nur in der Html-Ansicht
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}

if($req_mode != 'csv')
{
    // Html-Kopf wird geschrieben
    if($req_mode == 'print')
    {
    	header('Content-type: text/html; charset=utf-8');
        echo '
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
        <head>
            <!-- (c) 2004 - 2009 The Admidio Team - http://www.admidio.org -->
            
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        
            <title>'. $g_current_organization->getValue("org_longname"). ' - Liste - '. $role->getValue("rol_name"). '</title>
            
            <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/print.css" />
            <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>

            <!--[if lt IE 7]>
            <script type="text/javascript"><!--
                window.attachEvent("onload", correctPNG);
            --></script>
            <![endif]-->

            <style type="text/css">
                @page { size:landscape; }
            </style>
        </head>
        <body class="bodyPrint">';
    }
    else
    {
        $g_layout['title']    = 'Liste - '. $role->getValue('rol_name');
        $g_layout['includes'] = false;
        $g_layout['header']   = '
            <style type="text/css">
                body {
                    margin: 20px;
                }
            </style>
            <script type="text/javascript"><!--
                function exportList(element)
                {
                    var sel_list = element.value;

                    if(sel_list.length > 1)
                    {
                        self.location.href = "'. $g_root_path. '/adm_program/modules/lists/lists_show.php?" +
                            "lst_id='. $req_lst_id. '&rol_id='. $req_rol_id. '&mode=" + sel_list + "&show_members='.$show_members.'";
                    }
                }
            //--></script>';
        require(THEME_SERVER_PATH. "/overall_header.php");
    }

    if($show_members == 0)
    {
    	$member_status = 'Aktive Mitglieder';
    }
    elseif($show_members == 1)
    {
    	$member_status = 'Ehemalige Mitglieder';
    }
    elseif($show_members == 2)
    {
    	$member_status = 'Aktive und ehemalige Mitglieder';
    }

    echo '<h1 class="moduleHeadline">'. $role->getValue('rol_name'). ' &#40;'.$role->getValue('cat_name').'&#41;</h1>
    <h3>';

    //Beschreibung der Rolle einblenden
    if(strlen($role->getValue('rol_description')) > 0)
    {
        echo $role->getValue('rol_description'). ' - ';
    }
    
    echo $member_status.'</h3>';

    if($req_mode == 'html')
    {
        echo '<ul class="iconTextLinkList">
            <li>
                <span class="iconTextLink">';
                // Navigationspunkt zum uebergeordneten Punkt dieser Liste
                if(strpos($_SESSION['navigation']->getPreviousUrl(), 'mylist') === false)
                {
                    echo '
                    <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                    src="'. THEME_PATH. '/icons/application_view_list.png" alt="Zurück" /></a>
                    <a href="'.$g_root_path.'/adm_program/system/back.php">Listenübersicht</a>';
                }
                else
                {
                    echo '
                    <a href="'.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $req_lst_id. '&rol_id='. $req_rol_id. '&show_members='.$show_members.'"><img
                    src="'. THEME_PATH. '/icons/application_form.png" alt="Zurück" /></a>
                    <a href="'.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $req_lst_id. '&rol_id='. $req_rol_id. '&show_members='.$show_members.'">Konfiguration Eigene Liste</a>';
                }
            echo '</span>
            </li>';

            // Aufruf des Mailmoduls mit dieser Rolle
            if($g_current_user->mailRole($role->getValue("rol_id")) && $g_preferences['enable_mail_module'] == 1)
            {
                echo '<li>
                    <span class="iconTextLink">
                        <a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$req_rol_id.'"><img
                        src="'. THEME_PATH. '/icons/email.png" alt="E-Mail an Mitglieder"  title="E-Mail an Mitglieder" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$req_rol_id.'">E-Mail an Mitglieder</a>
                    </span>
                </li>';
            }

            // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
            if((  $g_current_user->assignRoles() 
               || isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')))
            && $role->getValue('rol_valid') == 1)
            {
                // der Webmasterrolle darf nur von Webmastern neue User zugeordnet werden
                if($role->getValue('rol_name')  != 'Webmaster'
                || ($role->getValue('rol_name') == 'Webmaster' && $g_current_user->isWebmaster()))
                {
                    echo '
                    <li>
                        <span class="iconTextLink">
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='. $role->getValue('rol_id'). '"><img 
                                src="'. THEME_PATH. '/icons/add.png" alt="Mitglieder zuordnen" title="Mitglieder zuordnen" /></a>
                            <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='. $role->getValue('rol_id'). '">Mitglieder zuordnen</a>
                        </span>
                    </li>';
                }
            }

            echo '<li>
                <span class="iconTextLink">
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&amp;mode=print&amp;rol_id='.$req_rol_id.'\', \'_blank\')"><img
                    src="'. THEME_PATH. '/icons/print.png" alt="Druckvorschau" /></a>
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&amp;mode=print&amp;rol_id='.$req_rol_id.'\', \'_blank\')">Druckvorschau</a>
                </span>
            </li>
            <li>
                <span class="iconTextLink">
                    <img src="'. THEME_PATH. '/icons/database_out.png" alt="Exportieren" />
                    <select size="1" name="export_mode" onchange="exportList(this)">
                        <option value="" selected="selected">Exportieren nach ...</option>
                        <option value="csv-ms">Microsoft Excel</option>
                        <option value="csv-ms-2k">Microsoft Excel 97/2000</option>
                        <option value="csv-oo">CSV-Datei (OpenOffice)</option>
                    </select>
                </span>
            </li>   
        </ul>';
    }
}

if($req_mode != 'csv')
{
    // Tabellenkopf schreiben
    echo '<table class="'.$class_table.'" style="width: 95%;" cellspacing="0">
        <thead><tr>';
}

// Spalten-Ueberschriften
for($column_number = 1; $column_number <= $list->countColumns(); $column_number++)
{
    $column = $list->getColumnObject($column_number);
    $align = 'left';

    // den Namen des Feldes ermitteln
    if($column->getValue('lsc_usf_id') > 0)
    {
        // benutzerdefiniertes Feld
        $usf_id = $column->getValue('lsc_usf_id');
        $col_name = $g_current_user->getPropertyById($usf_id, 'usf_name');

        if($g_current_user->getPropertyById($usf_id, 'usf_type') == 'CHECKBOX'
        || $g_current_user->getPropertyById($usf_id, 'usf_name') == 'Geschlecht')
        {
            $align = 'center';
        }
        elseif($g_current_user->getPropertyById($usf_id, 'usf_type') == 'NUMERIC')
        {
            $align = 'right';
        }
    }
    else
    {
        $usf_id = 0;
        $col_name = $arr_col_name[$column->getValue('lsc_special_field')];
    }

    // versteckte Felder duerfen nur von Leuten mit entsprechenden Rechten gesehen werden
    if($usf_id == 0
    || $g_current_user->editUsers()
    || $g_current_user->getPropertyById($usf_id, 'usf_hidden') == 0)
    {
        if($req_mode == 'csv')
        {
            if($column_number == 1)
            {
                // die Laufende Nummer noch davorsetzen
                $str_csv = $str_csv. $value_quotes. 'Nr.'. $value_quotes;
            }
            $str_csv = $str_csv. $separator. $value_quotes. $col_name. $value_quotes;
        }
        else
        {                
            if($column_number == 1)
            {
                // die Laufende Nummer noch davorsetzen
                echo '<th style="text-align: '.$align.';">Nr.</th>';
            }
            echo '<th style="text-align: '.$align.';">'.$col_name.'</th>';
        }
    }
}  // End-For

if($req_mode == 'csv')
{
    $str_csv = $str_csv. "\n";
}
else
{
    echo '</tr></thead><tbody>';
}

$irow        = $req_start + 1;  // Zahler fuer die jeweilige Zeile
$leader_head = -1;              // Merker um Wechsel zwischen Leiter und Mitglieder zu merken
if($req_mode == 'html' && $g_preferences['lists_members_per_page'] > 0)
{
    $members_per_page = $g_preferences['lists_members_per_page'];     // Anzahl der Mitglieder, die auf einer Seite angezeigt werden
}
else
{
    $members_per_page = $num_members;
}

// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$g_db->data_seek($result_list, $req_start))
{
    $g_message->show('invalid');
}

for($j = 0; $j < $members_per_page && $j + $req_start < $num_members; $j++)
{
    if($row = $g_db->fetch_array($result_list))
    {
        if($req_mode != 'csv')
        {
            // erst einmal pruefen, ob es ein Leiter ist, falls es Leiter in der Gruppe gibt, 
            // dann muss noch jeweils ein Gruppenkopf eingefuegt werden
            if($leader_head != $row['mem_leader']
            && ($row['mem_leader'] != 0 || $leader_head != -1))
            {
                if($row['mem_leader'] == 1)
                {
                    $title = 'Leiter';
                }
                else
                {
                    $title = 'Teilnehmer';
                }
                echo '<tr>
                    <td class="'.$class_sub_header.'" colspan="'. ($list->countColumns() + 1). '">
                        <div class="'.$class_sub_header_font.'" style="float: left;">&nbsp;'.$title.'</div>
                    </td>
                </tr>';
                $leader_head = $row['mem_leader'];
            }
        }

        if($req_mode == 'html')
        {
            echo '<tr class="tableMouseOver" style="cursor: pointer"
            onclick="window.location.href=\''. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '\'">';
        }
        else if($req_mode == 'print')
        {
            echo '<tr>';
        }

        // Felder zu Datensatz
        for($column_number = 1; $column_number <= $list->countColumns(); $column_number++)
        {
            $column = $list->getColumnObject($column_number);

            // da im SQL noch mem_leader und usr_id vor die eigentlichen Spalten kommen,
            // muss der Index auf row direkt mit 2 anfangen
            $sql_column_number = $column_number + 1;

            if($column->getValue('lsc_usf_id') > 0)
            {
                // pruefen, ob ein benutzerdefiniertes Feld und Kennzeichen merken
                $b_user_field = true;
                $usf_id = $column->getValue('lsc_usf_id');
            }
            else
            {
                $b_user_field = false;
                $usf_id = 0;
            }

            // versteckte Felder duerfen nur von Leuten mit entsprechenden Rechten gesehen werden
            if($usf_id == 0
            || $g_current_user->editUsers()
            || $g_current_user->getPropertyById($usf_id, 'usf_hidden') == 0)
            {
                if($req_mode != 'csv')
                {
                    $align = 'left';
                    if($b_user_field == true)
                    {
                        if($g_current_user->getPropertyById($usf_id, 'usf_type') == 'CHECKBOX'
                        || $g_current_user->getPropertyById($usf_id, 'usf_name') == 'Geschlecht')
                        {
                            $align = 'center';
                        }
                        elseif($g_current_user->getPropertyById($usf_id, 'usf_type') == 'NUMERIC')
                        {
                            $align = 'right';
                        }
                    }
    
                    if($column_number == 1)
                    {
                        // die Laufende Nummer noch davorsetzen
                        echo '<td style="text-align: '.$align.';">'.$irow.'</td>';
                    }
                    echo '<td style="text-align: '.$align.';">';
                }
                else
                {
                    if($column_number == 1)
                    {
                        // erste Spalte zeigt lfd. Nummer an
                        $str_csv = $str_csv. $value_quotes. $irow. $value_quotes;
                    }
                }
    
                $content  = '';
                $usf_type = '';
    
                // Feldtyp bei Spezialfeldern setzen
                if($column->getValue('lsc_special_field') == 'mem_begin' 
                || $column->getValue('lsc_special_field') == 'mem_end')
                {
                    $usf_type = 'DATE';
                }
                elseif($column->getValue('lsc_special_field') == 'usr_login_name')
                {
                    $usf_type = 'TEXT';
                }
                elseif($usf_id > 0)
                {
                    $usf_type = $g_current_user->getPropertyById($usf_id, 'usf_type');
                }
                            
                // Ausgabe je nach Feldtyp aufbereiten
                if($usf_id == $g_current_user->getProperty('Geschlecht', 'usf_id'))
                {
                    // Geschlecht anzeigen
                    if($row[$sql_column_number] == 1)
                    {
                        if($req_mode == 'csv' || $req_mode == 'print')
                        {
                            $content = 'männlich';
                        }
                        else
                        {
                            $content = '<img class="iconInformation" src="'. THEME_PATH. '/icons/male.png"
                                        title="männlich" alt="männlich" />';
                        }
                    }
                    elseif($row[$sql_column_number] == 2)
                    {
                        if($req_mode == 'csv' || $req_mode == 'print')
                        {
                            $content = 'weiblich';
                        }
                        else
                        {
                            $content = '<img class="iconInformation" src="'. THEME_PATH. '/icons/female.png"
                                        alt="weiblich" alt="weiblich" />';
                        }
                    }
                    else
                    {
                        if($req_mode != 'csv')
                        {
                            $content = '&nbsp;';
                        }
                    }
                }
                elseif($column->getValue('lsc_special_field') == 'usr_photo')
                {
                    // Benutzerfoto anzeigen
                    if($req_mode == 'html' || $req_mode == 'print')
                    {
	                    $_SESSION['profilphoto'][$row['usr_id']] = 0;
                    	if($g_preferences['profile_photo_storage'] == 0  && $row[$sql_column_number] != '')
						{
							$_SESSION['profilphoto'][$row['usr_id']]=$row[$sql_column_number];
						}
                        $content = '<img src="photo_show.php?usr_id='.$row['usr_id'].'" style="vertical-align: middle;" alt="Benutzerfoto" />';
                    }
                    if ($req_mode == 'csv' && $row[$sql_column_number] != NULL)
                    {
                        $content = 'Profilfoto Online';
                    }
                }
                else
                {
                    switch($usf_type)
                    {
                        case 'CHECKBOX':
                            // Checkboxen werden durch ein Bildchen dargestellt
                            if($row[$sql_column_number] == 1)
                            {
                                if($req_mode == 'csv')
                                {
                                    $content = 'ja';
                                }
                                else
                                {
                                    echo '<img src="'. THEME_PATH. '/icons/checkbox_checked.gif" style="vertical-align: middle;" alt="on" />';
                                }
                            }
                            else
                            {
                                if($req_mode == 'csv')
                                {
                                    $content = 'nein';
                                }
                                else
                                {
                                    echo '<img src="'. THEME_PATH. '/icons/checkbox.gif" style="vertical-align: middle;" alt="off" />';
                                }
                            }
                            break;
    
                        case 'DATE':
                            // Datum muss noch formatiert werden
                            $content = mysqldate('d.m.y', $row[$sql_column_number]);
                            break;
    
                        case 'EMAIL':
                            // E-Mail als Link darstellen
                            if(strlen($row[$sql_column_number]) > 0)
                            {
                                if($req_mode == 'html')
                                {
                                    if($g_preferences['enable_mail_module'] == 1 
                                    && $g_current_user->getPropertyById($usf_id, 'usf_name') == 'E-Mail')
                                    {
                                        $content = '<a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?usr_id='. $row['usr_id']. '">'. $row[$sql_column_number]. '</a>';
                                    }
                                    else
                                    {
                                        $content = '<a href="mailto:'. $row[$sql_column_number]. '">'. $row[$sql_column_number]. '</a>';
                                    }
                                }
                                else
                                {
                                    $content = $row[$sql_column_number];
                                }
                            }
                            break;
    
                        case 'URL':
                            // Homepage als Link darstellen
                            if(strlen($row[$sql_column_number]) > 0)
                            {
                                if($req_mode == 'html')
                                {
                                    $content = '<a href="'. $row[$sql_column_number]. '" target="_blank">'. substr($row[$sql_column_number], 7). '</a>';
                                }
                                else
                                {
                                    $content = substr($row[$sql_column_number], 7);
                                }
                            }
                            break;
    
                        default:
                            $content = $row[$sql_column_number];
                            break;                            
                    }
                }

                if($req_mode == 'csv')
                {
                    $str_csv = $str_csv. $separator. $value_quotes. $content. $value_quotes;
                }
    
                else
                {
                    echo $content. '</td>';
                }
            }
        }

        if($req_mode == 'csv')
        {
            $str_csv = $str_csv. "\n";
        }
        else
        {
            echo '</tr>';
        }

        $irow++;
    }
}  // End-While (jeder gefundene User)

if($req_mode == 'csv')
{
    // nun die erstellte CSV-Datei an den User schicken
    $filename = $g_organization. '-'. str_replace(' ', '_', str_replace('.', '', $role->getValue('rol_name'))). '.csv';
    header('Content-Type: text/comma-separated-values; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    //echo utf8_decode($str_csv);
    echo $str_csv;
}
else
{
    echo '</tbody></table>';

    if($req_mode != 'print')
    {
        // Navigation mit Vor- und Zurueck-Buttons
        $base_url = $g_root_path. '/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&mode='.$req_mode.'&rol_id='.$req_rol_id;
        echo generatePagination($base_url, $num_members, $members_per_page, $req_start, TRUE);
    }

    //INFOBOX zur Gruppe
    //nur anzeigen wenn zusatzfelder gefüllt sind
    if(strlen($role->getValue('rol_start_date')) > 0
    || $role->getValue('rol_weekday') > 0
    || strlen($role->getValue('rol_start_time')) > 0
    || strlen($role->getValue('rol_location')) > 0
    || strlen($role->getValue('rol_cost')) > 0
    || strlen($role->getValue('rol_max_members')) > 0)
    {
        echo '
        <div class="groupBox" id="infoboxListsBox">
            <div class="groupBoxHeadline">Infobox: '. $role->getValue('rol_name'). '</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">
                    <li>';
                        //Kategorie
                        echo '
                        <dl>
                            <dt>Kategorie:</dt>
                            <dd>'.$role->getValue('cat_name').'</dd>
                        </dl>
                    </li>';

                        //Beschreibung
                        if(strlen($role->getValue('rol_description')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Beschreibung:</dt>
                                    <dd>'.$role->getValue('rol_description').'</dd>
                                </dl>
                            </li>';
                        }

                        //Zeitraum
                        if(strlen($role->getValue('rol_start_date')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Zeitraum:</dt>
                                    <dd>'. mysqldate('d.m.y', $role->getValue('rol_start_date')). ' bis '. mysqldate('d.m.y', $role->getValue('rol_end_date')). '</dd>
                                </dl>
                            </li>';
                        }

                        //Termin
                        if($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Termin: </dt>
                                    <dd>'; 
                                        if($role->getValue('rol_weekday') > 0)
                                        {
                                            echo $arrDay[$role->getValue('rol_weekday')-1];
                                        }
                                        if(strlen($role->getValue('rol_start_time')) > 0)
                                        {
                                            echo ' von '. mysqltime('h:i', $role->getValue('rol_start_time')). ' bis '. mysqltime('h:i', $role->getValue('rol_end_time'));
                                        }

                                    echo'</dd>
                                </dl>
                            </li>';
                        }

                        //Treffpunkt
                        if(strlen($role->getValue('rol_location')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Treffpunkt:</dt>
                                    <dd>'.$role->getValue('rol_location').'</dd>
                                </dl>
                            </li>';
                        }

                        //Beitrag
                        if(strlen($role->getValue('rol_cost')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Beitrag:</dt>
                                    <dd>'. $role->getValue('rol_cost'). ' &euro;</dd>
                                </dl>
                            </li>';
                        }
						
						//Beitragszeitraum
                        if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Beitragszeitraum:</dt>
                                    <dd>'.$role->getRolCostPeriodDesc($role->getValue('rol_cost_period')).'</dd>
                                </dl>
                            </li>';
                        }

                        //maximale Teilnehmerzahl
                        if(strlen($role->getValue('rol_max_members')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>Max. Teilnehmer:</dt>
                                    <dd>'. $role->getValue('rol_max_members'). '</dd>
                                </dl>
                            </li>';
                        }
                echo'</ul>
            </div>
        </div>';
    } // Ende Infobox
    
    if($req_mode == 'print')
    {
        echo '</body></html>';
    }
    else
    {    
        echo '
        <ul class="iconTextLinkList">
            <li>
                <span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                    src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
                    <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
                </span>
            </li>
        </ul>';
    
        require(THEME_SERVER_PATH. '/overall_footer.php');
    }
}

?>