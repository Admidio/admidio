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
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=7&amp;folder_id=$folder_id\">
<div class=\"formLayout\" id=\"edit_download_folder_form\" >
    <div class=\"formHead\">Ordnerberechtigungen setzen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <div>
                    <input type=\"checkbox\" id=\"rol_mail_logout\" name=\"rol_mail_logout\" ";

                    //TODO!!!!
                    if($role->getValue("rol_mail_logout") == 1)
                    {
                        echo " checked=\"checked\" ";
                    }
                    if($role->getValue("rol_name") == "Webmaster")
                    {
                        echo " disabled=\"disabled\" ";
                    }
                    echo " onchange=\"markRoleRight('rol_mail_logout', 'rol_mail_login', true)\" value=\"1\" />
                    <label for=\"rol_mail_logout\">Der Ordner ist &ouml;ffentlich.</label>
                    <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"
                     onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag',this);\" onmouseout=\"ajax_hideTooltip()\" />
                </div>
            </li>
        </ul>

        <div style=\"text-align: left; float: left; padding-right: 5%;\">";

            echo "<div>kein Zugriff</div>
            <div>
                <select id=\"AllRoles\" size=\"8\" style=\"width: 200px;\">";
                //TODO!!!!
                while($row = $g_db->fetch_object($allRoles))
                {
                    if(in_array($row->rol_id,$childRoles)  )
                        $childRoleObjects[] = $row;
                    elseif ($row->rol_id == $req_rol_id)
                        continue;
                    else
                        echo "<option value=\"$row->rol_id\">$row->rol_name</option>";
                }
                echo "
                </select>
            </div>
            <div>
                <span class=\"iconTextLink\">
                    <a href=\"javascript:hinzufuegen()\">
                    <img src=\"". THEME_PATH. "/icons/add.png\" alt=\"Rolle hinzufügen\" /></a>
                    <a href=\"javascript:hinzufuegen()\">Rolle hinzufügen</a>
                </span>
            </div>
        </div>
        <div>
            <div>Zugriff</div>
            <div>
                <select id=\"ChildRoles\" name=\"ChildRoles[]\" size=\"8\" multiple style=\"width: 200px;\">";
                    foreach ($childRoleObjects as $childRoleObject)
                    {
                        echo "<option value=\"$childRoleObject->rol_id\">$childRoleObject->rol_name</option>";
                    }
                    echo "
                </select>
            </div>
            <div>
                <span class=\"iconTextLink\">
                    <a href=\"javascript:entfernen()\">
                    <img src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Rolle entfernen\" /></a>
                    <a href=\"javascript:entfernen()\">Rolle entfernen</a>
                </span>
            </div>
        </div>


        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
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
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('new_folder').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>