<?php
/******************************************************************************
 * Ordnerberechtigungen konfigurieren
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * phoder_id : Ordner Id des uebergeordneten Ordners
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require_once("../../system/classes/table_photos.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show("photoverwaltunsrecht");
}

// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false && $_GET["pho_id"]!=NULL)
{
    $g_message->show("invalid");
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

//Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($g_db);
$photo_album->readData($pho_id);
    
// Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
if($photo_album->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("norights");
}

//$photo_album->getFolderForDownload($phoder_id);

//NavigationsLink erhalten
//$navigationBar = $photo_album->getNavigationForDownload();

//Parentordner holen
$parentRoleSet = null;
if ($photo_album->getValue('pho_pho_id_parent'))
{
    $parentAlbum = new TablePhotos($g_db);
    $parentAlbum->getFolderForDownload($photo_album->getValue('pho_pho_id_parent'));
    //Rollen des uebergeordneten Ordners holen
    $parentRoleSet = $parentAlbum->getRoleArrayOfAlbum();

}

if ($parentRoleSet == null) 
{
	//wenn das uebergeordnete Album keine Rollen gesetzt hat sind alle erlaubt
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
$roleSet = $photo_album->getRoleArrayOfFolder();

// Html-Kopf ausgeben
$g_layout['title'] = "Albumberechtigungen setzen";

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
<form method=\"post\" action=\"$g_root_path/adm_program/modules/downloads/photo_album_function.php?job=set_rights&amp;pho_id=$pho_id\">
<div class=\"formLayout\" id=\"edit_photo_album_form\" >
    <div class=\"formHead\">Albumberechtigungen setzen</div>
    <div class=\"formBody\">";

    echo "$navigationBar";

        echo "
        <div class=\"groupBox\" style=\"width: 90%;\">
            <div class=\"groupBoxBody\" >
                <div style=\"margin-top: 6px;\">
                    <ul class=\"formFieldList\">
                        <li>
                            <div>
                                <input type=\"checkbox\" id=\"pho_public\" name=\"pho_public\" ";

                                if($photo_album->getValue("pho_public") == 0)
                                {
                                    echo " checked=\"checked\" ";
                                }
                                if($photo_album->getValue('pho_pho_id_parent') && $parentFolder->getValue("pho_public") == 0)
                                {
                                    echo " disabled=\"disabled\" ";
                                }
                                echo " value=\"0\" onclick=\"toggleDiv('rolesBox');\"/>
                                <label for=\"pho_public\"><img src=\"". THEME_PATH. "/icons/lock.png\" alt=\"Das Album ist &ouml;ffentlich.\" /></label>&nbsp;
                                <label for=\"pho_public\">&Ouml;ffentlicher Zugriff ist nicht erlaubt.</label>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\"
                                 onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"
                                 onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=publicDownloadFlag',this);\" onmouseout=\"ajax_hideTooltip()\" />";

                                //Der Wert der DisabledCheckbox muss mit einem versteckten Feld uebertragen werden.
                                if($photo_album->getValue('pho_pho_id_parent') && $parentFolder->getValue("pho_public") == 0)
                                {
                                    echo "<input type=hidden id=\"pho_public_hidden\" name=\"pho_public\" value=". $parentFolder->getValue("pho_public"). " />";
                                }

                            echo "
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class=\"groupBox\" id=\"rolesBox\" style=\"width: 90%; ";
            if($photo_album->getValue("pho_public") == 1)
            {
                echo " visibility: hidden; display: none;";
            }
            echo " \">
            <div class=\"groupBoxBody\" >
                <div style=\"margin-top: 6px;\">
                    <p>Hier wird konfiguriert welche Rollen Zugriff auf das Album haben d&uuml;rfen.
                       Gesetzte Berechtigungen werden an alle Unteralben vererbt und bereits vorhandene
                       Berechtigungen in Unteralben werden &uuml;berschrieben. Es stehen nur Rollen
                       zur Verf&uuml;gung die auf das &uuml;bergeordnete Album Zugriff haben.</p>

                    <div style=\"text-align: left; float: left;\">";
                        echo "
                        <div><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/no.png\" alt=\"Kein Zugriff\" title=\"Kein Zugriff\" />Kein Zugriff</div>
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