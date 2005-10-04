<?php
/******************************************************************************
 * Klasse fuer adm_user
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
 
class CUser
{
   var $m_id;
   var $m_name;
   var $m_vorname;
   var $m_adresse;
   var $m_plz;
   var $m_ort;
   var $m_land;
   var $m_tel1;
   var $m_tel2;
   var $m_mobil;
   var $m_fax;
   var $m_geburtstag;
   var $m_mail;
   var $m_weburl;
   var $m_login;
   var $m_password;
   var $m_last_login;
   var $m_act_login;
   var $m_num_login;
   var $m_last_change;
   var $m_last_change_id;

   function GetUser($id, $connection)
   {
      $sql = "SELECT * FROM adm_user WHERE au_id = $id";

      $result = mysql_query($sql, $connection);
      
      if($row = mysql_fetch_object($result))
      {
         $this->m_id                 = $row->au_id;            
         $this->m_name              = $row->au_name;            
         $this->m_vorname           = $row->au_vorname;         
         $this->m_adresse           = $row->au_adresse;         
         $this->m_plz              = $row->au_plz;            
         $this->m_ort              = $row->au_ort;            
         $this->m_land              = $row->au_land;            
         $this->m_tel1              = $row->au_tel1;            
         $this->m_tel2              = $row->au_tel2;            
         $this->m_mobil              = $row->au_mobil;         
         $this->m_fax              = $row->au_fax;               
         if($row->au_geburtstag == "0000-00-00")
            $this->m_geburtstag    = "";
          else
            $this->m_geburtstag    = $row->au_geburtstag;
         $this->m_mail              = $row->au_mail;            
         $this->m_weburl           = $row->au_weburl;         
         $this->m_login              = $row->au_login;
         $this->m_password             = $row->au_password;      
         $this->m_last_login        = $row->au_last_login;      
         $this->m_act_login        = $row->au_act_login;      
         $this->m_num_login        = $row->au_num_login;      
         $this->m_last_change      = $row->au_last_change;
         $this->m_last_change_id   = $row->au_last_change_id;
      }
      else
      {
         $this->m_id               = "";
         $this->m_name            = "";
         $this->m_vorname         = "";
         $this->m_adresse         = "";
         $this->m_plz            = "";
         $this->m_ort            = "";
         $this->m_land            = "";
         $this->m_tel1            = "";
         $this->m_tel2            = "";
         $this->m_mobil            = "";
         $this->m_fax            = "";
         $this->m_geburtstag     = "";
         $this->m_mail            = "";
         $this->m_weburl         = "";
         $this->m_login            = "";
         $this->m_password         = "";
         $this->m_last_login      = "";
         $this->m_act_login      = "";
         $this->m_num_login      = "";
         $this->m_last_change    = "";
         $this->m_last_change_id = "";
      }
   }
}
?>