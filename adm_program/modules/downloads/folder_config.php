<?php
/******************************************************************************
 * Ordnerberechtigungen konfigurieren
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : Ordner Id des uebergeordneten Ordners
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/folder_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen
if (array_key_exists("folder_id", $_GET))
{
    if (is_numeric($_GET["folder_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $folder_id = $_GET["folder_id"];
}
else
{
    // ohne FolderId gehts auch nicht weiter
    $g_message->show("invalid");
}



$_SESSION['navigation']->addUrl(CURRENT_URL);

//Folderobject erstellen
$folder = new Folder($g_db);
$folder->getFolderForDownload($folder_id);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$folder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $g_message->show("invalid");
}

//NavigationsLink erhalten
$navigationBar = $folder->getNavigationForDownload();

//Parentordner holen
$parentRoleSet = null;
if ($folder->getValue('fol_fol_id_parent')) {
    $parentFolder = new Folder($g_db);
    $parentFolder->getFolderForDownload($folder->getValue('fol_fol_id_parent'));
    //Rollen des uebergeordneten Ordners holen
    $parentRoleSet = $parentFolder->getRoleArrayOfFolder();

}

if ($parentRoleSet == null) {
        //wenn der uebergeordnete Ordner keine Rollen gesetzt hat sind alle erlaubt
        //alle aus der DB aus lesen
        $sql_roles = "SELECT *
                         FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE rol_valid = 1
                          AND rol_system = 0
                          AND rol_cat_id = cat_id
                          AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                        ORDER BY rol_name";
        $result_roles = $g_db->query($sql_roles);

        while($row_roles = $g_db->fetch_object($result_roles))
        {
            //Jede Rolle wird nun dem Array hinzugefuegt
            $parentRoleSet[] = array(
                                'rol_id'        => $row_roles->rol_id,
                                'rol_name'      => $row_roles->rol_name);

        }

    }


//aktuelles Rollenset des Ordners holen
$roleSet = $folder->getRoleArrayOfFolder();

// Html-Kopf ausgeben
$g_layout['title'] = "Ordnerberechtigungen setzen";

$g_layout['header'] = "
    <script type=\"text/javascript\"><!--
        // Scripts fuer Rollenbox
        function hinzufuegen()
        {
            var allowed_roles = document.getElementById('AllowedRoles');
            var denied_roles  = document.getElementById('DeniedRoles');

            if (denied_roles.selectedIndex >= 0) {
                NeuerEintrag = new Option(denied_roles.options[denied_roles.selectedIndex].text, denied_roles.options[denied_roles.selectedIndex].value, false, true);
                denied_roles.options[denied_roles.selectedIndex] = null;
                allowed_roles.options[allowed_roles.length] = NeuerEintrag;
            }
        }

        function entfernen()
        {
            var allowed_roles = document.getElementById('AllowedRoles');
            var denied_roles  = document.getElementById('DeniedRoles');

            if (allowed_roles.selectedIndex >= 0)
            {
                NeuerEintrag = new Option(allowed_roles.options[allowed_roles.selectedIndex].text, allowed_roles.options[allowed_roles.selectedIndex].value, false, true);
                allowed_roles.options[allowed_roles.selectedIndex] = null;
                denied_roles.options[denied_roles.length] = NeuerEintrag;
            }
        }

        function absenden()
        {
            var allowed_roles = document.getElementById('AllowedRoles');

            allowed_roles.multiple = true;

            for (var i = 0; i < allowed_roles.options.length; i++)
            {
                allowed_roles.options[i].selected = true;
            }

            form.submit();
        }

        function toggleDiv(objectId)
        {
            if (document.getElementById(objectId).style.visibility == 'hidden')
            {
                document.getElementById(objectId).style.visibility = 'visible';
                document.getElementById(objectId).style.display    = 'block';
            }
            else
            {
                document.getElementById(objectId).style.visibility = 'hidden';
                document.getElementById(objectId).style.display    = 'none';
            }
        }

    --></script>";



require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id=$folder_id\">
<div class=\"formLayout\" id=\"edit_download_folder_form\" >
    <div class=\"formHead\">Ordnerberechtigungen setzen</div>
    <div class=\"formBody\">";

    echo "$navigationBar";

        echo "
        <div class=\"groupBox\" style=\"width: 90%;\">
            <div class=\"groupBoxBody\" >
                <div style=\"margin-top: 6px;\">
                    <ul class=\"formFieldList\">
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"fol_public\" name=\"fol_public\" ";

                                if($folder->getValue("fol_public") == 0)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                if($folder->getValue('fol_fol_id_parent') && $parentFolder->getValue("fol_public") == 0)
                                {
                                    echo " disabled=\"disabled\" ";
                                }
                                echo " value=\"0\" onclick=\"toggleDiv('rolesBox');\"/>
                                <label for=\"fol_public\"><img src=\"". THEME_PATH. "/icons/lock.png\" alt=\"Der Ordner ist &ouml;ffentlich.\" /></label>&nbsp;
                                <label for=\"fol_public\">&Ouml;ffentlicher Zugriff ist nicht erlaubt.</label>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                                 onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"
                                 onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag',this);\" onmouseout=\"ajax_hideTooltip()\" />";

                                //Der Wert der DisabledCheckbox muss mit einem versteckten Feld uebertragen werden.
                                if($folder->getValue('fol_fol_id_parent') && $parentFolder->getValue("fol_public") == 0)
                                {
                                    echo "<input type=hidden id=\"fol_public_hidden\" name=\"fol_public\" value=". $parentFolder->getValue("fol_public"). " />";
                                }

                            echo "
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class=\"groupBox\" id=\"rolesBox\" style=\"width: 90%; ";
            if($folder->getValue("fol_public") == 1)
            {
                echo " visibility: hidden; display: none;";
            }
            echo " \">
            <div class=\"groupBoxBody\" >
                <div style=\"margin-top: 6px;\">
                    <p>Hier wird konfiguriert welche Rollen Zugriff auf den Ordner haben d&uuml;rfen.
                       Gesetzte Berechtigungen werden an alle Unterordner vererbt und bereits vorhandene
                       Berechtigungen in Unterordnern werden &uuml;berschrieben. Es stehen nur Rollen
                       zur Verf&uuml;gung die auf den &uuml;bergeordneten Ordner Zugriff haben.</p>

                    <div style=\"text-align: left; float: left;\">";
                        echo "
                        <div><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Kein Zugriff\" title=\"Kein Zugriff\" />Kein Zugriff</div>
                        <div>
                            <select id=\"DeniedRoles\" size=\"8\" style=\"width: 200px;\">";
                            for($i=0; $i<count($parentRoleSet); $i++) {

                                $nextRole = $parentRoleSet[$i];

                                if ($roleSet != null && in_array($nextRole, $roleSet)) {
                                    continue;
                                }
                                else {
                                    echo "<option value=\"". $nextRole['rol_id']. "\">". $nextRole['rol_name']. "</option>";
                                }

                            }

                            echo "
                            </select>
                        </div>
                    </div>
                    <div style=\"float: left;\" class=\"verticalIconList\">
                        <ul>
                            <li>
                                <a class=\"iconLink\" href=\"javascript:hinzufuegen()\">
                                    <img src=\"". THEME_PATH. "/icons/forward.png\" alt=\"Rolle hinzufügen\" title=\"Rolle hinzufügen\" />
                                </a>
                            </li>
                            <li>
                                <a class=\"iconLink\" href=\"javascript:entfernen()\">
                                    <img src=\"". THEME_PATH. "/icons/back.png\" alt=\"Rolle entfernen\" title=\"Rolle entfernen\" />
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/ok.png\" alt=\"Zugriff erlaubt\" title=\"Zugriff erlaubt\" />Zugriff erlaubt</div>
                        <div>
                            <select id=\"AllowedRoles\" name=\"AllowedRoles[]\" size=\"8\" style=\"width: 200px;\">";
                            for($i=0; $i<count($roleSet); $i++) {

                                $nextRole = $roleSet[$i];
                                echo "<option value=\"". $nextRole['rol_id']. "\">". $nextRole['rol_name']. "</option>";
                            }
                            echo "
                            </select>
                        </div>
                    </div>
                 </div>
            </div>
        </div>


        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" onclick=\"absenden()\">
            <img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Berechtigungen speichern\" />
            &nbsp;Berechtigungen speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>