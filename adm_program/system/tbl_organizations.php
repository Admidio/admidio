<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
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

class TblOrganizations
{
   var $id;
   var $longname;
   var $shortname;
   var $org_shortname_mother;
   var $homepage;
   var $mail_size;
   var $upload_size;
   var $photo_size;
   var $mail_extern;
   var $enable_rss;
   var $bbcode;

   function getOrganization($shortname, $connection)
   {
      $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE ag_shortname = '$shortname'";

      $result = mysql_query($sql, $connection);

      if($row = mysql_fetch_object($result))
      {
         $this->id          = $row->ag_id;
         $this->longname    = $row->ag_longname;
         $this->shortname   = $row->ag_shortname;
         $this->org_shortname_mother= $row->ag_org_shortname_mother;
         $this->homepage    = $row->ag_homepage;
         $this->mail_size   = $row->ag_mail_size;
         $this->upload_size = $row->ag_upload_size;
         $this->photo_size  = $row->ag_photo_size;
         $this->mail_extern = $row->ag_mail_extern;
         $this->enable_rss  = $row->ag_enable_rss;
         $this->bb_code     = $row->ag_bbcode;
      }
      else
      	$this->clear();
   }

   function clear()
   {
		$this->id          = "";
		$this->longname    = "";
		$this->shortname   = "";
		$this->org_shortname_mother= "";
		$this->homepage    = "";
		$this->mail_size   = "";
		$this->upload_size = "";
		$this->photo_size  = "";
		$this->mail_extern = "";
		$this->enable_rss  = "";
		$this->bb_code            = "";
   }
}
?>