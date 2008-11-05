<?php
/******************************************************************************
 * Eigene Listen erstellen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lst_id : Liste deren Konfiguration direkt angezeigt werden soll
 * rol_id : das Feld Rolle kann mit der entsprechenden Rolle vorbelegt werden
 * active_role  : 1 - (Default) aktive Rollen auflisten
 *                0 - Ehemalige Rollen auflisten
 * show_members : 0 - (Default) aktive Mitglieder der Rolle anzeigen
 *                1 - Ehemalige Mitglieder der Rolle anzeigen
 *                2 - Aktive und ehemalige Mitglieder der Rolle anzeigen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/list_configuration.php");

// Uebergabevariablen pruefen und ggf. vorbelegen
$req_lst_id = 0;
$req_rol_id = 0;
$active_role  = 1;
$show_members = 0;

if(isset($_GET['lst_id']))
{
    if(is_numeric($_GET['lst_id']) == false)
    {
        $g_message->show("invalid");
    }   
    $req_lst_id = $_GET["lst_id"];
} 

if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show("invalid");
    }   
    $req_rol_id = $_GET["rol_id"];
}  

if(isset($_GET['active_role']) && is_numeric($_GET['show_members']))
{
    $active_role = $_GET['active_role'];
}   

if(isset($_GET['show_members']) && is_numeric($_GET['show_members']))
{
    $show_members = $_GET['show_members'];
}  

if($req_rol_id == 0)
{
    // Navigation faengt hier im Modul an
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

$b_history = false;     // History-Funktion bereits aktiviert ja/nein
$default_column_rows = 6;    // Anzahl der Spalten, die beim Aufruf angezeigt werden

// Listenobjekt anlegen
$list = new ListConfiguration($g_db, $req_lst_id);

if(isset($_SESSION['mylist_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['mylist_request']);
    unset($_SESSION['mylist_request']);
    $req_rol_id = $form_values['rol_id'];
    
    // falls vorher schon Zeilen fuer Spalten manuell hinzugefuegt wurden, 
    // muessen diese nun direkt angelegt werden
    for($i = $default_column_rows+1; $i > 0; $i++)
    {
        if(isset($form_values["column$i"]))
        {
            $default_column_rows++;          
        }   
        else
        {
            $i = -1;
        }
    }
    
    $b_history = true;
}
elseif($req_lst_id > 0)
{
    $default_column_rows = $list->countColumns();
}

// Html-Kopf ausgeben
$g_layout['title']  = "Eigene Liste - Einstellungen";
$g_layout['header'] = $g_js_vars. '
    <script type="text/javascript">
        var fieldNumberIntern      = 0;
        var arr_user_fields    = createUserFieldsArray();
        var arr_default_fields = createColumnsArray();

        // Funktion fuegt eine neue Zeile zum Zuordnen von Spalten fuer die Liste hinzu
        function addColumn() 
        {
            var category = "";
            var fieldNumberShow  = fieldNumberIntern + 1;
            var table = document.getElementById("mylist_fields_tbody");
            var newTableRow = table.insertRow(fieldNumberIntern);
            var newCellCount = newTableRow.insertCell(-1);
            newCellCount.innerHTML = (fieldNumberShow) + ". Spalte :";
            
            // neue Spalte zur Auswahl des Profilfeldes
            var newCellField = newTableRow.insertCell(-1);
            htmlCboFields = "<select size=\"1\" id=\"column" + fieldNumberShow + "\" name=\"column" + fieldNumberShow + "\">" +
                    "<option value=\"\"></option>";
            for(var counter = 1; counter < arr_user_fields.length; counter++)
            {
                if(category != arr_user_fields[counter]["cat_name"])
                {
                    if(category.length > 0)
                    {
                        htmlCboFields += "</optgroup>";
                    }
                    htmlCboFields += "<optgroup label=\"" + arr_user_fields[counter]["cat_name"] + "\">";
                    category = arr_user_fields[counter]["cat_name"];
                }
                var selected = \'\';
                if((fieldNumberIntern == 0 && arr_user_fields[counter]["usf_name"] == \'Nachname\')
                || (fieldNumberIntern == 1 && arr_user_fields[counter]["usf_name"] == \'Vorname\'))
                {
                    selected = \' selected="selected" \';
                }
                if(arr_default_fields[fieldNumberIntern])
                {
                    if(arr_user_fields[counter]["usf_id"] == arr_default_fields[fieldNumberIntern][\'usf_id\'])
                    {
                        selected = \' selected="selected" \';
                    }
                }
                htmlCboFields += "<option value=\"" + arr_user_fields[counter]["usf_id"] + "\" " + selected + ">" + arr_user_fields[counter]["usf_name"] + "</option>"; 
            }
            htmlCboFields += "</select>";
            newCellField.innerHTML = htmlCboFields;
            
            // neue Spalte zur Einstellung der Sortierung
            var selectAsc  = \'\';
            var selectDesc = \'\';
            
            if(arr_default_fields[fieldNumberIntern])
            {
                if(arr_default_fields[fieldNumberIntern][\'sort\'] == "ASC")
                {
                    selectAsc = \' selected="selected" \';
                }
                if(arr_default_fields[fieldNumberIntern][\'sort\'] == "DESC")
                {
                    selectDesc = \' selected="selected" \';
                }
            }
            else if(fieldNumberIntern == 0)
            {
                selectAsc = \' selected="selected" \';
            }
            
            var newCellOrder = newTableRow.insertCell(-1);
            newCellOrder.innerHTML = "<select size=\"1\" id=\"sort" + fieldNumberShow + "\" name=\"sort" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>" +
                    "<option value=\"ASC\" " + selectAsc + ">A bis Z</option>" +
                    "<option value=\"DESC\" " + selectDesc + ">Z bis A</option>" +
                "</select>";
            
            // neue Spalte fuer Bedingungen
            condition = \'\';
            if(arr_default_fields[fieldNumberIntern])
            {
                if(arr_default_fields[fieldNumberIntern][\'condition\'])
                {
                    condition = arr_default_fields[fieldNumberIntern][\'condition\'];
                    condition = condition.replace(/{/g, "<");
                    condition = condition.replace(/}/g, ">");
                }
            }            
            var newCellConditions = newTableRow.insertCell(-1);
            newCellConditions.innerHTML = "<input type=\"text\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\" size=\"15\" maxlength=\"30\" value=\"" + condition + "\" />";

            fieldNumberIntern++;
        }
        
        function createUserFieldsArray()
        { 
            var user_fields = new Array(); ';
        
            // Mehrdimensionales Array fuer alle anzuzeigenden Spalten mit den noetigen Daten erstellen
            $i = 1;
            $old_cat_name = "";
            $old_cat_id   = 0;

            foreach($g_current_user->db_user_fields as $key => $value)
            {    
                // bei den Stammdaten noch Foto und Loginname anhaengen
                if($old_cat_name == "Stammdaten"
                && $value['cat_name'] != "Stammdaten")
                {
                    $g_layout['header'] .= '
                    user_fields['. $i. '] = new Object();
                    user_fields['. $i. '][\'cat_id\'] = '. $old_cat_id. ';
                    user_fields['. $i. '][\'cat_name\'] = \''. $old_cat_name. '\';
                    user_fields['. $i. '][\'usf_id\'] = \'usr_login_name\';
                    user_fields['. $i. '][\'usf_name\'] = \'Benutzername\';';
                    $i++;
                    
                    $g_layout['header'] .= '
                    user_fields['. $i. '] = new Object();
                    user_fields['. $i. '][\'cat_id\'] = '. $old_cat_id. ';
                    user_fields['. $i. '][\'cat_name\'] = \''. $old_cat_name. '\';
                    user_fields['. $i. '][\'usf_id\'] = \'usr_photo\';
                    user_fields['. $i. '][\'usf_name\'] = \'Foto\';';
                    $i++;
                }
                
                if($value['usf_hidden'] == 0 || $g_current_user->editUsers())
                {
                    $g_layout['header'] .= '
                    user_fields['. $i. '] = new Object();
                    user_fields['. $i. '][\'cat_id\'] = '. $value['cat_id']. ';
                    user_fields['. $i. '][\'cat_name\'] = \''. $value['cat_name']. '\';
                    user_fields['. $i. '][\'usf_id\'] = '. $value['usf_id']. ';
                    user_fields['. $i. '][\'usf_name\'] = \''. $value['usf_name']. '\';';
                
                    $old_cat_id   = $value['cat_id'];
                    $old_cat_name = $value['cat_name'];
                    $i++;
                }
            }       

            // Anfangs- und Enddatum der Rollenmitgliedschaft als Inhalte noch anhaengen
            $g_layout['header'] .= '
            user_fields['. $i. '] = new Object();
            user_fields['. $i. '][\'cat_id\'] = -1;
            user_fields['. $i. '][\'cat_name\'] = \'Rollendaten\';
            user_fields['. $i. '][\'usf_id\'] = \'mem_begin\';
            user_fields['. $i. '][\'usf_name\'] = \'Mitgliedsbeginn\';';
            
            $i++;
            $g_layout['header'] .= '
            user_fields['. $i. '] = new Object();
            user_fields['. $i. '][\'cat_id\'] = -1;
            user_fields['. $i. '][\'cat_name\'] = \'Rollendaten\';
            user_fields['. $i. '][\'usf_id\'] = \'mem_end\';
            user_fields['. $i. '][\'usf_name\'] = \'Mitgliedsende\';
            
            return user_fields;
        }
        
        function createColumnsArray()
        {
            var default_fields = new Array(); ';
            
            if(isset($form_values))
            {
                // Daten aller Zeilen werden aus den POST-Daten in ein JS-Array geschrieben
                $act_field_count = 0;
                while(isset($form_values['column'. $act_field_count]))
                {
                    $g_layout['header'] .= '
                    default_fields['. $act_field_count. '] = new Object();
                    default_fields['. $act_field_count. '][\'usf_id\']    = \''. $form_values['column'. $act_field_count]. '\';
                    default_fields['. $act_field_count. '][\'sort\']      = \''. $form_values['sort'. $act_field_count]. '\';
                    default_fields['. $act_field_count. '][\'condition\'] = \''. $form_values['condition'. $act_field_count]. '\';';
                    
                    $act_field_count++;
                }
                if($act_field_count > $default_column_rows)
                {
                    $default_column_rows = $act_field_count;
                }
            }
            else
            {
                for($number = 0; $number < $list->countColumns(); $number++)
                {
                    $column = $list->getColumnObject($number + 1);
                    if($column->getValue("lsc_usf_id") > 0)
                    {
                        $column_content = $column->getValue("lsc_usf_id");
                    }
                    else
                    {
                        $column_content = $column->getValue("lsc_special_field");
                    }
                    $g_layout['header'] .= '
                    default_fields['. $number. '] = new Object();
                    default_fields['. $number. '][\'usf_id\']    = \''. $column_content. '\';
                    default_fields['. $number. '][\'sort\']      = \''. $column->getValue("lsc_sort"). '\';
                    default_fields['. $number. '][\'condition\'] = \''. $column->getValue("lsc_filter"). '\';';
                }
            }

            $g_layout['header'] .= '
            return default_fields;
        }

        function loadList()
        {
            var lst_id = document.getElementById("lists_config").value;
            self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?lst_id=" + lst_id;
        }

        function send(mode)
        {
            for(var i = 0; i < fieldNumberIntern; i++)
            {
                if(document.getElementById("condition" + i))
                {
                    var condition = document.getElementById("condition" + i);
                    condition.value = condition.value.replace(/</g, "{");
                    condition.value = condition.value.replace(/>/g, "}");
                }
            }

            switch (mode)
            {
                case "show":
                    document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?mode=2";
                    document.getElementById("form_mylist").submit();
                    break;

                case "save":
                    var listName = "";
                    listName = prompt("Unter welcher Bezeichnung soll diese Konfiguration gespeichert werden ?");
                    if(listName != null)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$req_lst_id.'&mode=1&name=" + listName;
                        document.getElementById("form_mylist").submit();
                    }
                    break;

                case "delete":
                    var msg_result = confirm("Willst du die aktuelle Listenkonfiguration wirklich löschen ?");
                    if(msg_result)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$req_lst_id.'&mode=3";
                        document.getElementById("form_mylist").submit();
                    }
                    break;

                case "system":
                    var msg_result = confirm("Willst du die aktuelle Listenkonfiguration allen Benutzern zur Verfügung stellen ?");
                    if(msg_result)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$req_lst_id.'&mode=4";
                        document.getElementById("form_mylist").submit();
                    }
                    break;
            }
        }
    </script>';

require(THEME_SERVER_PATH. "/overall_header.php");

echo '
<form id="form_mylist" action="'. $g_root_path. '/adm_program/modules/lists/mylist_prepare.php" method="post">
<div class="formLayout" id="mylist_form">
    <div class="formHead">Eigene Liste</div>
    <div class="formBody">
        <p><b>1.</b> Möchtest du eine gespeicherte Konfiguration laden und verändern oder lieber eine neue Listenkonfiguration erstellen ?</p>
        <p><b>Listenkonfiguration :</b>&nbsp;&nbsp;
        <select size="1" id="lists_config" name="lists_config" onchange="loadList()">
            <option ';
                if($req_lst_id == 0)
                {
                    $selected = ' selected="selected" ';
                }
                else
                {
                    $selected = '';
                }
            echo $selected.' value="0">Neue Konfiguration erstellen</option>';

            // alle relevanten Konfigurationen fuer den User suchen
            $sql = "SELECT * FROM ". TBL_LISTS. "
                     WHERE lst_org_id = ". $g_current_organization->getValue("org_id") ."
                       AND (  lst_usr_id = ". $g_current_user->getValue("usr_id"). "
                           OR lst_global = 1)
                     ORDER BY lst_global ASC, lst_name ASC, lst_timestamp DESC ";
            $lst_result = $g_db->query($sql);
            
            if($g_db->num_rows() > 0)
            {
                $list_global_flag = "";
                $list_name_flag   = "";
                $optgroup_flag    = 0;
                $counter_unsaved_lists = 0;
                
                while($row = $g_db->fetch_array($lst_result))
                {
                    // maximal nur die letzten 5 Konfigurationen ohne Namen speichern
                    if(strlen($row['lst_name']) == 0)
                    {
                        $counter_unsaved_lists++;
                    }

                    if($counter_unsaved_lists > 5 && strlen($row['lst_name']) == 0)
                    {
                        // alle weiteren Konfigurationen ohne Namen loeschen
                        $del_list = new ListConfiguration($g_db, $row['lst_id']);
                        $del_list->delete();
                    }
                    else
                    {
                        // erst mal schauen, ob eine neue Gruppe von Konfigurationen angefangen hat
                        if($row['lst_global'] != $list_global_flag
                        || ($row['lst_name']  != $list_name_flag && strlen($list_name_flag) == 0))
                        {
                            if($optgroup_flag == 1)
                            {
                                echo '</optgroup>';
                            }
                            if($row['lst_global'] == 0 && strlen($row['lst_name']) == 0)
                            {
                                echo '<optgroup label="Deine letzten Konfigurationen">';
                            }
                            elseif($row['lst_global'] == 0 && strlen($row['lst_name']) > 0)
                            {
                                echo '<optgroup label="Gespeicherte Konfigurationen">';
                            }
                            else
                            {
                                echo '<optgroup label="Vorgegebene Konfigurationen">';
                            }
                            $list_global_flag = $row['lst_global'];
                            $list_name_flag   = $row['lst_name'];
                        }
                        
                        // auf die Konfiguration selektieren, die uebergeben wurde
                        if($req_lst_id == $row['lst_id'])
                        {
                            $selected = ' selected="selected" ';
                        }
                        else
                        {
                            $selected = '';
                        }

                        // Zeitstempel der Konfigurationen ohne Namen oder Namen anzeigen
                        if(strlen($row['lst_name']) == 0)
                        {
                            $description = mysqldatetime("d.m.y h:i", $row['lst_timestamp']);
                        }
                        else
                        {
                            $description = $row['lst_name'];
                        }
                        // Comboboxeintrag ausgeben
                        echo '<option '.$selected.' value="'.$row['lst_id'].'">'.$description.'</option>';
                    }
                }
                echo '</optgroup>';
            }           
        echo '</select>';
        
        // Listen speichern darf man speichern, wenn es Eigene sind, Neue oder als Webmaster auch Systemlisten
        if($g_current_user->isWebmaster()
        || $req_lst_id == 0
        || $g_current_user->getValue("usr_id") == $list->getValue("lst_usr_id"))
        {
            echo '
            <a class="iconLink" href="javascript:send(\'save\');"><img
                src="'. THEME_PATH. '/icons/disk.png" alt="Konfiguration speichern" title="Konfiguration speichern" /></a>';
        }

        if($g_current_user->isWebmaster() && $list->getValue("lst_global") == 1
        || ($g_current_user->getValue("usr_id") == $list->getValue("lst_usr_id") && strlen($list->getValue("lst_name")) > 0))
        {
            echo '
            <a class="iconLink" href="javascript:send(\'delete\');"><img
                src="'. THEME_PATH. '/icons/delete.png" alt="Konfiguration löschen" title="Konfiguration löschen" /></a>';
        }

        // eine gespeicherte Konfiguration kann vom Webmaster zur Systemkonfiguration gemacht werden
        if($g_current_user->isWebmaster() && $list->getValue("lst_global") == 0 && strlen($list->getValue("lst_name")) > 0)
        {
            echo '
            <a class="iconLink" href="javascript:send(\'system\');"><img
                src="'. THEME_PATH. '/icons/list_global.png" alt="Konfiguration allen Benutzern zur Verfügung stellen" title="Konfiguration allen Benutzern zur Verfügung stellen" /></a>';
        }
        echo '</p>
        
        <p><b>2.</b> Bestimme die Spalten, die in der Liste angezeigt werden sollen:</p>

        <table class="tableList" id="mylist_fields_table" style="width: 94%;" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 18%;">Nr.</th>
                    <th style="width: 37%;">Inhalt</th>
                    <th style="width: 18%;">Sortierung</th>
                    <th style="width: 27%;">Bedingung
                        <img class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title=""
                            onclick="window.open(\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=condition&amp;window=true\',\'Message\',\'width=650,height=400,left=310,top=200,scrollbars=no\')" 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=condition\',this);" onmouseout="ajax_hideTooltip()" />
                    </th>
                </tr>
            </thead>
            <tbody id="mylist_fields_tbody">
                <script type="text/javascript">          
                    for(var counter = 0; counter < '. $default_column_rows. '; counter++)
                    {
                        addColumn();
                    }
                </script>
                <tr id="table_row_button">
                    <td colspan="4">
                        <span class="iconTextLink">
                            <a href="javascript:addColumn()"><img
                            src="'. THEME_PATH. '/icons/add.png" alt="Weitere Spalte hinzufügen" /></a>
                            <a href="javascript:addColumn()">Weitere Spalte hinzufügen</a>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <br />
        
        <b>3.</b> Wähle eine Rolle aus von der du die Mitgliederliste erstellen willst:
        <p><b>Rolle :</b>&nbsp;&nbsp;';

        // Combobox mit allen Rollen ausgeben
        echo generateRoleSelectBox($req_rol_id);

        // Auswahlbox, ob aktive oder ehemalige Mitglieder angezeigt werden sollen
        $selected[0] = "";
        $selected[1] = "";
        $selected[2] = "";
        $selected[$show_members] = ' selected="selected" ';
        
        echo '&nbsp;&nbsp;&nbsp;
        <select size="1" id="show_members" name="show_members">
            <option '.$selected[0].' value="0">Aktive Mitglieder</option>
            <option '.$selected[1].' value="1">Ehemalige Mitglieder</option>
            <option '.$selected[2].' value="2">Aktive und Ehemalige</option>
        </select>
        
        <hr />

        <div class="formSubmit">
            <button name="btn_show" type="button" onclick="javascript:send(\'show\');" value="anzeigen"><img 
                src="'. THEME_PATH. '/icons/list.png" alt="Liste anzeigen" />&nbsp;Liste anzeigen</button>
        </div>
    </div>
</div>
</form>';

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($_SESSION['navigation']->count > 1)
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
}
    
require(THEME_SERVER_PATH. "/overall_footer.php");

?>