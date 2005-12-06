<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
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

class TblUsers
{
	var $db_connection;
   var $id;
   var $last_name;
   var $first_name;
   var $address;
   var $zip_code;
   var $city;
   var $country;
   var $phone;
   var $mobile;
   var $fax;
   var $birthday;
   var $gender;
   var $email;
   var $homepage;
   var $login_name;
   var $password;
   var $last_login;
   var $act_login;
   var $num_login;
   var $invalid_login;
   var $num_invalid;
   var $last_change;
   var $usr_id_change;
   var $valid;
   var $valid_shortname;

   // Konstruktor
   function TblUsers($connection)
   {
   	$this->db_connection = $connection;
   	$this->clear();
   }

	// User mit der uebergebenen ID aus der Datenbank auslesen
   function getUser($user_id)
   {
      $sql = "SELECT * FROM ". TBL_USERS. " WHERE au_id = $user_id";

      $result = mysql_query($sql, $this->db_connection);

      if($row = mysql_fetch_object($result))
      {
         $this->id         = $row->au_id;
         $this->last_name  = $row->au_name;
         $this->first_name = $row->au_vorname;
         $this->address    = $row->au_adresse;
         $this->zip_code   = $row->au_plz;
         $this->city       = $row->au_ort;
         $this->country    = $row->au_land;
         $this->phone      = $row->au_tel1;
         $this->mobil      = $row->au_mobil;
         $this->fax        = $row->au_fax;
         if($row->au_geburtstag == "0000-00-00")
            $this->birthday = "";
          else
            $this->birthday = $row->au_geburtstag;
         $this->gender        = "";
         $this->email         = $row->au_mail;
         $this->homepage      = $row->au_weburl;
         $this->login_name    = $row->au_login;
         $this->password      = $row->au_password;
         $this->last_login    = $row->au_last_login;
         $this->act_login     = $row->au_act_login;
         $this->num_login     = $row->au_num_login;
         $this->invalid_login = $row->au_invalid_login;
         $this->num_invalid   = $row->au_num_invalid;
         $this->last_change   = $row->au_last_change;
         $this->usr_id_change = $row->au_last_change_id;
   		$this->valid         = "";
   		$this->valid_shortname = "";
      }
      else
      	$this->clear();
   }

	// alle Klassenvariablen wieder zuruecksetzen
   function clear()
   {
		$this->id            = 0;
		$this->last_name     = "";
		$this->first_name    = "";
		$this->address       = "";
		$this->zip_code      = "";
		$this->city          = "";
		$this->country       = "";
		$this->phone         = "";
		$this->mobile        = "";
		$this->fax           = "";
		$this->birthday      = "";
		$this->gender        = "";
		$this->email         = "";
		$this->homepage      = "";
		$this->login_name    = NULL;
		$this->password      = NULL;
		$this->last_login    = "";
		$this->act_login     = "";
		$this->num_login     = 0;
		$this->invalid_login = "";
		$this->num_invalid   = 0;
		$this->last_change   = "";
		$this->usr_id_change = 0;
		$this->valid         = 1;
		$this->valid_shortname = "";
   }

   // aktuelle Userdaten in der Datenbank updaten
   // Es muss die ID des eingeloggten Users uebergeben werden,
   // damit die Aenderung protokolliert werden kann
   function update($login_user_id)
   {
   	if($this->id > 0 && $login_user_id > 0)
   	{
   		$act_date = date("Y-m-d H:i:s", time());

			$sql = "UPDATE ". TBL_USERS. " SET au_name    = '$this->last_name'
                                          , au_vorname = '$this->first_name'
														, au_adresse = '$this->address'
														, au_plz     = '$this->zip_code'
														, au_ort     = '$this->city'
														, au_land    = '$this->country'
														, au_tel1    = '$this->phone'
        												, au_mobil   = '$this->mobile'
                                          , au_fax     = '$this->fax'
														, au_geburtstag = '$this->birthday'
														, au_mail       = '$this->email'
														, au_weburl     = '$this->homepage'
														, au_last_login = '$this->last_login'
														, au_act_login  = '$this->act_login'
														, au_num_login  = $this->num_login
														, au_invalid_login  = '$this->invalid_login'
														, au_num_invalid    = $this->num_invalid
														, au_last_change    = '$act_date'
														, au_last_change_id = $login_user_id ";
			if(strlen($this->login_name) == 0)
				$sql = $sql. ", au_login = NULL, au_password = NULL ";
			else
				$sql = $sql. ", au_login = '$this->login_name', au_password = '$this->password' ";
			$sql = $sql. " WHERE au_id = $this->id ";
			$result = mysql_query($sql, $this->db_connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }
		   return 0;
     	}
     	return -1;
   }

   // aktuelle Userdaten neu in der Datenbank schreiben
   // Es muss die ID des eingeloggten Users uebergeben werden,
   // damit die Aenderung protokolliert werden kann
   function insert($login_user_id)
   {
   	if($this->id == 0 && $login_user_id > 0)
   	{
   		$act_date = date("Y-m-d H:i:s", time());

   		$sql = "INSERT INTO ". TBL_USERS. " (au_name, au_vorname, au_adresse, au_plz,
									  au_ort, au_land, au_tel1, au_mobil, au_fax, au_geburtstag,
									  au_mail, au_weburl, au_last_login, au_act_login, au_num_login,
									  au_invalid_login, au_num_invalid, au_last_change, au_last_change_id,
									  au_login, au_password )
							 VALUES ('$this->last_name', '$this->first_name', '$this->address', '$this->zip_code',
										'$this->city', '$this->country', '$this->phone', '$this->mobile', '$this->fax', '$this->birthday',
										'$this->email', '$this->homepage', NULL, NULL, 0,
										NULL, 0, '$act_date', $login_user_id, ";
			if(strlen($this->login_name) == 0)
				$sql = $sql. " NULL, NULL ) ";
			else
				$sql = $sql. " '$this->login_name', '$this->password' ) ";
			$result = mysql_query($sql, $this->db_connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }

		   $this->id = mysql_insert_id($this->db_connection);
		   return 0;
     	}
     	return -1;
   }
}
?>