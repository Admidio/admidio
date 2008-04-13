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
        var actFieldCount = 0;
		var roles         = new Array();

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
            htmlCboFields = "<select size=\"1\" name=\"column" + actFieldCount + "\">" +
					"<option value=\"\"></option>";
			for(var counter = 0; counter < roles.length; counter++)
			{
				if(category != roles[counter]["cat_name"])
				{
					if(category.length > 0)
					{
						htmlCboFields += "</optgroup>";
					}
					htmlCboFields += "<optgroup label=\"" + roles[counter]["cat_name"] + "\">";
					category = roles[counter]["cat_name"];
				}
				var selected = \'\';
				if((actFieldCount == 0 && roles[counter]["usf_name"] == \'Nachname\')
				|| (actFieldCount == 1 && roles[counter]["usf_name"] == \'Vorname\'))
				{
					selected = \' selected="selected" \';
				}
				htmlCboFields += "<option value=\"" + roles[counter]["usf_id"] + "\" " + selected + ">" + roles[counter]["usf_name"] + "</option>"; 
			}
			htmlCboFields += "</select>";
			newCellField.innerHTML = htmlCboFields;
			
			// neue Spalte zur Einstellung der Sortierung
			var selectAsc  = \'\';
			var selectDesc = \'\';
			if(actFieldCount == 0)
			{
				selectAsc = \' selected="selected" \';
			}
			var newCellOrder = newTableRow.insertCell(-1);
			newCellOrder.innerHTML = "<select size=\"1\" name=\"sort" + actFieldCount + "\">" +
			        "<option value=\"\">&nbsp;</option>" +
			        "<option value=\"ASC\" " + selectAsc + ">A bis Z</option>" +
			        "<option value=\"DESC\" " + selectDesc + ">Z bis A</option>" +
			    "</select>";
            
			// neue Spalte fuer Bedingungen
			var newCellConditions = newTableRow.insertCell(-1);
			newCellConditions.innerHTML = "<input type=\"text\" name=\"condition" + actFieldCount + "\" size=\"15\" maxlength=\"30\" value=\"\" />";

			actFieldCount++;
        }';
		
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
				roles['. $i. '] = new Object();
				roles['. $i. '][\'cat_id\'] = '. $old_cat_id. ';
				roles['. $i. '][\'cat_name\'] = \''. $old_cat_name. '\';
				roles['. $i. '][\'usf_id\'] = \'usr_login_name\';
				roles['. $i. '][\'usf_name\'] = \'Benutzername\';';
				$i++;
				
				$g_layout['header'] .= '
				roles['. $i. '] = new Object();
				roles['. $i. '][\'cat_id\'] = '. $old_cat_id. ';
				roles['. $i. '][\'cat_name\'] = \''. $old_cat_name. '\';
				roles['. $i. '][\'usf_id\'] = \'usr_photo\';
				roles['. $i. '][\'usf_name\'] = \'Foto\';';
				$i++;
			}
			
			$g_layout['header'] .= '
			roles['. $i. '] = new Object();
			roles['. $i. '][\'cat_id\'] = '. $value['cat_id']. ';
			roles['. $i. '][\'cat_name\'] = \''. $value['cat_name']. '\';
			roles['. $i. '][\'usf_id\'] = '. $value['usf_id']. ';
			roles['. $i. '][\'usf_name\'] = \''. $value['usf_name']. '\';';
			
			$old_cat_id   = $value['cat_id'];
			$old_cat_name = $value['cat_name'];
			$i++;
        } 		

		// Anfangs- und Enddatum der Rollenmitgliedschaft als Felder noch anhaengen
		$g_layout['header'] .= '
		roles['. $i. '] = new Object();
		roles['. $i. '][\'cat_id\'] = -1;
		roles['. $i. '][\'cat_name\'] = \'Rollendaten\';
		roles['. $i. '][\'usf_id\'] = \'mem_begin\';
		roles['. $i. '][\'usf_name\'] = \'Mitgliedsbeginn\';';
		
		$i++;
		$g_layout['header'] .= '
		roles['. $i. '] = new Object();
		roles['. $i. '][\'cat_id\'] = -1;
		roles['. $i. '][\'cat_name\'] = \'Rollendaten\';
		roles['. $i. '][\'usf_id\'] = \'mem_end\';
		roles['. $i. '][\'usf_name\'] = \'Mitgliedsende\';';

	$g_layout['header'] .= '</script>';

require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<form action=\"$g_root_path/adm_program/modules/lists/mylist_prepare.php\" method=\"post\">
<div class=\"formLayout\" id=\"mylist_form\">
    <div class=\"formHead\">Eigene Liste</div>
    <div class=\"formBody\">
        <b>1.</b> W&auml;hle eine Rolle aus von der du eine Mitgliederliste erstellen willst:
        <p><b>Rolle :</b>&nbsp;&nbsp;";

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
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
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
            <button name=\"anzeigen\" type=\"submit\" value=\"anzeigen\"><img src=\"". THEME_PATH. "/icons/application_view_columns.png\" alt=\"Liste anzeigen\" />&nbsp;Liste anzeigen</button>            
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