<?php
/******************************************************************************
 * Listen anzeigen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   : Ausgabeart   (html, print, csv-ms, csv-oo)
 * lst_id : ID der Listenkonfiguration, die angezeigt werden soll
 *          Wird keine ID uebergeben, wird die Default-Konfiguration angezeigt
 * rol_id : Rolle, fuer die die Funktion dargestellt werden soll
 * start  : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen 
 * show_members : 0 - (Default) aktive Mitglieder der Rolle anzeigen
 *                1 - Ehemalige Mitglieder der Rolle anzeigen
 *                2 - Aktive und ehemalige Mitglieder der Rolle anzeigen
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/list_configuration.php');
require_once('../../system/classes/table_roles.php');

// lokale Variablen der Uebergabevariablen initialisieren
$arr_mode   = array('csv-ms', 'csv-oo', 'html', 'print');
$req_rol_id = 0;
$req_lst_id = 0;
$req_start  = 0;
$show_members = 0;
$charset    = '';

// Uebergabevariablen pruefen

$req_mode   = strStripTags($_GET['mode']);

if(in_array($req_mode, $arr_mode) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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

	if(is_numeric($req_lst_id) == false || $req_lst_id == 0)
	{
	   $g_message->show($g_l10n->get('LST_DEFAULT_LIST_NOT_SET_UP'));
	}
}

if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_rol_id = $_GET['rol_id'];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET['start']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['show_members']) && is_numeric($_GET['show_members']))
{
    $show_members = $_GET['show_members'];
}

// Inhalt der Variablen explizit zuruecksetzen (einige Server behalten ansonsten alte Befüllungen vor)
unset($role_ids);

if($req_rol_id > 0)
{
    $role_ids[] = $req_rol_id;
}
else
{
    $role_ids = $_SESSION['role_ids'];
    $req_rol_id = $role_ids[0];
}

// Rollenobjekt erzeugen
$role = new TableRoles($g_db, $req_rol_id);

//Testen ob Recht zur Listeneinsicht besteht
if($role->viewRole() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

if($req_mode == 'csv-ms')
{
    $separator    = ';'; // Microsoft Excel 2007 und neuer braucht ein Semicolon
    $value_quotes = '"';
    $req_mode     = 'csv';
	$charset      = 'iso-8859-1';
}
else if($req_mode == 'csv-oo')
{
    $separator    = ',';   // fuer CSV-Dateien
    $value_quotes = '"';   // Werte muessen mit Anfuehrungszeichen eingeschlossen sein
    $req_mode     = 'csv';
	$charset      = 'utf-8';
}
else
{
    $separator    = ',';    // fuer CSV-Dateien
    $value_quotes = '';
}

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('usr_login_name' => $g_l10n->get('SYS_USERNAME'),
                      'usr_photo'      => $g_l10n->get('PHO_PHOTO'),
                      'mem_begin'      => $g_l10n->get('SYS_START'),
                      'mem_end'        => $g_l10n->get('SYS_END'),
                      'mem_leader'     => $g_l10n->get('SYS_LEADER')
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

// Listenkonfigurationsobjekt erzeugen und entsprechendes SQL-Statement erstellen
$list = new ListConfiguration($g_db, $req_lst_id);
$main_sql = $list->getSQL($role_ids, $show_members);
//echo $main_sql; exit();

// SQL-Statement der Liste ausfuehren und pruefen ob Daten vorhanden sind
$result_list = $g_db->query($main_sql);

$num_members = $g_db->num_rows($result_list);

if($num_members == 0)
{
    // Es sind keine Daten vorhanden !
    $g_message->show($g_l10n->get('LST_NO_USER_FOUND'));
}

if($num_members < $req_start)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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
            <!-- (c) 2004 - 2011 The Admidio Team - http://www.admidio.org -->
            
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        
            <title>'. $g_current_organization->getValue('org_longname'). ' - Liste - '. $role->getValue('rol_name'). '</title>
            
            <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/print.css" />
            <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>

            <style type="text/css">
                @page { size:landscape; }
            </style>
        </head>
        <body class="bodyPrint">';
    }
    else
    {
        $g_layout['title']    = $g_l10n->get('LST_LIST').' - '. $role->getValue('rol_name');
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
    	$member_status = $g_l10n->get('LST_ACTIVE_MEMBERS');
    }
    elseif($show_members == 1)
    {
    	$member_status = $g_l10n->get('LST_FORMER_MEMBERS');
    }
    elseif($show_members == 2)
    {
    	$member_status = $g_l10n->get('LST_ACTIVE_FORMER_MEMBERS');
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
                    // wenn nicht aus Listenuebersicht aufgerufen, dann wird hier die Listenuebersicht ohne Parameter aufgerufen
                    if(strpos($_SESSION['navigation']->getPreviousUrl(), 'lists.php') === false)
                    {
                        $url = $g_root_path.'/adm_program/modules/lists/lists.php';
                    }
                    else
                    {
                        $url = $g_root_path.'/adm_program/system/back.php';
                    }
                    echo '
                    <a href="'.$url.'"><img
                    src="'. THEME_PATH. '/icons/application_view_list.png" alt="'.$g_l10n->get('LST_LIST_VIEW').'" title="'.$g_l10n->get('LST_LIST_VIEW').'" /></a>
                    <a href="'.$url.'">'.$g_l10n->get('LST_LIST_VIEW').'</a>';
                }
                else
                {
                    echo '
                    <a href="'.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $req_lst_id. '&rol_id='. $req_rol_id. '&show_members='.$show_members.'"><img
                    src="'. THEME_PATH. '/icons/application_form.png" alt="'.$g_l10n->get('LST_KONFIGURATION_OWN_LIST').'" title="'.$g_l10n->get('LST_KONFIGURATION_OWN_LIST').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $req_lst_id. '&rol_id='. $req_rol_id. '&show_members='.$show_members.'">'.$g_l10n->get('LST_KONFIGURATION_OWN_LIST').'</a>';
                }
            echo '</span>
            </li>';

            // Aufruf des Mailmoduls mit dieser Rolle
            if($g_current_user->mailRole($role->getValue("rol_id")) && $g_preferences['enable_mail_module'] == 1)
            {
                echo '<li>
                    <span class="iconTextLink">
                        <a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$req_rol_id.'"><img
                        src="'. THEME_PATH. '/icons/email.png" alt="'.$g_l10n->get('LST_EMAIL_TO_MEMBERS').'"  title="'.$g_l10n->get('LST_EMAIL_TO_MEMBERS').'" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/mail/mail.php?rol_id='.$req_rol_id.'">'.$g_l10n->get('LST_EMAIL_TO_MEMBERS').'</a>
                    </span>
                </li>';
            }

            // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
            if((  $g_current_user->assignRoles() 
               || isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')))
            && $role->getValue('rol_valid') == 1)
            {
                // der Webmasterrolle darf nur von Webmastern neue User zugeordnet werden
                if($role->getValue('rol_name')  != $g_l10n->get('SYS_WEBMASTER')
                || ($role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER') && $g_current_user->isWebmaster()))
                {
                    echo '
                    <li>
                        <span class="iconTextLink">
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='. $role->getValue('rol_id'). '"><img 
                                src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$g_l10n->get('SYS_ASSIGN_MEMBERS').'n" /></a>
                            <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='. $role->getValue('rol_id'). '">'.$g_l10n->get('SYS_ASSIGN_MEMBERS').'</a>
                        </span>
                    </li>';
                }
            }

            echo '<li>
                <span class="iconTextLink">
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&amp;mode=print&amp;rol_id='.$req_rol_id.'\', \'_blank\')"><img
                    src="'. THEME_PATH. '/icons/print.png" alt="'.$g_l10n->get('LST_PRINT_PREVIEW').'" title="'.$g_l10n->get('LST_PRINT_PREVIEW').'" /></a>
                    <a href="#" onclick="window.open(\''.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&amp;mode=print&amp;rol_id='.$req_rol_id.'\', \'_blank\')">'.$g_l10n->get('LST_PRINT_PREVIEW').'</a>
                </span>
            </li>
            <li>
                <span class="iconTextLink">
                    <img src="'. THEME_PATH. '/icons/database_out.png" alt="'.$g_l10n->get('LST_EXPORT_TO').'" />
                    <select size="1" name="export_mode" onchange="exportList(this)">
                        <option value="" selected="selected">'.$g_l10n->get('LST_EXPORT_TO').' ...</option>
                        <option value="csv-ms">'.$g_l10n->get('LST_MICROSOFT_EXCEL').' ('.$g_l10n->get('SYS_ISO_8859_1').')</option>
                        <option value="csv-oo">'.$g_l10n->get('LST_CSV_FILE').' ('.$g_l10n->get('SYS_UTF8').')</option>
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
        || $g_current_user->getPropertyById($usf_id, 'usf_name_intern') == 'GENDER')
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
                $str_csv = $str_csv. $value_quotes. $g_l10n->get('SYS_ABR_NO'). $value_quotes;
            }
            $str_csv = $str_csv. $separator. $value_quotes. $col_name. $value_quotes;
        }
        else
        {                
            if($column_number == 1)
            {
                // die Laufende Nummer noch davorsetzen
                echo '<th style="text-align: '.$align.';">'.$g_l10n->get('SYS_ABR_NO').'</th>';
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
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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
                    $title = $g_l10n->get('SYS_LEADER');
                }
                else
                {
                    $title = $g_l10n->get('SYS_PARTICIPANTS');
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
                        || $g_current_user->getPropertyById($usf_id, 'usf_name_intern') == 'GENDER')
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
                if($usf_id == $g_current_user->getProperty('GENDER', 'usf_id'))
                {
                    // Geschlecht anzeigen
                    if($row[$sql_column_number] == 1)
                    {
                        if($req_mode == 'csv' || $req_mode == 'print')
                        {
                            $content = $g_l10n->get('SYS_MALE');
                        }
                        else
                        {
                            $content = '<img class="iconInformation" src="'. THEME_PATH. '/icons/male.png"
                                        title="'.$g_l10n->get('SYS_MALE').'" alt="'.$g_l10n->get('SYS_MALE').'" />';
                        }
                    }
                    elseif($row[$sql_column_number] == 2)
                    {
                        if($req_mode == 'csv' || $req_mode == 'print')
                        {
                            $content = $g_l10n->get('SYS_FEMALE');
                        }
                        else
                        {
                            $content = '<img class="iconInformation" src="'. THEME_PATH. '/icons/female.png"
                                        alt="'.$g_l10n->get('SYS_FEMALE').'" alt="'.$g_l10n->get('SYS_FEMALE').'" />';
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
                elseif($usf_id == $g_current_user->getProperty('COUNTRY', 'usf_id'))
				{
					$content = $g_l10n->getCountryByCode($row[$sql_column_number]);
				}
                elseif($column->getValue('lsc_special_field') == 'usr_photo')
                {
                    // Benutzerfoto anzeigen
                    if($req_mode == 'html' || $req_mode == 'print')
                    {
                        $imgSource = 'photo_show.php?usr_id='.$row['usr_id'];
                        if($g_preferences['profile_photo_storage'] == 0)
                        {
                            if(strlen($row[$sql_column_number]) == 0)
                            {
                                $imgSource = THEME_PATH. '/images/no_profile_pic.png';
                            }
                        }
                        else
                        {
                            // Profilbild aus dem Filesystem einlesen bzw. Default-Bild anzeigen
                            if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$_GET['usr_id'].'.jpg'))
                            {
                                $imgSource = $g_root_path.'/adm_my_files/user_profile_photos/'.$_GET['usr_id'].'.jpg';
                            }
                            else
                            {
                                $imgSource = THEME_PATH. '/images/no_profile_pic.png';
                            }
                        }
                        $content = '<img src="'.$imgSource.'" style="vertical-align: middle;" alt="'.$g_l10n->get('LST_USER_PHOTO').'" />';
                    }
                    if ($req_mode == 'csv' && $row[$sql_column_number] != NULL)
                    {
                        $content = $g_l10n->get('LST_USER_PHOTO');
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
                                    $content = $g_l10n->get('SYS_YES');
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
                                    $content = $g_l10n->get('SYS_NO');
                                }
                                else
                                {
                                    echo '<img src="'. THEME_PATH. '/icons/checkbox.gif" style="vertical-align: middle;" alt="off" />';
                                }
                            }
                            break;
    
                        case 'DATE':
							if(strlen($row[$sql_column_number]) > 0)
							{
								// Datum muss noch formatiert werden
								$date = new DateTimeExtended($row[$sql_column_number], 'Y-m-d', 'date');
								$content = $date->format($g_preferences['system_date']);
							}
                            break;
    
                        case 'EMAIL':
                            // E-Mail als Link darstellen
                            if(strlen($row[$sql_column_number]) > 0)
                            {
                                if($req_mode == 'html')
                                {
                                    if($g_preferences['enable_mail_module'] == 1 
                                    && $g_current_user->getPropertyById($usf_id, 'usf_name_intern') == 'EMAIL')
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
                                    $content = '<a href="'.$row[$sql_column_number].'" target="_blank">'.$row[$sql_column_number].'</a>';
                                }
                                else
                                {
                                    $content = $row[$sql_column_number];
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
    header('Content-Type: text/comma-separated-values; charset='.$charset);
    header('Content-Disposition: attachment; filename="'.$filename.'"');
	if($charset == 'iso-8859-1')
	{
		echo utf8_decode($str_csv);
	}
	else
	{
		echo $str_csv;
	}
}
else
{
    echo '</tbody></table>';

    if($req_mode != 'print')
    {
        // Navigation mit Vor- und Zurueck-Buttons
        $base_url = $g_root_path. '/adm_program/modules/lists/lists_show.php?lst_id='.$req_lst_id.'&mode='.$req_mode.'&rol_id='.$req_rol_id.'&show_members='.$show_members;
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
                            <dt>'.$g_l10n->get('SYS_CATEGORY').':</dt>
                            <dd>'.$role->getValue('cat_name').'</dd>
                        </dl>
                    </li>';

                        //Beschreibung
                        if(strlen($role->getValue('rol_description')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>'.$g_l10n->get('SYS_DESCRIPTION').':</dt>
                                    <dd>'.$role->getValue('rol_description').'</dd>
                                </dl>
                            </li>';
                        }

                        //Zeitraum
                        if(strlen($role->getValue('rol_start_date')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>'.$g_l10n->get('LST_PERIOD').':</dt>
                                    <dd>'.$g_l10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $g_preferences['system_date']), $role->getValue('rol_end_date', $g_preferences['system_date'])).'</dd>
                                </dl>
                            </li>';
                        }

                        //Termin
                        if($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
                        {
                            echo '<li>
                                <dl>
                                    <dt>'.$g_l10n->get('DAT_DATE').': </dt>
                                    <dd>'; 
                                        if($role->getValue('rol_weekday') > 0)
                                        {
                                            echo $role->getWeekdayDesc($role->getValue('rol_weekday')).' ';
                                        }
                                        if(strlen($role->getValue('rol_start_time')) > 0)
                                        {
                                            echo $g_l10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $g_preferences['system_time']), $role->getValue('rol_end_time', $g_preferences['system_time']));
                                        }

                                    echo '</dd>
                                </dl>
                            </li>';
                        }

                        //Treffpunkt
                        if(strlen($role->getValue('rol_location')) > 0)
                        {
                            echo '<li>
                                <dl>
                                    <dt>'.$g_l10n->get('SYS_LOCATION').':</dt>
                                    <dd>'.$role->getValue('rol_location').'</dd>
                                </dl>
                            </li>';
                        }

                        //Beitrag
                        if(strlen($role->getValue('rol_cost')) > 0)
                        {
                            echo '<li>
                                <dl>
                                    <dt>'.$g_l10n->get('SYS_CONTRIBUTION').':</dt>
                                    <dd>'. $role->getValue('rol_cost'). ' '.$g_preferences['system_currency'].'</dd>
                                </dl>
                            </li>';
                        }

						//Beitragszeitraum
                        if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                        {
                            echo '<li>
                                <dl>
                                    <dt>'.$g_l10n->get('SYS_CONTRIBUTION_PERIOD').':</dt>
                                    <dd>'.$role->getCostPeriodDesc($role->getValue('rol_cost_period')).'</dd>
                                </dl>
                            </li>';
                        }

                        //maximale Teilnehmerzahl
                        if(strlen($role->getValue('rol_max_members')) > 0)
                        {
                            echo'<li>
                                <dl>
                                    <dt>'.$g_l10n->get('SYS_MAX_PARTICIPANTS').':</dt>
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
                    src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
                </span>
            </li>
        </ul>';
    
        require(THEME_SERVER_PATH. '/overall_footer.php');
    }
}

?>