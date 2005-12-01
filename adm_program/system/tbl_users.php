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

   function getUser($id, $connection)
   {
      $sql = "SELECT * FROM ". TBL_USERS. " WHERE au_id = $id";

      $result = mysql_query($sql, $connection);

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

   function clear()
   {
		$this->id            = "";
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
		$this->login_name    = "";
		$this->password      = "";
		$this->last_login    = "";
		$this->act_login     = "";
		$this->num_login     = "";
		$this->invalid_login = "";
		$this->num_invalid   = "";
		$this->last_change   = "";
		$this->usr_id_change = "";
		$this->valid         = "";
		$this->valid_shortname = "";
   }
}
?>