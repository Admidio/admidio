<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Objekt einer Organisation zu erstellen. 
 * Eine Organisation kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $orga = new TblOrganization($g_adm_con);
 *
 * Mit der Funktion getOrganization($shortname) kann die gewuenschte Organisation
 * ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * update() - Die Organisation wird mit den geaenderten Daten in die Datenbank 
 *            zurueckgeschrieben
 * insert() - Eine neue Organisation wird in die Datenbank geschrieben
 * clear()  - Die Klassenvariablen werden neu initialisiert
 * getReferenceOrganizations($child = true, $parent = true)
 *          - Gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
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

class Organization
{
    var $db_connection;
    var $id;
    var $longname;
    var $shortname;
    var $org_id_parent;
    var $homepage;

    // Konstruktor
    function Organization($connection)
    {
        $this->db_connection = $connection;
        $this->clear();
    }

    // Organisation mit der uebergebenen ID aus der Datenbank auslesen
    function getOrganization($shortname)
    {
        if(strlen($shortname) > 0)
        {
            $shortname = strStripTags($shortname);
            $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_shortname = {0}";
            $sql = prepareSQL($sql, array($shortname));
            $result = mysql_query($sql, $this->db_connection);
            db_error($result);
    
            if($row = mysql_fetch_object($result))
            {
                $this->id          = $row->org_id;
                $this->longname    = $row->org_longname;
                $this->shortname   = $row->org_shortname;
                $this->org_id_parent = $row->org_org_id_parent;
                $this->homepage    = $row->org_homepage;
            }
            else
            {
                $this->clear();
            }
        }
        else
        {
            $this->clear();
        }
    }
   


    // alle Klassenvariablen wieder zuruecksetzen
   function clear()
   {
        $this->id            = 0;
        $this->longname      = "";
        $this->shortname     = "";
        $this->org_id_parent = NULL;
        $this->homepage      = "";
    }


    // aktuelle Organisationsdaten in der Datenbank updaten
    function update()
    {
        if($this->id > 0)
        {
            $sql = "UPDATE ". TBL_ORGANIZATIONS. "
                             SET org_longname      = {0}
                               , org_shortname     = {1}
                               , org_org_id_parent = {2}
                               , org_homepage      = {3}
                     WHERE org_id = $this->id ";
            $sql = prepareSQL($sql, array($this->longname, $this->shortname, $this->org_id_parent, $this->homepage));
            $result = mysql_query($sql, $this->db_connection);
            db_error($result);
            return 0;
        }
        return -1;
    }

    // Organisationsdaten in der Datenbank schreiben
    function insert()
    {
        if($this->id == 0)
        {
            $sql = "INSERT INTO ". TBL_ORGANIZATIONS. " (org_longname, org_shortname, org_org_id_parent, org_homepage)
                         VALUES ({0}, {1}, {2}, {3} ) ";
            $sql = prepareSQL($sql, array($this->longname, $this->shortname, $this->org_id_parent, $this->homepage));
            $result = mysql_query($sql, $this->db_connection);
            db_error($result);

            $this->id = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }
    
    // gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
    // Ueber die Variablen $child und $parent kann die ermittlen der 
    // Eltern bzw. Kinderorgas deaktiviert werden
    //
    // org_id ist der Schluessel und org_shortname der Wert des Arrays
    // falls $longname = true gesetzt ist, ist org_longname der Wert des Arrays
    function getReferenceOrganizations($child = true, $parent = true, $longname = false)
    {
        $arr_child_orgas = array();
    
        $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                 WHERE ";
        if($child == true)
        {
            $sql .= " org_org_id_parent = $this->id ";
        }
        if($parent == true
        && $this->org_id_parent > 0)
        {
            if($child == true)
            {
                $sql .= " OR ";
            }
            $sql .= " org_id = $this->org_id_parent ";
        }
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);
        
        while($row = mysql_fetch_object($result))
        {
            if($longname == true)
            {
                $arr_child_orgas[$row->org_id] = $row->org_longname;
            }
            else
            {
                $arr_child_orgas[$row->org_id] = $row->org_shortname;
            }
        }
        return $arr_child_orgas;
    }
}
?>