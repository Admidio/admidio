<?php
/******************************************************************************
 * Funktionen zuordnen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * ar_id     - Rolle der Mitglieder hinzugefügt oder entfernt werden sollen
 * popup   : 0 - (Default) Fenster wird normal mit Homepagerahmen angezeigt
 *           1 - Fenster wurde im Popupmodus aufgerufen
 * url:        - URL auf die danach weitergeleitet wird
 * restrict:	Begrenzte Userzahl:
 * 				m - (Default) nur Mitglieder
 * 				u - alle in der Datenbank gespeicherten user
 * 
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/tbl_user.php");
require("../../system/session_check_login.php");

//Übernahme der Rolle deren Mitgliederzuordnung bearbeitet werden soll
$role_id=$_GET['ar_id'];

// nur Webmaster & Moderatoren d&uuml;rfen Rollen zuweisen
if(!isModerator() && !isGroupLeader($role_id) && !editUser())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

if(!array_key_exists("popup", $_GET))
   $_GET['popup']    = 0;
if(!array_key_exists("new_user", $_GET))
   $_GET['new_user'] = 0;

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
   $url = $_GET['url'];
else
   $url = "";

//Erfassen der übergeben Rolle
$sql	=	"SELECT *
			FROM ". TBL_ROLES. "
			WHERE ar_id = '$role_id'";
$result_role = mysql_query($sql, $g_adm_con);
         	db_error($result, true);
$role= mysql_fetch_array($result_role);

//festlegen der Spaltenzahl er Tabelle
$column=5;
//Ist die Rolle eine Gruppe mit Leitern, dann 
if($role["ar_gruppe"]==1 && isModerator() && editUser())$column++;

//Übername ob nur Mitglieder oder alle User der Datenbank angezeigt werden sollen
$restrict=$_GET["restrict"];
if($restrict=="" || !isModerator() || !editUser())$restrict="m";

//Falls gefordert, nur Aufruf von inhabern der Rolle Mitglied
if($restrict=="m"){
	$sql = "
		SELECT DISTINCT au_id, au_name, au_vorname, au_geburtstag, au_ort, au_tel1, au_adresse, au_plz
		FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
		WHERE au_id = am_au_id
		AND ar_ag_shortname = '$g_organization'
		AND am_ar_id = ar_id
		AND am_valid = 1
		AND ar_valid = 1
		ORDER BY au_name, au_vorname ASC ";
	$result_user = mysql_query($sql, $g_adm_con);
	db_error($result_user);
	//Zählen wieviele Leute in der Datenbank stehen
	$user_anzahl = mysql_num_rows($result_user);
}

//Falls gefordert, aufrufen alle Leute aus der Datenbank
if($restrict=="u"){
	$sql = "
		SELECT au_id, au_name, au_vorname, au_geburtstag, au_ort, au_tel1, au_adresse, au_plz
		FROM ". TBL_USERS. "
		ORDER BY au_name, au_vorname ASC ";
	$result_user = mysql_query($sql, $g_adm_con);
	db_error($result_user);
	//Zählen wieviele Leute in der Datenbank stehen
	$user_anzahl = mysql_num_rows($result_user);
}

//Erfassen welche anfansgsbuchstaben bei Nachnamen Vorkommen
$first_letter_array = array();
for($x=0; $user = mysql_fetch_array($result_user); $x++){
	if(!in_array(ord($user['au_name']), $first_letter_array))$first_letter_array[$x]= ord($user['au_name']);
}
mysql_data_seek ($result_user, 0);

//Erfassen wer die Rolle bereits hat oder schon mal hatte
$sql = "
	SELECT am_au_id, am_ar_id, am_valid, am_leiter
	FROM ". TBL_MEMBERS. "
	WHERE am_ar_id = '$role_id'";
$result_role_member = mysql_query($sql, $g_adm_con);
db_error($result_role_member);
         	
//Schreiben der User-IDs die die Rolle bereits haben oder hatten in Array
//Schreiben der Leiter der Rolle in weiters arry
$role_member = array();
if($role["ar_gruppe"]==1)$group_leaders = array();
for($y=0; $member = mysql_fetch_array($result_role_member); $y++){
	if($member['am_valid']==1)$role_member[$y]= $member['am_au_id'];
	if($role["ar_gruppe"]==1 && $member["am_leiter"]==1)$group_leaders[$y]= $member['am_au_id'];
}


//Beginn HTML
echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>Mitglieder zuordnen</title>
   <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
   if($_GET['popup'] == 0)
      require("../../../adm_config/header.php");
echo "</head>";

if($_GET['popup'] == 0)
   require("../../../adm_config/body_top.php");
else
   echo "<body>";
   //Beginn Formular
echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <a name=\"Anfang\"></a>
	<form action=\"members_save.php?role_id=".$role_id. "&amp;popup=". $_GET['popup']. "&amp;url=$url\" method=\"post\" name=\"Mitglieder\">
	   <h2>Mitglieder zu $role[2] zuordnen</h2>";
     	//Button Alle bzw. nur Mitglieder anzeigen
     	if($restrict=="m" && (isModerator() || editUser()))
     		echo"	<button name=\"aller\" type=\"button\" value=\"back\" style=\"width: 140px;\" onclick=\"self.location.href='members.php?ar_id=$role_id&amp;popup=1&amp;restrict=u'\">
         			<img src=\"../../../adm_program/images/gruppe.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"\">&nbsp;Alle anzeigen
         		</button>";
     	if($restrict=="u" && (isModerator() || editUser()))
     		echo"	<button name=\"mitglieder\" type=\"button\" value=\"back\" style=\"width: 140px;\" onclick=\"self.location.href='members.php?ar_id=$role_id&amp;popup=1&amp;restrict=m'\">
         			<img src=\"../../../adm_program/images/person.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"\">&nbsp;Nur Mitglieder
         		</button>
					<button name=\"neu\" type=\"button\" value=\"neu\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_edit.php?new_user=1&amp;popup=1'\">
   					<img src=\"$g_root_path/adm_program/images/person_new.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Benutzer anlegen\">
   				&nbsp;Benutzer anlegen</button></p>";
     	//Anfang Tabelle
     	echo"
		<br><br>
		<table class=\"tableList\" cellpadding=\"3\" cellspacing=\"0\" ";
      	if($_GET['popup'] == 1)
        	echo "style=\"width: 95%;\">";
         	echo "
				<tr>
            	<th class=\"tableHeader\" style=\"text-align: center;\">Info</th>
					<th class=\"tableHeader\" style=\"text-align: center;\">Name</th>
            	<th class=\"tableHeader\" style=\"text-align: center;\">Vorname</th>
					<th class=\"tableHeader\" style=\"text-align: center;\">Geburtsdatum</th>
					<th class=\"tableHeader\" style=\"text-align: center;\">Mitglied</th>";
         		if($role["ar_gruppe"]==1 && isModerator() && editUser())echo"
         			<th class=\"tableHeader\" style=\"text-align: center;\">Leiter</th>";
				echo"
				</tr>";
 
  //Ausgabe der Tabellenzeilen, ggf. einfügen von Ankern
         	$user = mysql_fetch_array($result_user);
         //Für alle Namen die mit Zahlen beginnen z.B. 123GmbH
         	$ascii = array(48, 49, 50, 51, 52, 53, 54, 55, 56, 57);
           	
           	if(in_array(ord($user['au_name']), $ascii)){
         		//große Anfangsbuchstaben werden erst ab 50 Personen angezeigt
         		if($user_anzahl>50){
         			echo "<tr><td style=\"text-align: center;\" colspan=\"$column\">";           			
      					//Aktueller Anfangsbuchstabe plus Anker
     						$letter_string = "#";          			
           				echo"<a name=\"$letter_string\"></a><h2>$letter_string</h2>";
 						//Buchstaben Links zu Ankern wenn mehr als 100 Namen angezeigt werden sollen    			
							if($user_anzahl>100){     			
     							echo"<a href=\"#Anfang\">Anfang</a>&nbsp;";
     							for($menu_letter=65; $menu_letter<=90; $menu_letter++){
     							//Falls Aktueller Anfangsbuchstabe, Nur Buchstabe ausgeben
      							$menu_letter_string = chr($menu_letter);    					
     								if($letter==$menu_letter || !in_array($menu_letter, $first_letter_array))echo"$menu_letter_string&nbsp;";
     							//Falls Nicht Link zu Anker
     								if(in_array($menu_letter, $first_letter_array))echo"<a href=\"#$menu_letter_string\">$menu_letter_string</a>&nbsp;";
     							}//for
         				echo"<a href=\"#Ende\">Ende</a>";
							}//User_anzahl>100
         			echo"</td></tr>";
           		}//Ende 
           	for($letter=48; $letter<=57; $letter++){
         		//Ausgabe aller Personen mit entsprechendem Anfangsbuchstaben
	        		$user_name = $user['au_name'] ;
	        		while(ord($user['au_name'])==$letter ||ord($user['au_name'])==$letter+32){
         			$user_text= $user['au_vorname']."&nbsp;".$user['au_name']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_adresse']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_plz']."&nbsp;".$user['au_ort']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_tel1'];
         			echo"
						<tr>
							<td style=\"text-align: center;\">
								<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/note.png\" alt=\"Userinformationen\"
								 title=\"$user_text\">
							</td>
							<td style=\"text-align: center;\">". $user['au_name']."</td>
							<td style=\"text-align: center;\">". $user['au_vorname']."</td>
							<td style=\"text-align: center;\">";
								 if($user['au_geburtstag']!='0000-00-00')echo mysqldate("d.m.y", $user['au_geburtstag']);
							echo"</td>
							<td style=\"text-align: center;\">";
							//Häkchen setzen ob jemand Mitglied ist oder nicht
							if(in_array($user['au_id'], $role_member)){
								echo"<input type=\"checkbox\" id=\"member_$user[0]\" name=\"member_$user[0]\" checked value=\"1\">";
							}
							else{
								echo"<input type=\"checkbox\" id=\"member_$user[0]\" name=\"member_$user[0]\" value=\"1\">";
							}
							echo"</td>";
							//Häkchen setzen ob jemand Leiter ist oder nicht
							if($role["ar_gruppe"]==1 && isModerator() && editUser()){
							echo"<td style=\"text-align: center;\">";
								if(in_array($user['au_id'], $group_leaders)){
									echo"<input type=\"checkbox\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" checked value=\"1\">";
								}
								else{
									echo"<input type=\"checkbox\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" value=\"1\">";
								}
							echo"</td>";
							}
						echo"</tr>";
					$user = mysql_fetch_array($result_user);
         		}//Ende Whileschleife
				}//Ende for-Schleife
         }//Ende Namen mit Zahlen 	
         	
         //Für alle Namen die mit Buchstaben beginnen egal ob klein oder Groß
         	for($letter=65; $letter<=90; $letter++){
           	//große Anfangsbuchstaben werden erst ab 50 Personen angezeigt 
           		if(in_array($letter, $first_letter_array) && $user_anzahl>50){
         			echo "<tr><td style=\"text-align: center;\" colspan=\"$column\">";           			
      				//Aktueller Anfangsbuchstabe plus Anker
     					$letter_string = chr($letter);          			
           			echo"<a name=\"$letter_string\"></a><h2>$letter_string</h2>";
 					//Buchstaben Links zu Ankern wenn mehr als 100 Namen angezeigt werden sollen    			
						if($user_anzahl>100){     			
     						echo"<a href=\"#Anfang\">Anfang</a>&nbsp;";
     						for($menu_letter=65; $menu_letter<=90; $menu_letter++){
     						//Falls Aktueller Anfangsbuchstabe, Nur Buchstabe ausgeben
      						$menu_letter_string = chr($menu_letter);    					
     							if($letter==$menu_letter || !in_array($menu_letter, $first_letter_array))echo"$menu_letter_string&nbsp;";
     						//Falls Nicht Link zu Anker
     							if($letter!=$menu_letter && in_array($menu_letter, $first_letter_array))echo"<a href=\"#$menu_letter_string\">$menu_letter_string</a>&nbsp;";
     						}//for
         			echo"<a href=\"#Ende\">Ende</a>";
						}//User_anzahl>10
         			echo"</td></tr>";
           		}//Ende $letter==$letter_int					
	        	//Ausgabe aller Personen mit entsprechendem Anfangsbuchstaben
	        		while(ord($user['au_name'])==$letter ||ord($user['au_name'])==$letter+32){
         			$user_text= $user['au_vorname']."&nbsp;".$user['au_name']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_adresse']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_plz']."&nbsp;".$user['au_ort']."&nbsp;&nbsp;&nbsp;"
         							.$user['au_tel1'];
         			echo"
						<tr>
							<td style=\"text-align: center;\">
								<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/note.png\" alt=\"Userinformationen\"
								 title=\"$user_text\">
							</td>
							<td style=\"text-align: center;\">". $user['au_name']."</td>
							<td style=\"text-align: center;\">". $user['au_vorname']."</td>
							<td style=\"text-align: center;\">";
								 if($user['au_geburtstag']!='0000-00-00')echo mysqldate("d.m.y", $user['au_geburtstag']);
							echo"</td>
							<td style=\"text-align: center;\">";
							//Häkchen setzen ob jemand Mitglied ist oder nicht
							if(in_array($user['au_id'], $role_member)){
								echo"<input type=\"checkbox\" id=\"member_$user[0]\" name=\"member_$user[0]\" checked value=\"1\">";
							}
							else{
								echo"<input type=\"checkbox\" id=\"member__$user[0]\" name=\"member_$user[0]\" value=\"1\">";
							}
							echo"</td>";
							//Häkchen setzen ob jemand Leiter ist oder nicht
							if($role["ar_gruppe"]==1 && isModerator() && editUser()){
							echo"<td style=\"text-align: center;\">";
								if(in_array($user['au_id'], $group_leaders)){
									echo"<input type=\"checkbox\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" checked value=\"1\">";
								}
								else{
									echo"<input type=\"checkbox\" id=\"leader_$user[0]\" name=\"leader_$user[0]\" value=\"1\">";
								}
							echo"</td>";
							}
						echo"</tr>";
					$user = mysql_fetch_array($result_user);
         		}//Ende Whileschleife
         	}//Ende for-Schleife
      echo"</table>";
      //Buttons schließen oder Speichern
      echo"
	  		<a name=\"Ende\"></a>
			<div style=\"margin: 8px;\">
   	     	<button name=\"speichern\" type=\"submit\" value=\"speichern\">
            	<img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">&nbsp;Speichern
				</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp
            <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
               <img src=\"$g_root_path/adm_program/images/error.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">&nbsp;Schlie&szlig;en
				</button>
     		</div>
   </form> </div>";//Ende Formular
     if($_GET['popup'] == 0)
      require("../../../adm_config/body_bottom.php");

echo "</body>
</html>";
?>