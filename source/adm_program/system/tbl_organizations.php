<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
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

class TblOrganizations
{
	var $db_connection;
   var $id;
   var $longname;
   var $shortname;
   var $org_shortname_mother;
   var $org_org_id_parent;
   var $homepage;
   var $mail_size;
   var $upload_size;
   var $photo_size;
   var $mail_extern;
   var $enable_rss;
   var $bbcode;

   // Konstruktor
   function TblOrganizations($connection)
   {
   	$this->db_connection = $connection;
   	$this->clear();
   }

	// User mit der uebergebenen ID aus der Datenbank auslesen
   function getOrganization($shortname)
   {
      $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_shortname = '$shortname'";

      $result = mysql_query($sql, $this->db_connection);

      if($row = mysql_fetch_object($result))
      {
         $this->id          = $row->org_id;
         $this->longname    = $row->org_longname;
         $this->shortname   = $row->org_shortname;
         $this->org_org_id_parent = $row->org_org_id_parent;
         $this->homepage    = $row->org_homepage;
         $this->mail_size   = $row->org_mail_size;
         $this->upload_size = $row->org_upload_size;
         $this->photo_size  = $row->org_photo_size;
         $this->mail_extern = $row->org_mail_extern;
         $this->enable_rss  = $row->org_enable_rss;
         $this->bbcode      = $row->org_bbcode;
      }
      else
      	$this->clear();
      	
      if ($this->org_org_id_parent != "")
      {
      	$sql = "SELECT org_shortname FROM ". TBL_ORGANIZATIONS. " WHERE org_id = '$this->org_org_id_parent'";

      	$result = mysql_query($sql, $this->db_connection);
      	
      	if($row = mysql_fetch_object($result))
      	{  
        	 $this->org_shortname_mother = $row->org_shortname;      
      	}
      	
      }	
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
   function update()
   {
   	if($this->id > 0)
   	{
   		if($this->mail_extern != 1) $this->mail_extern = 0;
   		if($this->enable_rss != 1)  $this->enable_rss = 0;
   		if($this->bbcode != 1)      $this->bbcode = 0;

			$sql = "UPDATE ". TBL_ORGANIZATIONS. "
												 SET org_longname    = '$this->longname'
                                    	, org_shortname   = '$this->shortname'
													, org_org_id_parent      = '$this->org_org_id_parent'
													, org_homepage    = '$this->homepage'
													, org_mail_size = $this->mail_size
													, org_mail_extern = $this->mail_extern
													, org_enable_rss  = $this->enable_rss
													, org_bbcode      = $this->bbcode
					   WHERE org_id = $this->id ";
			$result = mysql_query($sql, $this->db_connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }
		   return 0;
     	}
     	return -1;
   }

   // aktuelle Userdaten neu in der Datenbank schreiben
   function insert()
   {
   	if($this->id == 0)
   	{
   		if($this->mail_extern != 1) $this->mail_extern = 0;
   		if($this->enable_rss != 1) $this->enable_rss = 0;
   		if($this->bbcode != 1)     $this->bbcode = 0;

   		$sql = "INSERT INTO ". TBL_ORGANIZATIONS. " (org_longname, org_shortname, org_org_id_parent
										org_homepage, org_mail_attachement_size,
										org_mail_extern, org_enable_rss, org_bbcode )
							 VALUES ('$this->longname', '$this->shortname', '$this->org_org_id_parent',
										'$this->homepage', $this->mail_size,
										$this->mail_extern, $this->enable_rss, $this->org_bbcode ) ";
			$result = mysql_query($sql, $this->db_connection);
		   if(!$result) { echo "Error: ". mysql_error(); exit(); }

		   $this->id = mysql_insert_id($this->db_connection);
		   return 0;
     	}
     	return -1;
   }
}
?>