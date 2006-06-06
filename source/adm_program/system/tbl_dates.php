<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 *
 * Diese Klasse dient dazu einen Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $date = new TblDates($g_adm_con);
 *
 * Mit der Funktion getDate($dat_id) kann nun der gewuenschte Termin ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * delete()               - Der gewaehlte User wird aus der Datenbank geloescht
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * getIcal()              - Es wird eine vCard des Users als String zurueckgegeben
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

class TblDates
{
    var $db_connection;
    var $id;
    var $org_shortname;
    var $global;
    var $begin;
    var $end;
    var $description;
    var $location;
    var $headline;
    var $usr_id;
    var $timestamp;
    var $last_change;
    var $usr_id_change;
    
    
    // Konstruktor
    function TblDates($connection)
    {
        $this->db_connection = $connection;
        $this->clear();
    }

    // User mit der uebergebenen ID aus der Datenbank auslesen
    function getDate($dat_id)
    {
        $sql = "SELECT * FROM ". TBL_DATES. " WHERE dat_id = $dat_id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        if($row = mysql_fetch_object($result))
        {
            $this->id             = $row->dat_id;
            $this->org_shortname  = $row->dat_org_shortname;
            $this->global         = $row->dat_global;
            $this->begin          = $row->dat_begin;
            $this->end            = $row->dat_end;
            $this->description    = $row->dat_description;
            $this->location       = $row->dat_location;
            $this->headline       = $row->dat_headline;
            $this->usr_id         = $row->dat_usr_id;
            $this->timestamp      = $row->dat_timestamp;
            $this->last_change    = $row->dat_last_change;
            $this->usr_id_change  = $row->dat_usr_id_change;            
        }
        else
        {
            $this->clear();
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        $this->id             = 0;
        $this->org_shortname  = "";
        $this->global         = 0;
        $this->begin          = "";
        $this->end            = "";
        $this->description    = "";
        $this->location       = "";
        $this->headline       = "";
        $this->usr_id         = 0;
        $this->timestamp      = "";
        $this->last_change    = "";
        $this->usr_id_change  = 0;
    }


    // aktuellen Benutzer loeschen   
    function delete()
    {
        $sql    = "DELETE FROM ". TBL_DATES. " 
                    WHERE dat_id = $this->id ";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $this->clear();
    }
   
    // gibt die Userdaten als VCard zurueck   
    function getIcal() 
    {
        $iCal  = (string) "BEGIN:VCALENDAR\r\n";
        $iCal .= (string) "VERSION:2.0\r\n";
        $iCal .= (string) "PRODID:-//www.admidio.org//Admidio" . getVersion() . "\r\n";
        $iCal  = (string) "BEGIN:VEVENT\r\n";
        if (strlen(trim($this->begin)) > 0) 
        {
            $iCal .= (string) "DTSTART:" . mysqldatetime("ymdThis", $this->begin) . "\r\n";
        }
        if (strlen(trim($this->end)) > 0) 
        {
           $iCal .= (string) "DTEND:" . mysqldatetime("ymdThis", $this->end) . "\r\n";
        }
        //if (strlen(trim($this->headline)) > 0) 
        //{
        //   $iCal .= (string) "SUMMARY:" . this->headline . "\r\n";
        //}
        //if (strlen(trim($this->description)) > 0) 
        //{
        //    $iCal .= (string) "DESCRIPTION:" . this->description . "\r\n";
        //}
        //if (strlen(trim($this->location)) > 0) 
        //{
        //    $iCal .= (string) "LOCATION:" . this->location . "\r\n";
        //}
        
        $iCal .= (string) "END:VEVENT\r\n";
        $iCal.= (string) "END:VCALENDAR\r\n";
        return $iCal;
    }
}
?>