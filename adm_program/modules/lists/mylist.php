<?php
/******************************************************************************
 * Eigene Listen erstellen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id : das Feld Rolle kann mit der entsprechenden Rolle vorbelegt werden
 * active_role   : 1 - (Default) aktive Rollen auflisten
 *                 0 - Ehemalige Rollen auflisten
 * active_member : 1 - (Default) aktive Mitglieder der Rolle anzeigen
 *                 0 - Ehemalige Mitglieder der Rolle anzeigen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen und ggf. vorbelegen
$req_rol_id = 0;

if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show("invalid");
    }   
    $req_rol_id = $_GET["rol_id"];
}  

if(!isset($_GET['active_role']))
{
    $active_role = 1;
}
else
{
    if($_GET['active_role'] != 0
    && $_GET['active_role'] != 1)
    {
        $active_role = 1;
    }
    else
    {
        $active_role = $_GET['active_role'];
    }
}   

if(!isset($_GET['active_member']))
{
    $active_member = 1;
}
else
{
    if($_GET['active_member'] != 0
    && $_GET['active_member'] != 1)
    {
        $active_member = 1;
    }
    else
    {
        $active_member = $_GET['active_member'];
    }
}  

if($req_rol_id == 0)
{
    // Navigation faengt hier im Modul an
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

$b_history = false;     // History-Funktion bereits aktiviert ja/nein
$default_fields = 6;    // Anzahl der Felder, die beim Aufruf angezeigt werden

if(isset($_SESSION['mylist_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['mylist_request']);
    unset($_SESSION['mylist_request']);
    $req_rol_id = $form_values['rol_id'];
    if(isset($form_values['former']) && $form_values['former'] == 1)
    {
        $active_member = 0;
    }
    
    // falls vorher schon Felder manuell hinzugefuegt wurden, 
    // muessen diese nun direkt angelegt werden
    for($i = $default_fields+1; $i > 0; $i++)
    {
        if(isset($form_values["column$i"]))
        {
            $default_fields++;          
        }   
        else
        {
            $i = -1;
        }
    }
    
    $b_history = true;
}

// Html-Kopf ausgeben
$g_layout['title']  = "Eigene Liste - Einstellungen";
$g_layout['header'] = '
    <script type="text/javascript">
        var actFieldCount      = 0;
		var arr_user_fields    = createUserFieldsArray();
        var arr_default_fields = createDefaultFieldsArray();

		// Funktion fuegt eine neue Zeile zum Zuordnen von Profilfeldern hinzu
        function addField() 
        {
			var category = "";
			var table = document.getElementById("mylist_fields_tbody");
            var newTableRow = table.insertRow(actFieldCount);
			var newCellCount = newTableRow.insertCell(-1);
            newCellCount.innerHTML = (actFieldCount + 1) + ". Feld :";
			
			// neue Spalte zur Auswahl des Profilfeldes
			var newCellField = newTableRow.insertCell(-1);
            htmlCboFields = "<select size=\"1\" id=\"column" + actFieldCount + "\" name=\"column" + actFieldCount + "\">" +
					"<option value=\"\"></option>";
			for(var counter = 0; counter < arr_user_fields.length; counter++)
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
				if((actFieldCount == 0 && arr_user_fields[counter]["usf_name"] == \'Nachname\')
				|| (actFieldCount == 1 && arr_user_fields[counter]["usf_name"] == \'Vorname\'))
				{
					selected = \' selected="selected" \';
				}
                if(arr_default_fields[actFieldCount])
                {
                    if(arr_user_fields[counter]["usf_id"] == arr_default_fields[actFieldCount][\'usf_id\'])
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
			
            if(arr_default_fields[actFieldCount])
            {
                if(arr_default_fields[actFieldCount][\'sort\'] == "ASC")
                {
                    selectAsc = \' selected="selected" \';
                }
                if(arr_default_fields[actFieldCount][\'sort\'] == "DESC")
                {
                    selectDesc = \' selected="selected" \';
                }
            }
            else if(actFieldCount == 0)
			{
				selectAsc = \' selected="selected" \';
			}
			
			var newCellOrder = newTableRow.insertCell(-1);
			newCellOrder.innerHTML = "<select size=\"1\" id=\"sort" + actFieldCount + "\" name=\"sort" + actFieldCount + "\">" +
			        "<option value=\"\">&nbsp;</option>" +
			        "<option value=\"ASC\" " + selectAsc + ">A bis Z</option>" +
			        "<option value=\"DESC\" " + selectDesc + ">Z bis A</option>" +
			    "</select>";
            
			// neue Spalte fuer Bedingungen
            condition = \'\';
            if(arr_default_fields[actFieldCount])
            {
                if(arr_default_fields[actFieldCount][\'condition\'])
                {
                    condition = arr_default_fields[actFieldCount][\'condition\'];
                    condition = condition.replace(/{/g, "<");
					condition = condition.replace(/}/g, ">");
                }
            }            
			var newCellConditions = newTableRow.insertCell(-1);
			newCellConditions.innerHTML = "<input type=\"text\" id=\"condition" + actFieldCount + "\" name=\"condition" + actFieldCount + "\" size=\"15\" maxlength=\"30\" value=\"" + condition + "\" />";

			actFieldCount++;
        }
        
        function createUserFieldsArray()
        { 
            var user_fields = new Array(); ';
		
    		// Mehrdimensionales Array fuer alle anzuzeigenden Felder mit den noetigen Daten erstellen
    		$i = 0;
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
    			
    			if($value['usf_hidden'] == 0 || $g_current_user->editUser())
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

    		// Anfangs- und Enddatum der Rollenmitgliedschaft als Felder noch anhaengen
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
        
        function createDefaultFieldsArray()
        {
            var default_fields = new Array(); ';
            
            if(isset($form_values))
            {
                // Daten aller Felder werden aus den POST-Daten in ein JS-Array geschrieben
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
                if($act_field_count > $default_fields)
                {
                    $default_fields = $act_field_count;
                }
            }

            $g_layout['header'] .= '
            return default_fields;
        }
        
        function send()
        {
        	for(var i = 0; i < actFieldCount; i++)
        	{
				if(document.getElementById("condition" + i))
				{
					var condition = document.getElementById("condition" + i);
					condition.value = condition.value.replace(/</g, "{");
					condition.value = condition.value.replace(/>/g, "}");
				}
        	}
        
			document.getElementById("form_mylist").action  = "'. $g_root_path. '/adm_program/modules/lists/mylist_prepare.php";
			document.getElementById("form_mylist").submit();
        }
    </script>';

require(THEME_SERVER_PATH. "/overall_header.php");

echo '
<form id="form_mylist" action="'. $g_root_path. '/adm_program/modules/lists/mylist_prepare.php" method="post">
<div class="formLayout" id="mylist_form">
    <div class="formHead">Eigene Liste</div>
    <div class="formBody">
        <b>1.</b> Wähle eine Rolle aus von der du eine Mitgliederliste erstellen willst:
        <p><b>Rolle :</b>&nbsp;&nbsp;';

        // Combobox mit allen Rollen ausgeben
        echo generateRoleSelectBox($req_rol_id);

        echo "&nbsp;&nbsp;&nbsp;
        <input type=\"checkbox\" id=\"former\" name=\"former\" value=\"1\" ";
            if(!$active_member) 
            {
                echo " checked=\"checked\" ";
            }
            echo " />
        <label for=\"former\">nur Ehemalige</label></p>

        <p><b>2.</b> Bestimme die Felder, die in der Liste angezeigt werden sollen:</p>

        <table class=\"tableList\" id=\"mylist_fields_table\" style=\"width: 94%;\" cellspacing=\"0\">
            <thead>
                <tr>
                    <th style=\"width: 18%;\">Nr.</th>
                    <th style=\"width: 37%;\">Feld</th>
                    <th style=\"width: 18%;\">Sortierung</th>
                    <th style=\"width: 27%;\">Bedingung
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=condition&amp;window=true','Message','width=650,height=400,left=310,top=200,scrollbars=no')\" 
                            onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=condition',this);\" onmouseout=\"ajax_hideTooltip()\"/>
                    </th>
                </tr>
            </thead>
            <tbody id=\"mylist_fields_tbody\">
				<script type=\"text/javascript\">          
					for(var counter = 0; counter < ". $default_fields. "; counter++)
					{
						addField();
					}
                </script>
                <tr id=\"table_row_button\">
                    <td colspan=\"4\">
                        <span class=\"iconTextLink\">
                            <a href=\"javascript:addField()\"><img
                            src=\"". THEME_PATH. "/icons/add.png\" alt=\"Feld hinzufügen\" /></a>
                            <a href=\"javascript:addField()\">Feld hinzuf&uuml;gen</a>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <hr />

        <div class=\"formSubmit\">
            <button name=\"anzeigen\" type=\"button\" onclick=\"javascript:send();\" value=\"anzeigen\"><img src=\"". THEME_PATH. "/icons/list.png\" alt=\"Liste anzeigen\" />&nbsp;Liste anzeigen</button>            
        </div>
    </div>
</div>
</form>";

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($_SESSION['navigation']->count > 1)
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>";
}
    
require(THEME_SERVER_PATH. "/overall_footer.php");

?>