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

   // Konstruktor
   function TblOrganizations()
   {
   	$this->clear();
   }

	// User mit der uebergebenen ID aus der Datenbank auslesen
   function getOrganization($shortname, $connection)
   {
      $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE ag_shortname = '$shortname'";

      $result = mysql_query($sql, $connection);

      if($row = mysql_fetch_object($result))
      {
         $this->id          = $row->ag_id;
         $this->longname    = $row->ag_longname;
         $this->shortname   = $row->ag_shortname;
         $this->org_shortname_mother= $row->ag_mother;
         $this->homepage    = $row->ag_homepage;
         $this->mail_size   = $row->ag_mail_attachment_size;
         $this->upload_size = $row->ag_upload_size;
         $this->photo_size  = $row->ag_photo_size;
         $this->mail_extern = $row->ag_mail_extern;
         $this->enable_rss  = $row->ag_enable_rss;
         $this->bbcode      = $row->ag_bbcode;
      }
      else
      	$this->clear();
   }

	// alle Klassenvariablen wieder zuruecksetzen
   function clear()
   {
		$this->id          = 0;
		$this->longname    = "";
		$this->shortname   = "";
		$this->org_shortname_mother= "";
		$this->homepage    = "";
		$this->mail_size   = 0;
		$this->upload_size = 0;
		$this->photo_size  = 0;
		$this->mail_extern = 0;
		$this->enable_rss  = 1;
		$this->bbcode     = 1;
	}


   // aktuelle Userdaten in der Datenbank updaten
   function update($connection)
   {
   	if($this->id > 0)
   	{
   		if($this->mail_extern != 1) $this->mail_extern = 0;
   		if($this->enable_rss != 1)  $this->enable_rss = 0;
   		if($this->bbcode != 1)      $this->bbcode = 0;

			$sql = "UPDATE ". TBL_ORGANIZATIONS. "
												 SET ag_longname    = '$this->longname'
                                    	, ag_shortname   = '$this->shortname'
													, ag_mother      = '$this->org_shortname_mother'
													, ag_homepage    = '$this->homepage'
													, ag_mail_attachment_size = $this->mail_size
													, ag_mail_extern = $this->mail_extern
													, ag_enable_rss  = $this->enable_rss
													, ag_bbcode      = $this->bbcode
					   WHERE ag_id = $this->id ";
			$result = mysql_query($sql, $connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }
		   return 0;
     	}
     	return -1;
   }

   // aktuelle Userdaten neu in der Datenbank schreiben
   function insert($connection)
   {
   	if($this->id == 0)
   	{
   		if($this->mail_extern != 1) $this->mail_extern = 0;
   		if($this->enable_rss != 1) $this->enable_rss = 0;
   		if($this->bbcode != 1)     $this->bbcode = 0;

   		$sql = "INSERT INTO ". TBL_ORGANIZATIONS. " (ag_longname, ag_shortname, ag_mother
										ag_homepage, ag_mail_attachement_size,
										ag_mail_extern, ag_enable_rss, ag_bbcode )
							 VALUES ('$this->longname', '$this->shortname', '$this->org_shortname_mother',
										'$this->homepage', $this->mail_size,
										$this->mail_extern, $this->enable_rss, $this->ag_bbcode ) ";
			$result = mysql_query($sql, $connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }

		   $this->id = mysql_insert_id($connection);
		   return 0;
     	}
     	return -1;
   }
}
?>