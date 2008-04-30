<?php
/******************************************************************************
 * Profil anzeigen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id: zeigt das Profil der uebergebenen user_id an
 *          (wird keine user_id uebergeben, dann Profil des eingeloggten Users anzeigen)
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen 

if(isset($_GET['user_id']))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    // Daten des uebergebenen Users anzeigen
    $a_user_id = $_GET['user_id'];
}
else
{
    // wenn nichts uebergeben wurde, dann eigene Daten anzeigen
    $a_user_id = $g_current_user->getValue("usr_id");
}

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($a_user_id))
{
    $g_message->show("norights");
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($field, $user_id)
{
    global $g_preferences, $g_root_path, $g_current_user;
	$html      = "";
    $value     = "";
	$msg_image = "";
	$messenger = false;
    
    if($g_current_user->editProfile($user_id) == false && $field['usf_hidden'] == 1)
    {
        return "";
    }
    
    switch($field['usf_type'])
    {
        case "CHECKBOX":
            if($field['usd_value'] == 1)
            {
                $value = "<img src=\"". THEME_PATH. "/icons/checkbox_checked.gif\" alt=\"on\" />";
            }
            else
            {
                $value = "<img src=\"". THEME_PATH. "/icons/checkbox.gif\" alt=\"off\" />";
            }
            break;
            
        case "DATE":
            if(strlen($field['usd_value']) > 0)
            {
                $value = mysqldate('d.m.y', $field['usd_value']);
                if($field['usf_name'] == "Geburtstag")
                {
                    // Alter mit ausgeben
                    $value = $value. '&nbsp;&nbsp;&nbsp;('. dtGetAge($field['usd_value']). ' Jahre)';
                }
            }
            break;
            
        case "EMAIL":
            // E-Mail als Link darstellen
            if(strlen($field['usd_value']) > 0)
            {
                if($g_preferences['enable_mail_module'] != 1)
                {
                    $mail_link = "mailto:". $field['usd_value'];
                }
                else
                {
                    $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?usr_id=$user_id";
                }
                if(strlen($field['usd_value']) > 25)
                {
                    $value = '<a href="'. $field['usd_value'].'" target="_blank" title="'. $field['usd_value'].'">'. substr($field['usd_value'], 0, 25). '...</a>';   
                }
                else
                {
                    $value = '<a href="'. $mail_link. '" style="overflow: visible; display: inline;" title="'.$field['usd_value'].'">'. $field['usd_value']. '</a>';;                    
                }               
            }
            break;

        case "URL":
            // Homepage als Link darstellen
            if(strlen($field['usd_value']) > 0)
            {
                if(strlen($field['usd_value']) > 25)
                {
                    $value = '<a href="'. $field['usd_value'].'" target="_blank" title="'. $field['usd_value'].'">'. substr($field['usd_value'], strpos($field['usd_value'], "//") + 2, 25). '...</a>';   
                }
                else
                {
                    $value = '<a href="'. $field['usd_value'].'" target="_blank" title="'. $field['usd_value'].'">'. substr($field['usd_value'], strpos($field['usd_value'], "//") + 2). '</a>';                    
                }
            }
            break;
                                
        case "TEXT_BIG":
            $value = nl2br($field['usd_value']);
            break;
            
        default:
            $value = $field['usd_value'];
            break;
    }
	    
    if($field['cat_name'] != "Stammdaten")
    {
        // Icons der Messenger anzeigen
        if($field['usf_name'] == 'ICQ')
        {
			if(strlen($field['usd_value']) > 0)
			{
	            // Sonderzeichen aus der ICQ-Nummer entfernen (damit kommt www.icq.com nicht zurecht)
	            preg_match_all("/\d+/", $field['usd_value'], $matches);
	            $icq_number = implode("", reset($matches));

	            // ICQ Onlinestatus anzeigen
	            $value = "<a class=\"iconLink\" href=\"http://www.icq.com/people/cmd.php?uin=$icq_number&amp;action=add\"><img 
	                        src=\"http://status.icq.com/online.gif?icq=$icq_number&amp;img=5\" 
	                        alt=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzufügen\" 
	                        title=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzufügen\" /></a>
	                      $value";
			}
			$messenger = true;
        }
        elseif($field['usf_name'] == 'Skype')
        {
			if(strlen($field['usd_value']) > 0)
			{
	            // Skype Onlinestatus anzeigen
	            $value = "<script type=\"text/javascript\" src=\"http://download.skype.com/share/skypebuttons/js/skypeCheck.js\"></script>
	            <a class=\"iconLink\" href=\"skype:". $field['usd_value']. "?add\"><img 
	                src=\"http://mystatus.skype.com/smallicon/". $field['usd_value']. "\"
	                title=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzufügen\" 
	                alt=\"". $field['usd_value']. " zu ". $field['usf_name']. " hinzufügen\" /></a>
	            $value";
			}
			$messenger = true;
        }
        elseif($field['usf_name'] == 'AIM')
        {
            $msg_image = "aim.png";
        }
        elseif($field['usf_name'] == 'Google Talk')
        {
            $msg_image = "google.gif";
        }
        elseif($field['usf_name'] == 'MSN')
        {
            $msg_image = "msn.png";
        }
        elseif($field['usf_name'] == 'Yahoo')
        {
            $msg_image = "yahoo.png";
        }
        if(strlen($msg_image) > 0)
        {
            $value = '<img src="'. THEME_PATH. '/icons/'. $msg_image. '" style="vertical-align: middle;" 
                alt="'. $field['usf_name']. '" title="'. $field['usf_name']. '" />&nbsp;&nbsp;'. $value;
			$messenger = true;
        }
    }
    
	// Feld anzeigen, außer bei Messenger, wenn dieser keine Daten enthält
	if($messenger == false
	|| ($messenger == true && strlen($field['usd_value']) > 0))
	{
	    $html = '<li>
	                <dl>
	                    <dt>'. $field['usf_name']. ':</dt>
	                    <dd>'. $value. '&nbsp;</dd>
	                </dl>
	            </li>';
	}
             
    return $html;
}

// User auslesen
$user = new User($g_db, $a_user_id);

unset($_SESSION['profile_request']);
// Seiten fuer Zuruecknavigation merken
if($a_user_id != $g_current_user->getValue("usr_id") && isset($_GET['user_id']) == false)
{
    $_SESSION['navigation']->clear();
}
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = "Profil";
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/prototype.js\" type=\"text/javascript\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects\" type=\"text/javascript\"></script>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/modules/profile/profile.js\"></script>";

require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<div class=\"formLayout\" id=\"profile_form\">
    <div class=\"formHead\">";
        if($a_user_id == $g_current_user->getValue("usr_id"))
        {
            echo "Mein Profil";
        }
        else
        {
            echo "Profil von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
        }
    echo "</div>

    <div class=\"formBody\">
        <div>";
            // *******************************************************************************
            // Userdaten-Block
            // *******************************************************************************

            echo "
            <div style=\"width: 65%; float: left;\">
                <div class=\"groupBox\">
                    <div class=\"groupBoxHeadline\">
                        <div style=\"float: left;\">". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
                        
                            // Icon des Geschlechts anzeigen
                            if($user->getValue("Geschlecht") > 0)
                            {
                                if($user->getValue("Geschlecht") == 1)
                                {
                                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/male.png\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\" />";
                                }
                                elseif($user->getValue("Geschlecht") == 2)
                                {
                                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/female.png\" title=\"weiblich\" alt=\"weiblich\" />";
                                }
                            }
                        echo "</div>
                        <div style=\"text-align: right;\">
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_function.php?mode=1&amp;user_id=". $user->getValue("usr_id"). "\"><img
                                src=\"". THEME_PATH. "/icons/vcard.png\" 
                                alt=\"vCard von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). " exportieren\" 
                                title=\"vCard von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). " exportieren\" /></a>";
                            
                            // Nur berechtigte User duerfen das Passwort editieren
                            if($a_user_id == $g_current_user->getValue("usr_id") || $g_current_user->isWebmaster())
                            {
                                echo'
                                <a class="iconLink" style="cursor:pointer;" onclick="window.open(\'password.php?usr_id='. $a_user_id. '\',\'Titel\',\'width=350,height=300,left=310,top=200\')"><img 
                                    src="'. THEME_PATH. '/icons/key.png" alt="Passwort ändern" title="Passwort ändern" /></a>';
                            }
                            // Nur berechtigte User duerfen ein Profil editieren
                            if($g_current_user->editProfile($a_user_id) == true)
                            {
                                echo '
                                <a class="iconLink" href="'. $g_root_path. '/adm_program/modules/profile/profile_new.php?user_id='. $a_user_id. '"><img
                                    src="'. THEME_PATH. '/icons/edit.png" alt="Profildaten bearbeiten" title="Profildaten bearbeiten" /></a>';
                            }
                        echo "</div>
                    </div>
                    <div class=\"groupBoxBody\">
                        <ul class=\"formFieldList\">
                            <li>
                                <dl>
                                    <dt>Benutzername:</dt>
                                    <dd><i>". $user->getValue("usr_login_name"). "&nbsp;</i></dd>
                                </dl>
                            </li>";

                            // Schleife ueber alle Felder der Stammdaten

                            foreach($user->db_user_fields as $key => $value)
                            {
                                // nur Felder der Stammdaten anzeigen
                                if($value['cat_name'] == "Stammdaten")
                                {
                                    switch($value['usf_name'])
                                    {
                                        case "Nachname":
                                        case "Vorname":
                                        case "PLZ":
                                        case "Ort":
                                        case "Land":
                                        case "Geschlecht":
                                            // diese Felder werden nicht einzeln dargestellt
                                            break;

                                        case "Adresse":
                                            if($value['usf_name'] == "Adresse")   // nur 1x bei Adresse schreiben
                                            {
                                                echo "<li>
                                                    <dl>
                                                        <dt>Adresse:</dt>
                                                        <dd>";
                                                            if(strlen($user->getValue("Adresse")) > 0)
                                                                echo $user->getValue("Adresse");
                                                            if(strlen($user->getValue("PLZ")) > 0 || strlen($user->getValue("Ort")) > 0)
                                                            {
                                                                echo "<br />";
                                                                if(strlen($user->getValue("PLZ")) > 0)
                                                                    echo $user->getValue("PLZ"). " ";
                                                                if(strlen($user->getValue("Ort")) > 0)
                                                                    echo $user->getValue("Ort");
                                                            }
                                                            if(strlen($user->getValue("Land")) > 0)
                                                                echo "<br />". $user->getValue("Land");

                                                            if($g_preferences['profile_show_map_link']
                                                            && strlen($user->getValue("Adresse")) > 0
                                                            && (  strlen($user->getValue("PLZ"))  > 0
                                                            || strlen($user->getValue("Ort"))  > 0 ))
                                                            {
                                                                // Button mit Karte anzeigen
                                                                $map_url = "http://maps.google.com/?q=". urlencode($user->getValue("Adresse"));
                                                                if(strlen($user->getValue("PLZ"))  > 0)
                                                                {
                                                                    $map_url .= ",%20". urlencode($user->getValue("PLZ"));
                                                                }
                                                                if(strlen($user->getValue("Ort"))  > 0)
                                                                {
                                                                    $map_url .= ",%20". urlencode($user->getValue("Ort"));
                                                                }
                                                                if(strlen($user->getValue("Land"))  > 0)
                                                                {
                                                                    $map_url .= ",%20". urlencode($user->getValue("Land"));
                                                                }

                                                                echo '<br />
                                                                <span class="iconTextLink">
                                                                    <a href="'. $map_url. '" target="_blank"><img
                                                                        src="'. THEME_PATH. '/icons/map.png" alt="Karte" /></a>
                                                                    <a href="'. $map_url. '" target="_blank">Karte</a>
                                                                </span>';

                                                                if($g_current_user->getValue("usr_id") != $a_user_id)
                                                                {
                                                                    if(strlen($g_current_user->getValue("Adresse")) > 0
                                                                    && (  strlen($g_current_user->getValue("PLZ"))  > 0
                                                                    || strlen($g_current_user->getValue("Ort"))  > 0 ))
                                                                    {
                                                                        // Link fuer die Routenplanung
                                                                        $route_url = "http://maps.google.com/?f=d&amp;saddr=". urlencode($g_current_user->getValue("Adresse"));
                                                                        if(strlen($g_current_user->getValue("PLZ"))  > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($g_current_user->getValue("PLZ"));
                                                                        }
                                                                        if(strlen($g_current_user->getValue("Ort"))  > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($g_current_user->getValue("Ort"));
                                                                        }
                                                                        if(strlen($g_current_user->getValue("Land"))  > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($g_current_user->getValue("Land"));
                                                                        }

                                                                        $route_url .= "&amp;daddr=". urlencode($user->getValue("Adresse"));
                                                                        if(strlen($user->getValue("PLZ"))  > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($user->getValue("PLZ"));
                                                                        }
                                                                        if(strlen($user->getValue("Ort")) > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($user->getValue("Ort"));
                                                                        }
                                                                        if(strlen($user->getValue("Land")) > 0)
                                                                        {
                                                                            $route_url .= ",%20". urlencode($user->getValue("Land"));
                                                                        }
                                                                        echo " - <a href=\"$route_url\" target=\"_blank\">Route anzeigen</a>";
                                                                    }
                                                                }
                                                            }
                                                            else
                                                            {
                                                                // Freiraeume ausgeben, damit es layoutmaessig mit dem Bild keine Probleme gibt
                                                                if(strlen($user->getValue("Adresse")) == 0)
                                                                    echo "<br />&nbsp;";
                                                                else
                                                                {
                                                                    if(strlen($user->getValue("PLZ")) == 0 && strlen($user->getValue("Ort")) == 0 && strlen($g_current_user->getValue("Land"))  > 0)
                                                                        echo "<br />&nbsp;";
                                                                }
                                                            }
                                                        echo "</dd>
                                                    </dl>
                                                </li>";
                                            }
                                            break;

                                        default:
                                            echo getFieldCode($value, $a_user_id);
                                            break;
                                    }
                                }
                                else
                                {
                                    // keine Stammdaten mehr also diese Schleife erst einmal abbrechen
                                    break;
                                }
                            }
                        echo "</ul>
                    </div>
                </div>
            </div>";

            echo "<div style=\"width: 28%; float: right\">";

                // *******************************************************************************
                // Bild-Block
                // *******************************************************************************

                //Nachsehen ob fuer den User ein Photo gespeichert wurde
                $sql =" SELECT usr_id
                          FROM ".TBL_USERS."
                         WHERE usr_id = $a_user_id
                           AND usr_photo IS NOT NULL ";
                $result_photo = $g_db->query($sql);

                echo"
                <div class=\"groupBox\">
                    <div class=\"groupBoxBody\" style=\"text-align: center;\">
						<table width=\"100%\" summary=\"Profilfoto\" border=\"0\" style=\"border:0px;\" cellpadding=\"0\" cellspacing=\"0\" rules=\"none\">
							<tr>
								<td>
								<img src=\"$g_root_path/adm_program/modules/profile/profile_photo_show.php?usr_id=$a_user_id&amp;id=". time(). "\" alt=\"Profilfoto\" />
								</td>
							</tr>";
                             // Nur berechtigte User duerfen das Profilfoto editieren
                            if($g_current_user->editProfile($a_user_id) == true)
                            {
                                echo "
                                <tr>
									<td align=\"center\">
										<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$a_user_id\"><img src=\"". THEME_PATH. "/icons/photo.png\" alt=\"Foto ändern\" title=\"Foto ändern\" /></a>";
                                    //Dass Bild kann natürlich nur gelöscht werden, wenn entsprechende Rechte bestehen
	                                if(strlen($user->getValue("usr_photo")) > 0)
	                                {
	                                    echo"
											<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=msg_delete&amp;usr_id=$a_user_id\"><img src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Foto löschen\" title=\"Foto löschen\" /></a>
										</td>";
	                                }
	                                else
	                                {
	                                    echo"</td>";
	                                }
								echo "</tr>";
                            }   
						echo"</table>
                    </div>
                </div>
            </div>
        </div>

        <div style=\"clear: left; font-size: 1pt;\">&nbsp;</div>";

        // *******************************************************************************
        // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
        // *******************************************************************************

        $category = "";
        foreach($user->db_user_fields as $key => $value)
        {
            // Felder der Kategorie Stammdaten wurde schon angezeigt, nun alle anderen anzeigen
            // versteckte Felder nur anzeigen, wenn man das Recht hat, dieses Profil zu editieren
            if($value['cat_name'] != "Stammdaten"
            && (  $g_current_user->editProfile($a_user_id) == true
               || ($g_current_user->editProfile($a_user_id) == false && $value['usf_hidden'] == 0 )))
            {
                // Kategorienwechsel den Kategorienheader anzeigen
                // Kategorie "Messenger" nur anzeigen, wenn auch Daten zugeordnet sind
                if($category != $value['cat_name'] 
                && (  $value['cat_name'] != "Messenger" 
                   || ($value['cat_name'] == "Messenger" && strlen($value['usd_value']) > 0 )))
                {
                    if(strlen($category) > 0)
                    {
                        // div-Container groupBoxBody und groupBox schliessen
                        echo "</ul></div></div>";
                    }
                    $category = $value['cat_name'];

                    echo "<div class=\"groupBox\">
                        <div class=\"groupBoxHeadline\">
                            <div style=\"float: left;\">". $value['cat_name']. "</div>";
                            // Nur berechtigte User duerfen ein Profil editieren
                            if($g_current_user->editProfile($a_user_id) == true)
                            {
                                echo "
                                <div style=\"text-align: right;\">
                                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$a_user_id#cat-". $value['cat_id']. "\"><img
                                        src=\"". THEME_PATH. "/icons/edit.png\" alt=\"". $value['cat_name']. " bearbeiten\" title=\"". $value['cat_name']. " bearbeiten\" /></a>
                                </div>";
                            }
                        echo "</div>
                        <div class=\"groupBoxBody\">
                            <ul class=\"formFieldList\">";
                }

                // Html des Feldes ausgeben
                // bei Kategorie "Messenger" nur anzeigen, wenn auch Daten zugeordnet sind
                if($value['cat_name'] != "Messenger" 
                || ($value['cat_name'] == "Messenger" && strlen($value['usd_value']) > 0 ))
                {
                    echo getFieldCode($value, $a_user_id);
                }
            }
        }

        if(strlen($category) > 0)
        {
            // div-Container groupBoxBody und groupBox schliessen
            echo "</ul></div></div>";
        }

        if($g_preferences['profile_show_roles'] == 1)
        {
            // *******************************************************************************
            // Rollen-Block
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
            
            $count_show_roles = 0;
            $sql = "SELECT *
                      FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_ORGANIZATIONS. "
                     WHERE mem_rol_id = rol_id
                       AND mem_valid  = 1
                       AND mem_usr_id = $a_user_id
                       AND rol_valid  = 1
                       AND rol_cat_id = cat_id
                       AND cat_org_id = org_id
                       AND org_id     = ". $g_current_organization->getValue("org_id"). "
                     ORDER BY org_shortname, cat_sequence, rol_name";
            $result_role = $g_db->query($sql);
            $count_role  = $g_db->num_rows($result_role);

            echo "<div class=\"groupBox\" id=\"profile_roles_box\">
                <div class=\"groupBoxHeadline\">
                    <div style=\"float: left;\">Rollenmitgliedschaften und Berechtigungen&nbsp;</div>";
                        // Moderatoren & Gruppenleiter duerfen neue Rollen zuordnen
                        if(($g_current_user->assignRoles() || isGroupLeader($g_current_user->getValue("usr_id")) || $g_current_user->editUser())
                        && $user->getValue("usr_reg_org_shortname") != $g_current_organization->getValue("org_shortname"))
                        {
                            echo "
                            <div style=\"text-align: right;\">
                                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/roles.php?user_id=$a_user_id\"><img
                                    src=\"". THEME_PATH. "/icons/edit.png\" title=\"Rollen ändern\" alt=\"Rollen ändern\" /></a>
                            </div>";
                        }
                echo '</div>
                <div class="groupBoxBody">
					<ul class="formFieldList" id="role_list">';
						while($row = $g_db->fetch_array($result_role))
						{
							if($g_current_user->viewRole($row['mem_rol_id']))
							{
								// jede einzelne Rolle anzeigen
								echo '<li id="role_'. $row['mem_rol_id']. '">
									<dl>
										<dt>
											'. $row['cat_name']. ' - ';
												if($g_current_user->viewRole($row['mem_rol_id']))
												{
													echo'<a href="'. $g_root_path. '/adm_program/modules/lists/lists_show.php?type=address&mode=html&rol_id='. $row['mem_rol_id']. '" title="'. $row['rol_description']. '">'. $row['rol_name']. '</a>';
												}
												else
												{
													echo $row['rol_name'];  
												}
												if($row['mem_leader'] == 1)
												{
													echo ' - Leiter';
												}
											echo '&nbsp;';
											
											// nun fuer alle Rollenrechte die Icons anzeigen
											if($row['rol_assign_roles'] == 1)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/wand.png\"
												alt=\"Rollen anlegen, bearbeiten, löschen und zuordnen\" title=\"Rollen anlegen, bearbeiten, löschen und zuordnen\" />";
											}
											if($row['rol_approve_users'] == 1)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/properties.png\"
												alt=\"Registrierungen verwalten und zuordnen\" title=\"Registrierungen verwalten und zuordnen\" />";
											}                                                    
											if($row['rol_edit_user'] == 1)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/group.png\"
												alt=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" title=\"Profildaten und Rollenzuordnungen aller Benutzer bearbeiten\" />";
											}
											if($row['rol_profile'] == 1)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/user.png\"
												alt=\"Eigenes Profil bearbeiten\" title=\"Eigenes Profil bearbeiten\" />";
											}
											if($row['rol_announcements'] == 1 && $g_preferences['enable_announcements_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/note.png\"
												alt=\"Ankündigungen anlegen und bearbeiten\" title=\"Ankündigungen anlegen und bearbeiten\" />";
											}
											if($row['rol_dates'] == 1 && $g_preferences['enable_dates_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/date.png\"
												alt=\"Termine anlegen und bearbeiten\" title=\"Termine anlegen und bearbeiten\" />";
											}
											if($row['rol_photo'] == 1 && $g_preferences['enable_photo_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/photo.png\"
												alt=\"Fotos hochladen und bearbeiten\" title=\"Fotos hochladen und bearbeiten\" />";
											}
											if($row['rol_download'] == 1 && $g_preferences['enable_download_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/folder_down.png\"
												alt=\"Downloads hochladen und bearbeiten\" title=\"Downloads hochladen und bearbeiten\" />";
											}
											if($row['rol_guestbook'] == 1 && $g_preferences['enable_guestbook_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/comment.png\"
												alt=\"Gästebucheinträge bearbeiten und löschen\" title=\"Gästebucheinträge bearbeiten und löschen\" />";
											}
											if($row['rol_guestbook_comments'] == 1 && $g_preferences['enable_guestbook_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/comments.png\"
												alt=\"Kommentare zu Gästebucheinträgen anlegen\" title=\"Kommentare zu Gästebucheinträgen anlegen\" />";
											}
											if($row['rol_weblinks'] == 1 && $g_preferences['enable_weblinks_module'] > 0)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/globe.png\"
												alt=\"Weblinks anlegen und bearbeiten\" title=\"Weblinks anlegen und bearbeiten\" />";
											}
											if($row['rol_all_lists_view'] == 1)
											{
												echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/pages_white_text.png\"
												alt=\"Mitgliederlisten aller Rollen einsehen\" title=\"Mitgliederlisten aller Rollen einsehen\" />";
											}
																	echo "</dt>
										<dd>
											seit ". mysqldate('d.m.y', $row['mem_begin']);
											if($g_current_user->assignRoles() || $g_current_user->editUser())

											{
												echo "
												<a class=\"iconLink\" href=\"javascript:deleteRole(". $row['rol_id']. ", '". $row['rol_name']. "', ". $row['rol_valid']. ", ". $user->getValue("usr_id"). ", '". $row['cat_name']. "', '". 
													mysqldate('d.m.y', $row['mem_begin']). "', ". $row['mem_leader']. ", ". $g_current_user->isWebmaster(). ", '". $g_root_path. "', '". $g_preferences['theme']. "')\"><img 
													src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Rolle löschen\" title=\"Rolle löschen\" /></a>";
											}
										echo "</dd>
									</dl>
								</li>";
								$count_show_roles++;  
							}       
						}
						
						if($count_show_roles == 0)
						{
							echo 'Diese Person ist kein Mitglied der Organisation '. 
							$g_current_organization->getValue("org_longname"). ' bzw. es sind keine Rollen sichtbar.';
						}						
					echo '</ul>
                </div>
            </div>';
        }

        if($g_preferences['profile_show_former_roles'] == 1)
        {
            // *******************************************************************************
            // Ehemalige Rollen Block
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet waren
            
            $count_show_roles = 0;
            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_ORGANIZATIONS. "
                        WHERE mem_rol_id = rol_id
                          AND mem_valid  = 0
                          AND mem_usr_id = $a_user_id
                          AND rol_valid  = 1
                          AND rol_cat_id = cat_id
                          AND cat_org_id = org_id
                          AND org_id     = ". $g_current_organization->getValue("org_id"). "
                        ORDER BY org_shortname, cat_sequence, rol_name";
            $result_role = $g_db->query($sql);
            $count_role  = $g_db->num_rows($result_role);
            $visible     = "";
            
            if($count_role == 0)
            {
                $visible = ' style="display: none;" ';
            }

            echo "<div class=\"groupBox\" id=\"profile_former_roles_box\" $visible>
                <div class=\"groupBoxHeadline\">Ehemalige Rollenmitgliedschaften&nbsp;</div>
                <div class=\"groupBoxBody\">
                    <ul class=\"formFieldList\" id=\"former_role_list\">";
						while($row = $g_db->fetch_array($result_role))
						{
							if($g_current_user->viewRole($row['mem_rol_id']))
							{
								// jede einzelne Rolle anzeigen
								echo "
								<li id=\"former_role_". $row['mem_rol_id']. "\">
									<dl>
										<dt>".
											$row['cat_name'];
											if($g_current_user->viewRole($row['mem_rol_id']))
											{
												echo" - <a href=\"$g_root_path/adm_program/modules/lists/lists_show.php?type=address&mode=html&rol_id=". $row['mem_rol_id']. "\">". $row['rol_name']. "</a>";
											}
											else
											{
												echo" - ".$row['rol_name']; 
											}
											if($row['mem_leader'] == 1)
											{
												echo " - Leiter";
											}
										echo "</dt>
										<dd>
											vom ". mysqldate('d.m.y', $row['mem_begin']). "
											bis ". mysqldate('d.m.y', $row['mem_end']);
											if($g_current_user->isWebmaster())
											{
												echo "
												<a class=\"iconLink\" href=\"javascript:deleteFormerRole(". $row['rol_id']. ", '". $row['rol_name']. "', ". $user->getValue("usr_id"). ", '". $g_root_path. "')\"><img 
													src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Rolle löschen\" title=\"Rolle löschen\" /></a>";
											}
										echo "</dd>
									</dl>
								</li>";
								$count_show_roles++;
							}
							if($count_show_roles == 0)
							{
								echo 'Es können keine ehemalige Rollenmitgliedschaften angezeigt werden.';
							}							
						}
                    echo "</ul>
                </div>
            </div>";
        }

        if($g_preferences['profile_show_extern_roles'] == 1
        && (  $g_current_organization->getValue("org_org_id_parent") > 0 
           || $g_current_organization->hasChildOrganizations() ))
        {
            // *******************************************************************************
            // Rollen-Block anderer Organisationen
            // *******************************************************************************

            // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
            $sql = "SELECT *
                      FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_ORGANIZATIONS. "
                     WHERE mem_rol_id = rol_id
                       AND mem_valid  = 1
                       AND mem_usr_id = $a_user_id
                       AND rol_valid  = 1
                       AND rol_this_list_view = 2
                       AND rol_cat_id = cat_id
                       AND cat_org_id = org_id
                       AND org_id    <> ". $g_current_organization->getValue("org_id"). "
                     ORDER BY org_shortname, cat_sequence, rol_name";
            $result_role = $g_db->query($sql);

            if($g_db->num_rows($result_role) > 0)
            {
                echo "<div class=\"groupBox\" id=\"profile_roles_box_other_orga\">
                    <div class=\"groupBoxHeadline\">Rollenmitgliedschaften anderer Organisationen&nbsp;</div>
                    <div class=\"groupBoxBody\">
                        <ul class=\"formFieldList\">";
                            while($row = $g_db->fetch_array($result_role))
                            {
                                // jede einzelne Rolle anzeigen
                                echo "
                                <li>
                                    <dl>
                                        <dt>
                                            ". $row['org_shortname']. " - ".
                                                $row['cat_name']. " - ". $row['rol_name'];
                                                if($row['mem_leader'] == 1)
                                                {
                                                    echo " - Leiter";
                                                }
                                            echo "&nbsp;
                                        </dt>
                                        <dd>seit ". mysqldate('d.m.y', $row['mem_begin']). "</dd>
                                    </dl>
                                </li>";
                            }
                        echo "</ul>
                    </div>
                </div>";
            }
        }
    echo "</div>
</div>";

if(isset($_GET['user_id']) == true)
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

//Habs mal rausgenommen Gruss Jochen
//echo "<div onclick=\"new Effect.Highlight(this)\">Test</div>";

?>