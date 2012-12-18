<?php
/******************************************************************************
 * Eigene Listen erstellen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
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

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/list_configuration.php');

// Initialize and check the parameters
$getListId      = admFuncVariableIsValid($_GET, 'lst_id', 'numeric', 0);
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getActiveRole  = admFuncVariableIsValid($_GET, 'active_role', 'boolean', 1);
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric', 0);

// falls ehemalige Rolle, dann auch nur ehemalige Mitglieder anzeigen
if($getActiveRole == 0)
{
    $getShowMembers = 1;
}

if($getRoleId == 0)
{
    // Navigation faengt hier im Modul an
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL);

$default_column_rows = 6;    // Anzahl der Spalten, die beim Aufruf angezeigt werden

// Listenobjekt anlegen
$list = new ListConfiguration($gDb, $getListId);

if(isset($_SESSION['mylist_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['mylist_request']);
    unset($_SESSION['mylist_request']);
    $getRoleId = $form_values['rol_id'];
    
    // falls vorher schon Zeilen fuer Spalten manuell hinzugefuegt wurden, 
    // muessen diese nun direkt angelegt werden
    for($i = $default_column_rows+1; $i > 0; $i++)
    {
        if(isset($form_values['column'.$i]))
        {
            $default_column_rows++;          
        }   
        else
        {
            $i = -1;
        }
    }
}
elseif($getListId > 0)
{
    $default_column_rows = $list->countColumns();
}

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('LST_MY_LIST').' - '.$gL10n->get('LST_CONFIGURATION');
$gLayout['header'] = '
    <script type="text/javascript">
        var listId             = '.$getListId.';
        var fieldNumberIntern  = 0;
        var arr_user_fields    = createProfileFieldsArray();
        var arr_default_fields = createColumnsArray();

        // Funktion fuegt eine neue Zeile zum Zuordnen von Spalten fuer die Liste hinzu
        function addColumn() 
        {
            // MySQL erlaubt nur 61 gejointe Tabellen
            if(fieldNumberIntern >= 57)
            {
                alert("'.$gL10n->get('LST_NO_MORE_COLUMN').'");
                return;
            }
            
            var category = "";
            var fieldNumberShow  = fieldNumberIntern + 1;
            var table = document.getElementById("mylist_fields_tbody");
            var newTableRow = table.insertRow(fieldNumberIntern);
            //$(newTableRow).css("display", "none"); // ausgebaut wg. Kompatibilitaetsproblemen im IE8
            var newCellCount = newTableRow.insertCell(-1);
            newCellCount.innerHTML = (fieldNumberShow) + ".&nbsp;'.$gL10n->get('LST_COLUMN').'&nbsp;:";
            
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

                var selected = "";
                // bei einer neuen Liste sind Vorname und Nachname in den ersten Spalten vorbelegt
                if((  (fieldNumberIntern == 0 && arr_user_fields[counter]["usf_name_intern"] == "LAST_NAME")
                   || (fieldNumberIntern == 1 && arr_user_fields[counter]["usf_name_intern"] == "FIRST_NAME"))
                && listId == 0)
                {
                    selected = " selected=\"selected\" ";
                }

                // bei gespeicherten Listen das entsprechende Profilfeld selektieren
                if(arr_default_fields[fieldNumberIntern])
                {
                    if(arr_user_fields[counter]["usf_id"] == arr_default_fields[fieldNumberIntern]["usf_id"])
                    {
                        selected = " selected=\"selected\" ";
                    }
                }
                htmlCboFields += "<option value=\"" + arr_user_fields[counter]["usf_id"] + "\" " + selected + ">" + arr_user_fields[counter]["usf_name"] + "</option>"; 
            }
            htmlCboFields += "</select>";
            newCellField.innerHTML = htmlCboFields;
            
            // neue Spalte zur Einstellung der Sortierung
            var selectAsc  = "";
            var selectDesc = "";
            
            if(arr_default_fields[fieldNumberIntern])
            {
                if(arr_default_fields[fieldNumberIntern]["sort"] == "ASC")
                {
                    selectAsc = " selected=\"selected\" ";
                }
                if(arr_default_fields[fieldNumberIntern]["sort"] == "DESC")
                {
                    selectDesc = " selected=\"selected\" ";
                }
            }
            else if(fieldNumberIntern == 0)
            {
                selectAsc = " selected=\"selected\" ";
            }
            
            var newCellOrder = newTableRow.insertCell(-1);
            newCellOrder.innerHTML = "<select size=\"1\" id=\"sort" + fieldNumberShow + "\" name=\"sort" + fieldNumberShow + "\">" +
                    "<option value=\"\">&nbsp;</option>" +
                    "<option value=\"ASC\" " + selectAsc + ">'.$gL10n->get('LST_A_TO_Z').'</option>" +
                    "<option value=\"DESC\" " + selectDesc + ">'.$gL10n->get('LST_Z_TO_A').'</option>" +
                "</select>";
            
            // neue Spalte fuer Bedingungen
            condition = "";
            if(arr_default_fields[fieldNumberIntern])
            {
                if(arr_default_fields[fieldNumberIntern]["condition"])
                {
                    condition = arr_default_fields[fieldNumberIntern]["condition"];
                    condition = condition.replace(/{/g, "<");
                    condition = condition.replace(/}/g, ">");
                }
            }            
            var newCellConditions = newTableRow.insertCell(-1);
            newCellConditions.innerHTML = "<input type=\"text\" id=\"condition" + fieldNumberShow + "\" name=\"condition" + fieldNumberShow + "\" maxlength=\"50\" value=\"" + condition + "\" />";

			$(newTableRow).fadeIn("slow");
            fieldNumberIntern++;
        }
        
        function createProfileFieldsArray()
        { 
            var user_fields = new Array(); ';
        
			// create a multidimensional array for all columns with the necessary data
            $i = 1;
            $oldCategoryNameIntern = '';
			$posEndOfMasterData = 0;

            foreach($gProfileFields->mProfileFields as $field)
            {    
				// at the end of category master data save positions for loginname and username
				// they will be added after profile fields loop
                if($oldCategoryNameIntern == 'MASTER_DATA'
                && $field->getValue('cat_name_intern') != 'MASTER_DATA')
                {
					$posEndOfMasterData = $i;
					$i = $i + 2;
                }
                
				// add profile field to user field array
                if($field->getValue('usf_hidden') == 0 || $gCurrentUser->editUsers())
                {
                    $gLayout['header'] .= '
                    user_fields['. $i. '] = new Object();
                    user_fields['. $i. ']["cat_id"]   = '. $field->getValue('cat_id'). ';
                    user_fields['. $i. ']["cat_name"] = "'. $field->getValue('cat_name'). '";
                    user_fields['. $i. ']["usf_id"]   = '. $field->getValue('usf_id'). ';
                    user_fields['. $i. ']["usf_name"] = "'. addslashes($field->getValue('usf_name')). '";
                    user_fields['. $i. ']["usf_name_intern"] = "'. addslashes($field->getValue('usf_name_intern')). '";';
                
                    $oldCategoryNameIntern = $field->getValue('cat_name_intern');
                    $i++;
                }
            }       

			// Add loginname and photo at the end of category master data
			// add new category with start and end date of role membership
			if($posEndOfMasterData == 0)
			{
				$posEndOfMasterData = $i;
				$i = $i + 2;
			}
            $gLayout['header'] .= '
			user_fields['. $posEndOfMasterData. '] = new Object();
			user_fields['. $posEndOfMasterData. ']["cat_id"]   = user_fields[1]["cat_id"];
			user_fields['. $posEndOfMasterData. ']["cat_name"] = user_fields[1]["cat_name"];
			user_fields['. $posEndOfMasterData. ']["usf_id"]   = "usr_login_name";
			user_fields['. $posEndOfMasterData. ']["usf_name"] = "'.$gL10n->get('SYS_USERNAME').'";
			user_fields['. $posEndOfMasterData. ']["usf_name_intern"] = "'.$gL10n->get('SYS_USERNAME').'";

			user_fields['. ($posEndOfMasterData+1). '] = new Object();
			user_fields['. ($posEndOfMasterData+1). ']["cat_id"]   = user_fields[1]["cat_id"];;
			user_fields['. ($posEndOfMasterData+1). ']["cat_name"] = user_fields[1]["cat_name"];
			user_fields['. ($posEndOfMasterData+1). ']["usf_id"]   = "usr_photo";
			user_fields['. ($posEndOfMasterData+1). ']["usf_name"] = "'.$gL10n->get('PHO_PHOTO').'";
			user_fields['. ($posEndOfMasterData+1). ']["usf_name_intern"] = "'.$gL10n->get('PHO_PHOTO').'";
					
            user_fields['. $i. '] = new Object();
            user_fields['. $i. ']["cat_id"]   = -1;
            user_fields['. $i. ']["cat_name"] = "'.$gL10n->get('LST_ROLE_INFORMATION').'";
            user_fields['. $i. ']["usf_id"]   = "mem_begin";
            user_fields['. $i. ']["usf_name"] = "'.$gL10n->get('LST_MEMBERSHIP_START').'";
            user_fields['. $i. ']["usf_name_intern"] = "'.$gL10n->get('LST_MEMBERSHIP_START').'";';
            
            $i++;
            $gLayout['header'] .= '
            user_fields['. $i. '] = new Object();
            user_fields['. $i. ']["cat_id"]   = -1;
            user_fields['. $i. ']["cat_name"] = "'.$gL10n->get('LST_ROLE_INFORMATION').'";
            user_fields['. $i. ']["usf_id"]   = "mem_end";
            user_fields['. $i. ']["usf_name"] = "'.$gL10n->get('LST_MEMBERSHIP_END').'";
            user_fields['. $i. ']["usf_name_intern"] = "'.$gL10n->get('LST_MEMBERSHIP_END').'";
            
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
                    $gLayout['header'] .= '
                    default_fields['. $act_field_count. '] = new Object();
                    default_fields['. $act_field_count. ']["usf_id"]    = "'. $form_values['column'. $act_field_count]. '";
                    default_fields['. $act_field_count. ']["sort"]      = "'. $form_values['sort'. $act_field_count]. '";
                    default_fields['. $act_field_count. ']["condition"] = "'. $form_values['condition'. $act_field_count]. '";';
                    
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
                    if($column->getValue('lsc_usf_id') > 0)
                    {
                        $column_content = $column->getValue('lsc_usf_id');
                    }
                    else
                    {
                        $column_content = $column->getValue('lsc_special_field');
                    }
                    $gLayout['header'] .= '
                    default_fields['. $number. '] = new Object();
                    default_fields['. $number. ']["usf_id"]    = "'. $column_content. '";
                    default_fields['. $number. ']["sort"]      = "'. $column->getValue('lsc_sort'). '";
                    default_fields['. $number. ']["condition"] = "'. $column->getValue('lsc_filter'). '";';
                }
            }

            $gLayout['header'] .= '
            return default_fields;
        }

        function loadList()
        {
            var lst_id = $("#lists_config").attr("value");
            var rol_id = $("#rol_id").attr("value");
            var show_members = $("#show_members").attr("value");
            self.location.href = gRootPath + "/adm_program/modules/lists/mylist.php?lst_id=" + lst_id + "&rol_id=" + rol_id + "&active_role='.$getActiveRole.'&show_members=" + show_members;
        }

        function send(mode)
        {
            for(var i = 1; i <= fieldNumberIntern; i++)
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
                    document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=1";
                    document.getElementById("form_mylist").submit();
                    break;

                case "save_as":
                    var listName = "";
                    listName = prompt("'.$gL10n->get('LST_CONFIGURATION_SAVE').'");
                    if(listName != null)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?mode=1&name=" + listName;
                        document.getElementById("form_mylist").submit();
                    }
                    break;

                case "delete":
                    var msg_result = confirm("'.$gL10n->get('LST_CONFIGURATION_DELETE').'");
                    if(msg_result)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=3";
                        document.getElementById("form_mylist").submit();
                    }
                    break;

                case "system":
                    var msg_result = confirm("'.$gL10n->get('LST_WANT_CONFIGURATION_FOR_ALL_USERS').'");
                    if(msg_result)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=4";
                        document.getElementById("form_mylist").submit();
                    }
                    break;

                case "default":
                    var msg_result = confirm("'.$gL10n->get('LST_CONFIGURATION_DEFAULT').'");
                    if(msg_result)
                    {
                        document.getElementById("form_mylist").action  = gRootPath + "/adm_program/modules/lists/mylist_function.php?lst_id='.$getListId.'&mode=5";
                        document.getElementById("form_mylist").submit();
                    }
                    break;
            }
        }
    </script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<form id="form_mylist" action="'. $g_root_path. '/adm_program/modules/lists/mylist_prepare.php" method="post">
<div class="formLayout" id="mylist_form">
    <div class="formHead">'.$gL10n->get('LST_MY_LIST').'</div>
    <div class="formBody">
        <p><b>1.</b> '.$gL10n->get('LST_CHANGE_LIST').'</p>
        <p><b>'.$gL10n->get('LST_CONFIGURATION_LIST').' :</b>&nbsp;&nbsp;
        <select size="1" id="lists_config" name="lists_config" onchange="loadList()">
            <option ';
                if($getListId == 0)
                {
                    $selected = ' selected="selected" ';
                }
                else
                {
                    $selected = '';
                }
            echo $selected.' value="0">'.$gL10n->get('LST_CREATE_NEW_CONFIGURATION').'</option>';

            // alle relevanten Konfigurationen fuer den User suchen
            $sql = 'SELECT * FROM '. TBL_LISTS. '
                     WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id') .'
                       AND (  lst_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                           OR lst_global = 1)
                     ORDER BY lst_global ASC, lst_name ASC, lst_timestamp DESC ';
            $lst_result = $gDb->query($sql);
            
            if($gDb->num_rows() > 0)
            {
                $list_global_flag = '';
                $list_name_flag   = '';
                $optgroup_flag    = 0;
                $counter_unsaved_lists = 0;
                $tableList = new TableLists($gDb);
                
                while($row = $gDb->fetch_array($lst_result))
                {
                    $tableList->clear();
                    $tableList->setArray($row);
                
                    // maximal nur die letzten 5 Konfigurationen ohne Namen speichern
                    if(strlen($tableList->getValue('lst_name')) == 0)
                    {
                        $counter_unsaved_lists++;
                    }

                    if($counter_unsaved_lists > 5 && strlen($tableList->getValue('lst_name')) == 0)
                    {
                        // alle weiteren Konfigurationen ohne Namen loeschen
                        $del_list = new ListConfiguration($gDb, $tableList->getValue('lst_id'));
                        $del_list->delete();
                    }
                    else
                    {
                        // erst mal schauen, ob eine neue Gruppe von Konfigurationen angefangen hat
                        if($tableList->getValue('lst_global') != $tableList_global_flag
                        || ($tableList->getValue('lst_name')  != $tableList_name_flag && strlen($tableList_name_flag) == 0))
                        {
                            if($optgroup_flag == 1)
                            {
                                echo '</optgroup>';
                            }
                            if($tableList->getValue('lst_global') == 0 && strlen($tableList->getValue('lst_name')) == 0)
                            {
                                echo '<optgroup label="'.$gL10n->get('LST_YOUR_LAST_CONFIGURATION').'">';
                            }
                            elseif($tableList->getValue('lst_global') == 0 && strlen($tableList->getValue('lst_name')) > 0)
                            {
                                echo '<optgroup label="'.$gL10n->get('LST_YOUR_CONFIGURATION').'">';
                            }
                            else
                            {
                                echo '<optgroup label="'.$gL10n->get('LST_PRESET_CONFIGURATION').'">';
                            }
                            $tableList_global_flag = $tableList->getValue('lst_global');
                            $tableList_name_flag   = $tableList->getValue('lst_name');
                        }
                        
                        // auf die Konfiguration selektieren, die uebergeben wurde
                        if($getListId == $tableList->getValue('lst_id'))
                        {
                            $selected = ' selected="selected" ';
                        }
                        else
                        {
                            $selected = '';
                        }

                        // Zeitstempel der Konfigurationen ohne Namen oder Namen anzeigen
                        if(strlen($tableList->getValue('lst_name')) == 0)
                        {
                            $description = $tableList->getValue('lst_timestamp', $gPreferences['system_date'].' '.$gPreferences['system_time']);
                        }
                        else
                        {
                            $description = $tableList->getValue('lst_name');
                        }
                        // Comboboxeintrag ausgeben
                        echo '<option '.$selected.' value="'.$tableList->getValue('lst_id').'">'.$description.'</option>';
                    }
                }
                echo '</optgroup>';
            }           
        echo '</select>';
        
        // Listen speichern darf man speichern, wenn es Eigene sind, Neue oder als Webmaster auch Systemlisten
        if(($gCurrentUser->isWebmaster() && $list->getValue('lst_global') == 1)
        || ($gCurrentUser->getValue('usr_id') == $list->getValue('lst_usr_id') && strlen($list->getValue('lst_name')) > 0))
        {
            echo '
            <a class="iconLink" href="javascript:send(\'save\');"><img
                src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('LST_SAVE_CONFIGURATION').'" title="'.$gL10n->get('LST_SAVE_CONFIGURATION').'" /></a>';
        }

        if($gCurrentUser->isWebmaster()
        || $getListId == 0
        || $gCurrentUser->getValue('usr_id') == $list->getValue('lst_usr_id'))
        {
        	if(strlen($list->getValue('lst_name')) > 0)
        	{
        		$icon = 'disk_copy.png';
        		$icon_text = $gL10n->get('LST_SAVE_CONFIGURATION_OTHER_NAME');
        	}
        	else
        	{
        		$icon = 'disk.png';
        		$icon_text = $gL10n->get('LST_SAVE_CONFIGURATION');
        	}
            echo '
            <a class="iconLink" href="javascript:send(\'save_as\');"><img
                src="'. THEME_PATH. '/icons/'.$icon.'" alt="'.$icon_text.'" title="'.$icon_text.'" /></a>';
        }

        // eigene Liste duerfen geloescht werden, Webmaster koennen auch Systemkonfigurationen loeschen
        if(($gCurrentUser->isWebmaster() && $list->getValue('lst_global') == 1)
        || ($gCurrentUser->getValue('usr_id') == $list->getValue('lst_usr_id') && strlen($list->getValue('lst_name')) > 0))
        {
            echo '
            <a class="iconLink" href="javascript:send(\'delete\');"><img
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('LST_DELETE_CONFIGURATION').'" title="'.$gL10n->get('LST_DELETE_CONFIGURATION').'" /></a>';
        }

        // eine gespeicherte Konfiguration kann vom Webmaster zur Systemkonfiguration gemacht werden
        if($gCurrentUser->isWebmaster() && $list->getValue('lst_global') == 0 && strlen($list->getValue('lst_name')) > 0)
        {
            echo '
            <a class="iconLink" href="javascript:send(\'system\');"><img
                src="'. THEME_PATH. '/icons/list_global.png" alt="'.$gL10n->get('LST_CONFIGURATION_ALL_USERS').'" title="'.$gL10n->get('LST_CONFIGURATION_ALL_USERS').'" /></a>';
        }
        
        // eine Systemkonfiguration kann vom Webmaster zur Default-Liste gemacht werden
        if($gCurrentUser->isWebmaster() && $list->getValue('lst_global') == 1)
        {
            echo '
            <a class="iconLink" href="javascript:send(\'default\');"><img
                src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('LST_NEW_DEFAULT_CONFIGURATION').'" title="'.$gL10n->get('LST_NEW_DEFAULT_CONFIGURATION').'" /></a>';
        }
        
        // Hinweistext fuer Webmaster
        if($gCurrentUser->isWebmaster())
        {
            echo '
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=mylist_config_webmaster&amp;inline=true"><img 
                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=mylist_config_webmaster\',this)" onmouseout="ajax_hideTooltip()"
                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
        }
        echo '</p>
        
        <p><b>2.</b> '.$gL10n->get('LST_SET_COLUMNS').':</p>

        <table class="tableList" id="mylist_fields_table" style="width: 100%;" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 20%;">'.$gL10n->get('SYS_ABR_NO').'</th>
                    <th style="width: 37%;">'.$gL10n->get('SYS_CONTENT').'</th>
                    <th style="width: 18%;">'.$gL10n->get('SYS_ORDER').'</th>
                    <th style="width: 25%;">'.$gL10n->get('SYS_CONDITION').'
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=mylist_condition&amp;inline=true"><img 
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_SHOW_HELP').'" title="'.$gL10n->get('SYS_SHOW_HELP').'" /></a>
                    </th>
                </tr>
            </thead>
            <tbody id="mylist_fields_tbody">
                <script type="text/javascript"><!--          
                    for(var counter = 0; counter < '. $default_column_rows. '; counter++)
                    {
                        addColumn();
                    }
                //--></script>
                <tr id="table_row_button">
                    <td colspan="4">
                        <span class="iconTextLink">
                            <a href="javascript:addColumn()"><img
                            src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('LST_ADD_ANOTHER_COLUMN').'" /></a>
                            <a href="javascript:addColumn()">'.$gL10n->get('LST_ADD_ANOTHER_COLUMN').'</a>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <br />
        
        <b>3.</b> '.$gL10n->get('LST_CHOOSE_ROLE').':
        <p><b>'.$gL10n->get('SYS_ROLE').' :</b>&nbsp;&nbsp;';

        // Combobox mit allen Rollen ausgeben, ggf. nur die inaktiven Rollen anzeigen
        $role_select_box_mode = 0;
        if($getActiveRole == 0)
        {
            $role_select_box_mode = 2;
        }
        echo FormElements::generateRoleSelectBox($getRoleId, '', $role_select_box_mode);

        // Auswahlbox, ob aktive oder ehemalige Mitglieder angezeigt werden sollen
        // bei inaktiven Rollen gibt es nur Ehemalige
        if($getActiveRole == 1)
        {
            $selected[0] = '';
            $selected[1] = '';
            $selected[2] = '';
            $selected[$getShowMembers] = ' selected="selected" ';
            echo '&nbsp;&nbsp;&nbsp;
            <select size="1" id="show_members" name="show_members">
                <option '.$selected[0].' value="0">'.$gL10n->get('LST_ACTIVE_MEMBERS').'</option>
                <option '.$selected[1].' value="1">'.$gL10n->get('LST_FORMER_MEMBERS').'</option>
                <option '.$selected[2].' value="2">'.$gL10n->get('LST_ACTIVE_FORMER_MEMBERS').'</option>
            </select>';
        }
        
        echo '<hr />

        <div class="formSubmit">
            <button id="btnShow" type="button" onclick="javascript:send(\'show\');"><img 
                src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('LST_SHOW_LIST').'" />&nbsp;'.$gL10n->get('LST_SHOW_LIST').'</button>
        </div>
    </div>
</div>
</form>';

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($gNavigation->count() > 1)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}
    
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>