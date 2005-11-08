<?php
/******************************************************************************
 * Allgemeine Funktionen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

// Funktion fuer das Error-Handling der Datenbank

function db_error ($result, $inline = 0)
{
   global $g_root_path;

   if(!$result && $inline == 0)
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=mysql&err_text=". mysql_error();
      header($location);
      exit();
   }  
   elseif(!$result && $inline == 1)
   {
      echo "Error: ". mysql_error();
      exit();   
   }
}

// die Versionsnummer bitte nicht aendern !!!

function getVersion()
{
   return "1.2b";
}

// die Übergebenen Variablen für den SQL-Code werden geprueft
// dadurch soll es nicht mehr moeglich sein, Code in ein Statement einzuschleusen
//
// Anwendungsbeispiel:
// $sqlQuery = 'SELECT col1, col2 FROM tab1 WHERE col1 = {1} AND col3 = {2} LIMIT {3}';
// $stm = mysql_query(prepareSQL($sqlQuery, array('username', 24.3, 20);

function prepareSQL($queryString, $paramArr) {
   foreach (array_keys($paramArr) as $paramName) {
       if (is_int($paramArr[$paramName])) {
           $paramArr[$paramName] = (int)$paramArr[$paramName];
       }
       elseif (is_numeric($paramArr[$paramName])) {
           $paramArr[$paramName] = (float)$paramArr[$paramName];
       }
       elseif (($paramArr[$paramName] != 'NULL') and ($paramArr[$paramName] != 'NOT NULL')) {
           $paramArr[$paramName] = mysql_escape_string(stripslashes($paramArr[$paramName]));
           $paramArr[$paramName] = '\''.$paramArr[$paramName].'\'';
       }
   }

   return preg_replace('/\{(.*?)\}/ei','$paramArr[\'$1\']', $queryString);
}

// HTTP_REFERER wird gesetzt. Bei Ausnahmen geht es zurueck zur Startseite
// Falls eine URL uebergeben wird, so wird diese geprueft und ggf. zurueckgegeben

function getHttpReferer()
{
   global $g_root_path;
   global $g_main_page;
  
   $exception = 0;
   
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "menue.htm");
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "status.php");
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "err_msg.php");      
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "web.htm");   
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "index.htm");       
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "login.php");       
   if($exception == 0)
      $exception = substr_count($_SERVER['HTTP_REFERER'], "forum_hinweis.php");       
   if($exception == 0)
   {
      $tmp_url = $g_root_path. "/";
      if(strcmp($_SERVER['HTTP_REFERER'], $tmp_url) == 0)
         $exception = 1;      
   }
     
   if($exception == 0)
      return $_SERVER['HTTP_REFERER'];
   else
      return $g_root_path. "/". $g_main_page;
}

// Funktion prueft, ob ein User die uebergebene Rolle besitzt
// Inhalt der Variable "$function" muss gleich dem DB-Feld "rolle.funktion" sein

function hasRole($function, $user_id = 0)
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;
   
   if($user_id == 0)
      $user_id = $g_user_id;
   
   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id        = $user_id
                 AND am_valid        = 1
                 AND am_ar_id        = ar_id
                 AND ar_ag_shortname = '$g_organization'
                 AND ar_funktion     = '$function' 
                 AND ar_valid        = 1 ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   $user_found = mysql_num_rows($result);
   
   if($user_found == 1)
      return 1;
   else
      return 0;
}

// Funktion prueft, ob der angemeldete User Moderatorenrechte hat

function isModerator($user_id = 0)
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   if($user_id == 0)
      $user_id = $g_user_id;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $user_id
                 AND am_valid             = 1
                 AND am_ar_id             = ar_id
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_r_moderation      = 1
                 AND ar_valid             = 1
                   ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

// Funktion prueft, ob der angemeldete User Leiter einer Gruppe /Kurs ist

function isGroupLeader()
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $g_user_id
                 AND am_valid             = 1
                 AND am_leiter            = 1
                 AND am_ar_id             = ar_id
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_valid             = 1
                   ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

// Funktion prueft, ob der angemeldete User Benutzerdaten bearbeiten darf

function editUser()
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $g_user_id
                 AND am_valid             = 1
                 AND am_ar_id             = ar_id
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_r_user_bearbeiten = 1
                 AND ar_valid             = 1
                   ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

// Funktion prueft, ob der angemeldete User Termine anlegen darf

function editDate()
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $g_user_id
                 AND am_ar_id             = ar_id
                 AND am_valid             = 1
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_r_termine         = 1
                 AND ar_valid             = 1 ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

// Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf

function editPhoto()
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $g_user_id
                 AND am_ar_id             = ar_id
                 AND am_valid             = 1
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_r_foto            = 1
                 AND ar_valid             = 1 ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

// Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf

function editDownload()
{
   global $g_user_id;
   global $g_adm_con;
   global $g_organization;

   $sql    = "SELECT *
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id             = $g_user_id
                 AND am_ar_id             = ar_id
                 AND am_valid             = 1
                 AND ar_ag_shortname      = '$g_organization'
                 AND ar_r_download        = 1
                 AND ar_valid             = 1 ";

   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $edit_user = mysql_num_rows($result);

   if($edit_user > 0)
      return true;
   else
      return false;
}

?>