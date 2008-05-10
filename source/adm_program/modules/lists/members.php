<?php
/******************************************************************************
 * Mitglieder einer Rolle zuordnen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id     - Rolle der Mitglieder hinzugefuegt oder entfernt werden sollen
 * restrict:    Begrenzte Userzahl:
 *              m - (Default) nur Mitglieder
 *              u - alle in der Datenbank gespeicherten user
 * popup   :    0 - (Default) Fenster wird normal mit Homepagerahmen angezeigt
 *              1 - Fenster wurde im Popupmodus aufgerufen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/role_class.php");

// Uebergabevariablen pruefen

if(isset($_GET["rol_id"]) && is_numeric($_GET["rol_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $role_id = $_GET["rol_id"];
}

if(isset($_GET["restrict"]) && $_GET["restrict"] == "u")
{
    $restrict = "u";
}
else
{
    $restrict = "m";
}

//URL auf Navigationstack ablegen, wenn werder selbstaufruf der Seite, noch interner Ankeraufruf
if(!isset($_GET["restrict"]))
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}

// Objekt der uebergeben Rollen-ID erstellen
$role = new Role($g_db, $role_id);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen Mitglied der richtigen Gliedgemeinschaft sein
if(  (!$g_current_user->assignRoles()
   && !isGroupLeader($g_current_user->getValue("usr_id"), $role_id) 
   && !$g_current_user->editUsers()) 
|| (  !$g_current_user->isWebmaster() 
   && $role->getValue("rol_name") == "Webmaster") 
|| $role->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
{
    $g_message->show("norights");
}

if($restrict=="m")
{
    //Falls gefordert, nur Aufruf von Inhabern der Rolle Mitglied
    $sql = "SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, birthday.usd_value as birthday, 
                   city.usd_value as city, phone.usd_value as phone, address.usd_value as address, zip_code.usd_value as zip_code
            FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_USERS. "
            LEFT JOIN ". TBL_USER_DATA. " as last_name
              ON last_name.usd_usr_id = usr_id
             AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as first_name
              ON first_name.usd_usr_id = usr_id
             AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as birthday
              ON birthday.usd_usr_id = usr_id
             AND birthday.usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as city
              ON city.usd_usr_id = usr_id
             AND city.usd_usf_id = ". $g_current_user->getProperty("Ort", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as phone
              ON phone.usd_usr_id = usr_id
             AND phone.usd_usf_id = ". $g_current_user->getProperty("Telefon", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as address
              ON address.usd_usr_id = usr_id
             AND address.usd_usf_id = ". $g_current_user->getProperty("Adresse", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as zip_code
              ON zip_code.usd_usr_id = usr_id
             AND zip_code.usd_usf_id = ". $g_current_user->getProperty("PLZ", "usf_id"). "
            WHERE usr_id   = mem_usr_id
            AND mem_rol_id = rol_id
            AND mem_valid  = 1
            AND rol_valid  = 1
            AND usr_valid  = 1
            AND rol_cat_id = cat_id
            AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            ORDER BY last_name, first_name ";
}
elseif($restrict=="u")
{
    //Falls gefordert, aufrufen alle Leute aus der Datenbank
    $sql = "SELECT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, birthday.usd_value as birthday, 
                   city.usd_value as city, phone.usd_value as phone, address.usd_value as address, zip_code.usd_value as zip_code
            FROM ". TBL_USERS. "
            LEFT JOIN ". TBL_USER_DATA. " as last_name
              ON last_name.usd_usr_id = usr_id
             AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as first_name
              ON first_name.usd_usr_id = usr_id
             AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as birthday
              ON birthday.usd_usr_id = usr_id
             AND birthday.usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as city
              ON city.usd_usr_id = usr_id
             AND city.usd_usf_id = ". $g_current_user->getProperty("Ort", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as phone
              ON phone.usd_usr_id = usr_id
             AND phone.usd_usf_id = ". $g_current_user->getProperty("Telefon", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as address
              ON address.usd_usr_id = usr_id
             AND address.usd_usf_id = ". $g_current_user->getProperty("Adresse", "usf_id"). "
            LEFT JOIN ". TBL_USER_DATA. " as zip_code
              ON zip_code.usd_usr_id = usr_id
             AND zip_code.usd_usf_id = ". $g_current_user->getProperty("PLZ", "usf_id"). "
            WHERE usr_valid = 1
            ORDER BY last_name, first_name ";
}
$result_user = $g_db->query($sql);

//Zaehlen wieviele Leute in der Datenbank stehen
$user_anzahl = $g_db->num_rows($result_user);

///Erfassen welche Anfansgsbuchstaben bei Nachnamen Vorkommen
$first_letter_array = array();
for($x=0; $user = $g_db->fetch_array($result_user); $x++)
{
    //Anfangsbuchstabe erfassen
    $this_letter = ord($user['last_name']);
    
    //falls Kleinbuchstaben
    if($this_letter>=97 && $this_letter<=122)
    {
        $this_letter = $this_letter-32;
    }
    
    //falls zahlen
    if($this_letter>=48 && $this_letter<=57)
    {
        $this_letter = 35;
    }

    //Umlaute zu A
    if($this_letter>=192 && $this_letter<=198)
    {
        $this_letter = 65;
    }
    
    //Umlaute zu O
    if($this_letter>=210 && $this_letter<=214)
    {
        $this_letter = 79;
    }
    
    //Umlaute zu U
    if($this_letter>=217 && $this_letter<=220)
    {
        $this_letter = 85;
    }
    
    $first_letter_array[$x]= $this_letter;
}

//SQL-Abfrag zurück an Anfang setzen
$g_db->data_seek ($result_user, 0);


//Erfassen wer die Rolle bereits hat oder schon mal hatte
$sql="  SELECT mem_usr_id, mem_rol_id, mem_valid, mem_leader
        FROM ". TBL_MEMBERS. "
        WHERE mem_rol_id = $role_id ";
$result_role_member = $g_db->query($sql);

//Schreiben der User-IDs die die Rolle bereits haben oder hatten in Array
//Schreiben der Leiter der Rolle in weiters arry
$role_member   = array();
$group_leaders = array();
for($y=0; $member = $g_db->fetch_array($result_role_member); $y++)
{
    if($member['mem_valid']==1)
    {
        $role_member[$y]= $member['mem_usr_id'];
    }
    if($member["mem_leader"]==1)
    {
        $group_leaders[$y]= $member['mem_usr_id'];
    }
}

// User zaehlen, die mind. einer Rolle zugeordnet sind
$sql    = "SELECT COUNT(*)
             FROM ". TBL_USERS. "
            WHERE usr_valid = 1 ";
$result = $g_db->query($sql);

$row = $g_db->fetch_array($result);
$count_valid_users = $row[0];

// Html-Kopf ausgeben
$g_layout['title']  = "Mitgliederzuordnung für \"". $role->getValue("rol_name"). "\"";
$g_layout['header'] = "
    <script type=\"text/javascript\"><!--
        function markMember(element)
        {
            if(element.checked == true)
            {
                var name   = element.name;
                var pos_number = name.search('_') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'member_' + number;
                document.getElementById(role_name).checked = true;
            }
        }

        function unmarkLeader(element)
        {
            if(element.checked == false)
            {
                var name   = element.name;
                var pos_number = name.search('_') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'leader_' + number;
                document.getElementById(role_name).checked = false;
            }
        }

    // Dieses Array enthaelt alle IDs, die in den Orga-Einstellungen auftauchen
    ids = new Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
                    'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'zahl');


    // Die eigentliche Funktion: Schaltet die Einstellungsdialoge durch
    function toggleDiv(element_id)
    {
        //Alle divs auf unsichtbar setzen
        var i, id_head, id_body;
        for (i=0;i<ids.length;i++)
        {
            id_head = 'head_letter_' + ids[i];
            id_body = 'letter_' + ids[i];
            if(document.getElementById(id_head))
            {
                document.getElementById(id_head).style.visibility = 'hidden';
                document.getElementById(id_head).style.display    = 'none';
                document.getElementById(id_body).style.visibility = 'hidden';
                document.getElementById(id_body).style.display    = 'none';
            }
        }

        // Angeforderten Bereich anzeigen
        id_head = 'head_' + element_id;
        document.getElementById(id_head).style.visibility = 'visible';
        document.getElementById(id_head).style.display    = '';
        document.getElementById(element_id).style.visibility = 'visible';
        document.getElementById(element_id).style.display    = '';
    }

    // Alle Divs anzeigen
    function showAll()
    {
        //Alle divs auf unsichtbar setzen
        var i, id_head, id_body;
        
        for (i=0;i<ids.length;i++)
        {
            id_head = 'head_letter_' + ids[i];
            id_body = 'letter_' + ids[i];
            
            if(document.getElementById(id_head))
            {
                document.getElementById(id_head).style.visibility = 'visible';
                document.getElementById(id_head).style.display    = '';
                document.getElementById(id_body).style.visibility = 'visible';
                document.getElementById(id_body).style.display    = '';
            }
        }     
    }
    --></script>";

require(THEME_SERVER_PATH. "/overall_header.php");
echo "
<h1>". $g_layout['title']. "</h1>";

if(($count_valid_users != $user_anzahl || $restrict == "u")
&& ($g_current_user->assignRoles() || $g_current_user->editUsers()))
{
    //Button Alle bzw. nur Mitglieder anzeigen
    echo "<ul class=\"iconTextLinkList\">";
        if($restrict=="m")
        {
            echo "<li>
                <span class=\"iconTextLink\">
                    <a href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=u\"><img
                    src=\"". THEME_PATH. "/icons/group.png\" alt=\"Alle Benutzer anzeigen\" /></a>
                    <a href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=u\">Alle Benutzer anzeigen</a>
                </span>
            </li>";
        }
        else
        {
            //Nur Mitglieder anzeigen
            echo "<li>
                <span class=\"iconTextLink\">
                    <a href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=m\"><img
                    src=\"". THEME_PATH. "/icons/profile.png\" alt=\"Nur Mitglieder anzeigen\" /></a>
                    <a href=\"members.php?rol_id=$role_id&amp;popup=1&amp;restrict=m\">Nur Mitglieder anzeigen</a>
                </span>
            </li>";

            //aktuelle Rolle in SessionID sichern
            $_SESSION['set_rol_id'] = $role_id;
            //Neuen Benutzer Anlegen
            echo"<li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\"><img
                    src=\"". THEME_PATH. "/icons/add.png\" alt=\"Login\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\">Benutzer anlegen</a>
                </span>
            </li>";
        }
    echo "</ul>";
}

echo "<form action=\"$g_root_path/adm_program/modules/lists/members_save.php?role_id=".$role_id. "\" method=\"post\">";

    //Buchstaben Navigation bei mehr als 50 personen
    if($g_db->num_rows($result_user)>=50)
    {
        //Alle
        echo"<div class=\"pageNavigation\"><a href=\"#\" onclick=\"showAll();\">Alle</a>&nbsp;";

        for($menu_letter=35; $menu_letter<=90; $menu_letter++)
        {
            //Falls Aktueller Anfangsbuchstabe, Nur Buchstabe ausgeben
            $menu_letter_string = chr($menu_letter);
            if(!in_array($menu_letter, $first_letter_array) && $menu_letter>=65 && $menu_letter<=90)
            {
                echo"$menu_letter_string&nbsp;";
            }
            //Falls Nicht Link zu Anker
            if(in_array($menu_letter, $first_letter_array) && $menu_letter>=65 && $menu_letter<=90)
            {
                echo"<a href=\"#\" onclick=\"toggleDiv('letter_$menu_letter_string');\">$menu_letter_string</a>&nbsp;";
            }

            //Fuer Namen die mit Zahlen beginnen
            if($menu_letter == 35)
            {
                if( in_array(35, $first_letter_array))
                {
                    echo"<a href=\"#\" onclick=\"toggleDiv('zahl');\">$menu_letter_string</a>&nbsp;";
                }
                else 
                {
                    echo"&#35;&nbsp;";
                }
                $menu_letter = 64;
            }
        }//for

        echo "</div>";

        //Container anlegen und Ausgabe
        $letter_merker=34;
        $user = $g_db->fetch_array($result_user);

        //Anfangsbuchstabe erfassen
        $this_letter = ord($user['last_name']);

        //falls Kleinbuchstaben
        if($this_letter>=97 && $this_letter<=122)
        {
            $this_letter = $this_letter-32;
        }

        //falls zahlen
        if($this_letter>=48 && $this_letter<=57)
        {
            $this_letter = 35;
        }

        //Umlaute zu A
        if($this_letter>=192 && $this_letter<=198)
        {
            $this_letter = 65;
        }

        //Umlaute zu O
        if($this_letter>=210 && $this_letter<=214)
        {
            $this_letter = 79;
        }

        //Umlaute zu U
        if($this_letter>=217 && $this_letter<=220)
        {
            $this_letter = 85;
        }

        //Tabelle anlegen
        echo"
        <table class=\"tableList\" cellspacing=\"0\">
            <thead>
                <tr>
                    <th>Info</th>
                    <th style=\"text-align: center;\">Mitglied</th>
                    <th>Name</th>
                    <th>Vorname</th>
                    <th>Geburtsdatum</th>
                    <th style=\"text-align: center;\">Leiter<img 
                        class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=leader&amp;window=true','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=leader',this);\" onmouseout=\"ajax_hideTooltip()\"/></th>
                </tr>
            </thead>";

        //Zeilen ausgeben
        for($x=1; $x<=$g_db->num_rows($result_user); $x++)
        {
            //Sprung zu Buchstaben
            if($this_letter!=$letter_merker && $letter_merker==35)
            {
                $letter_merker=64;
            }
            
            //Nach erstem benoetigtem Container suchen, solange leere ausgeben
            while($this_letter != $letter_merker 
            && !in_array($letter_merker+1, $first_letter_array) 
            && $letter_merker < 91)
            {
                //Falls Zahl
                if($letter_merker == 35)
                {
                    $letter_merker++;
                    $letter_string = "zahl";
                }

                //Sonst
                else
                {
                    $letter_merker++;
                    //Buchstabe fuer ID
                    $letter_string = chr($letter_merker);
                }
            }//Ende while

            //Falls neuer Anfangsbuchstabe Container ausgeben
            $letter_text = "";
            if($this_letter!=$letter_merker && $letter_merker)
            {
                //Falls normaler Buchstabe
                if($letter_merker >=64 && $letter_merker <=90)
                {
                    $letter_merker++;
                    //Buchstabe fuer ID
                    $letter_string = chr($letter_merker);
                    $letter_text = $letter_string;
                }

                //Falls Zahl
                if($letter_merker == 34)
                {
                    $letter_merker++;
                    $letter_string = "zahl";
                    $letter_text = "&#35;";
                }

                // Ueberschrift fuer neuen Buchstaben
                $block_id = "letter_$letter_string";
                echo "<tbody id=\"head_$block_id\">
                    <tr>
                        <td class=\"tableSubHeader\" colspan=\"6\">
                            <a href=\"javascript:showHideBlock('$block_id','". THEME_PATH. "')\"><img class=\"iconShowHide\"
                            id=\"img_$block_id\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>$letter_string
                        </td>
                    </tr>
                </tbody>
                <tbody id=\"$block_id\">";
            }


            //Datensatz ausgeben
            $user_text = $user['first_name']."&nbsp;".$user['last_name'];
            if(strlen($user['address']) > 0)
            {
                $user_text = $user_text. " - ". $user['address'];
            }
            if(strlen($user['zip_code']) > 0 || strlen($user['city']) > 0)
            {
                $user_text = $user_text. " - ". $user['zip_code']. " ". $user['city'];
            }
            if(strlen($user['phone']) > 0)
            {
                $user_text = $user_text. " - ". $user['phone'];
            }
            
            echo"
            <tr class=\"tableMouseOver\">
                <td><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/profile.png\" alt=\"Userinformationen\" title=\"$user_text\" /></td>
                
                <td style=\"text-align: center;\">";
                    //Haekchen setzen ob jemand Mitglied ist oder nicht
                    if(in_array($user['usr_id'], $role_member))
                    {
                        echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_". $user['usr_id']. "\" name=\"member_". $user['usr_id']. "\" checked=\"checked\" value=\"1\" />";
                    }
                    else
                    {
                        echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_". $user['usr_id']. "\" name=\"member_". $user['usr_id']. "\" value=\"1\" />";
                    }
                echo"</td>
                
                <td>". $user['last_name']."</td>
                <td>". $user['first_name']."</td>

                <td>";
                    //Geburtstag nur ausgeben wenn bekannt
                    if(strlen($user['birthday']) > 0)
                    {
                        echo mysqldate("d.m.y", $user['birthday']);
                    }
                echo"</td>

                <td style=\"text-align: center;\">";
                    //Haekchen setzen ob jemand Leiter ist oder nicht
                    if(in_array($user['usr_id'], $group_leaders))
                    {
                        echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_". $user['usr_id']. "\" name=\"leader_". $user['usr_id']. "\" checked=\"checked\" value=\"1\" />";
                    }
                    else
                    {
                        echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_". $user['usr_id']. "\" name=\"leader_". $user['usr_id']. "\" value=\"1\" />";
                    }
                echo"</td>
            </tr>";

            //Naechsten Datensatz abrufen
            $user = $g_db->fetch_array($result_user);

            //Anfangsbuchstabe erfassen
            $this_letter = ord($user['last_name']);

            //falls Kleinbuchstaben
            if($this_letter>=97 && $this_letter<=122)
            {
                $this_letter = $this_letter-32;
            }

            //falls zahlen
            if($this_letter>=48 && $this_letter<=57)
            {
                $this_letter = 35;
            }

            //Umlaute zu A
            if($this_letter>=192 && $this_letter<=198)
            {
                $this_letter = 65;
            }

            //Umlaute zu O
            if($this_letter>=210 && $this_letter<=214)
            {
                $this_letter = 79;
            }

            //Umlaute zu U
            if($this_letter>=217 && $this_letter<=220)
            {
                $this_letter = 85;
            }

            if($this_letter != $letter_merker || $g_db->num_rows($result_user)+1==$x)
            {
                echo "</tbody>";
            }
            //Ende Container
        }//End For
        echo "</table>";

    }//Ende if >50


    //fuer weniger als 50 Benutzer
    else
    {
        //Tabelle anlegen
        echo"
        <table class=\"tableList\" cellspacing=\"0\" >
            <thead>
                <tr>
                    <th>Info</th>
                    <th style=\"text-align: center;\">Mitglied</th>
                    <th>Name</th>
                    <th>Vorname</th>
                    <th>Geburtsdatum</th>
                    <th style=\"text-align: center;\">Leiter<img 
                    	class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\" 
                    	onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=leader&amp;window=true','Message','width=600,height=500,left=310,top=200,scrollbars=yes')\" 
                    	onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=leader',this);\" onmouseout=\"ajax_hideTooltip()\"/>
                    </th>
                </tr>
            </thead>
            <tbody>";

            while($user = $g_db->fetch_array($result_user))
            {
                 //Datensatz ausgeben
                $user_text= $user['first_name']."&nbsp;".$user['last_name']."&nbsp;&nbsp;&nbsp;"
                            .$user['address']."&nbsp;&nbsp;&nbsp;"
                            .$user['zip_code']."&nbsp;".$user['city']."&nbsp;&nbsp;&nbsp;"
                            .$user['phone'];
                echo"
                <tr class=\"tableMouseOver\">
                    <td><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/profile.png\" alt=\"Userinformationen\" title=\"$user_text\" /></td>

                    <td style=\"text-align: center;\">";
                        //Haekchen setzen ob jemand Mitglied ist oder nicht
                        if(in_array($user['usr_id'], $role_member))
                        {
                            echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_". $user['usr_id']. "\" name=\"member_". $user['usr_id']. "\" checked=\"checked\" value=\"1\" />";
                        }
                        else
                        {
                            echo"<input type=\"checkbox\" onclick=\"unmarkLeader(this)\" id=\"member_". $user['usr_id']. "\" name=\"member_". $user['usr_id']. "\" value=\"1\" />";
                        }
                    echo"</td>

                    <td>". $user['last_name']."</td>
                    <td>". $user['first_name']."</td>
                    <td>";
                        //Geburtstag nur ausgeben wenn bekannt
                        if($user['birthday']!='0000-00-00')
                        {
                            echo mysqldate("d.m.y", $user['birthday']);
                        }
                    echo"</td>

                    <td style=\"text-align: center;\">";
                        //Haekchen setzen ob jemand Leiter ist oder nicht
                        if(in_array($user['usr_id'], $group_leaders))
                        {
                            echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_". $user['usr_id']. "\" name=\"leader_". $user['usr_id']. "\" checked=\"checked\" value=\"1\" />";
                        }
                        else
                        {
                            echo"<input type=\"checkbox\" onclick=\"markMember(this)\" id=\"leader_". $user['usr_id']. "\" name=\"leader_". $user['usr_id']. "\" value=\"1\" />";
                        }
                    echo"</td>
                </tr>";

            }

            echo "</tbody>
        </table>";
    }
    
    //Buttons schliessen oder Speichern
    echo"<div class=\"formSubmit\">
        <button name=\"speichern\" type=\"submit\" value=\"speichern\"><img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />&nbsp;Speichern</button>
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
//nur bei mehr als 50
if($g_db->num_rows($result_user)>=50)
{
    echo"
    <div class=\"smallFontSize\">
        Das Zwischenspeichern vor dem Buchstabenwechsel ist nicht notwendig&#33;&#33;&#33;
    </div>";
}
   
require(THEME_SERVER_PATH. "/overall_footer.php");

?>