<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
   var $reg_org_shortname;

   // Konstruktor
   function TblUsers($connection)
   {
   	$this->db_connection = $connection;
   	$this->clear();
   }

	// User mit der uebergebenen ID aus der Datenbank auslesen
   function getUser($user_id)
   {
      $sql = "SELECT * FROM ". TBL_USERS. " WHERE usr_id = $user_id";

      $result = mysql_query($sql, $this->db_connection);

      if($row = mysql_fetch_object($result))
      {
         $this->id         = $row->usr_id;
         $this->last_name  = $row->usr_last_name;
         $this->first_name = $row->usr_first_name;
         $this->address    = $row->usr_address;
         $this->zip_code   = $row->usr_zip_code;
         $this->city       = $row->usr_city;
         $this->country    = $row->usr_country;
         $this->phone      = $row->usr_phone;
         $this->mobile     = $row->usr_mobile;
         $this->fax        = $row->usr_fax;
         if($row->usr_birthday == "0000-00-00")
            $this->birthday = "";
          else
            $this->birthday = $row->usr_birthday;
         $this->gender        = $row->usr_gender;
         $this->email         = $row->usr_email;
         $this->homepage      = $row->usr_homepage;
         $this->login_name    = $row->usr_login_name;
         $this->password      = $row->usr_password;
         $this->last_login    = $row->usr_last_login;
         $this->act_login     = $row->usr_act_login;
         $this->num_login     = $row->usr_num_login;
         $this->invalid_login = $row->usr_invalid_login;
         $this->num_invalid   = $row->usr_num_invalid;
         $this->last_change   = $row->usr_last_change;
         $this->usr_id_change = $row->usr_usr_id_change;
   		$this->valid         = $row->usr_valid;
   		$this->reg_org_shortname = $row->usr_reg_org_shortname;
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
		$this->reg_org_shortname = "";
   }

   // aktuelle Userdaten in der Datenbank updaten
   // Es muss die ID des eingeloggten Users uebergeben werden,
   // damit die Aenderung protokolliert werden kann
   function update($login_user_id)
   {
   	if($this->id > 0 && $login_user_id > 0)
   	{
   		$act_date = date("Y-m-d H:i:s", time());

			$sql = "UPDATE ". TBL_USERS. " SET usr_last_name  = '$this->last_name'
                                          , usr_first_name = '$this->first_name'
														, usr_address    = '$this->address'
														, usr_zip_code   = '$this->zip_code'
														, usr_city       = '$this->city'
														, usr_country    = '$this->country'
														, usr_phone      = '$this->phone'
        												, usr_mobile     = '$this->mobile'
                                          , usr_fax        = '$this->fax'
														, usr_birthday   = '$this->birthday'
														, usr_gender     = '$this->gender'
														, usr_email      = '$this->email'
														, usr_homepage   = '$this->homepage'
														, usr_last_login = '$this->last_login'
														, usr_act_login  = '$this->act_login'
														, usr_num_login  = $this->num_login
														, usr_invalid_login = '$this->invalid_login'
														, usr_num_invalid   = $this->num_invalid
														, usr_last_change   = '$act_date'
														, usr_usr_id_change = $login_user_id 
														, usr_valid         = $this->valid
														, usr_reg_org_shortname = '$this->reg_org_shortname' ";
			if(strlen($this->login_name) == 0)
				$sql = $sql. ", usr_login_name = NULL, usr_password = NULL ";
			else
				$sql = $sql. ", usr_login_name = '$this->login_name', usr_password = '$this->password' ";
			$sql = $sql. " WHERE usr_id = $this->id ";
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

   		$sql = "INSERT INTO ". TBL_USERS. " (usr_last_name, usr_first_name, usr_address, usr_zip_code,
									  usr_city, usr_country, usr_phone, usr_mobile, usr_fax, usr_birthday, 
									  usr_gender, usr_email, usr_homepage, usr_last_login, usr_act_login, 
									  usr_num_login, usr_invalid_login, usr_num_invalid, usr_last_change, 
									  usr_usr_id_change, usr_valid, usr_reg_org_shortname, usr_login_name, usr_password )
							 VALUES ('$this->last_name', '$this->first_name', '$this->address', '$this->zip_code',
										'$this->city', '$this->country', '$this->phone', '$this->mobile', '$this->fax', '$this->birthday', 
										'$this->gender', '$this->email', '$this->homepage', NULL, NULL, 
										0,	NULL, 0, '$act_date', $login_user_id, $this->valid, '$this->reg_org_shortname', ";
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

	// aktuellen Benutzer loeschen   
   function delete()
   {
   	$sql    = "DELETE FROM ". TBL_USERS. " 
   	            WHERE usr_id = $this->id ";
		$result = mysql_query($sql, $this->db_connection);
		if(!$result) { echo "Error: ". mysql_error(); exit(); }
		
		$this->clear();
   }
}
?>