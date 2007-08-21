<?php
/******************************************************************************
 * Eigene Listen erstellen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
$_SESSION['navigation']->addUrl($g_current_url);

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
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>
    
    <script type=\"text/javascript\">
        var actFieldCount = $default_fields;
        var resObject     = createXMLHttpRequest();

        function addField() 
        {
            actFieldCount++;
            
            resObject.open('POST', '$g_root_path/adm_program/modules/lists/mylist_field_list.php', true);
            resObject.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            resObject.onreadystatechange = handleResponse;
            resObject.send('field_number=' + actFieldCount);
        }

        function handleResponse() 
        {
            if(resObject.readyState == 4) 
            {
                var table       = document.getElementById('mylist_fields_table');
                var newTableRow = table.insertRow(actFieldCount);
                newTableRow.innerHTML = resObject.responseText;
            }
        }
    </script>";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

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
                echo " checked ";
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
                        <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=condition','Message','width=450,height=600,left=310,top=200,scrollbars=yes')\">
                    </th>
                </tr>
            </thead>
            <tbody>";            
                // Zeilen mit den einzelnen Feldern anzeigen
                for($i = 1; $i <= $default_fields; $i++)
                {
                    echo "<tr>";
                    include("mylist_field_list.php");
                    echo "</tr>";
                }
                echo "
                <tr id=\"table_row_button\">
                    <td colspan=\"4\">&nbsp;
                        <ul class=\"iconTextLink\">
                            <li>
                                <a href=\"javascript:addField()\"><img
                                src=\"$g_root_path/adm_program/images/add.png\" alt=\"Feld hinzuf&uuml;gen\"></a>
                                <a href=\"javascript:addField()\">Feld hinzuf&uuml;gen</a>
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <hr />

        <div class=\"formSubmit\">
            <button name=\"anzeigen\" type=\"submit\" value=\"anzeigen\">
            <img src=\"$g_root_path/adm_program/images/application_view_columns.png\" alt=\"Liste anzeigen\">
            &nbsp;Liste anzeigen</button>            
        </div>
    </div>
</div>
</form>";

// Zurueck-Button nur anzeigen, wenn MyList nicht direkt aufgerufen wurde
if($_SESSION['navigation']->count > 1)
{
    echo "
    <ul class=\"iconTextLink\">
        <li>
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </li>
    </ul>";
}
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>