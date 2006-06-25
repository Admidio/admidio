<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Userobjekt zu erstellen. 
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $user = new TblUsers($g_adm_con);
 *
 * Mit der Funktion getUser($user_id) kann nun der gewuenschte User ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * update($login_user_id) - User wird mit den geaenderten Daten in die Datenbank 
 *                          zurueckgeschrieben
 * insert($login_user_id) - Ein neuer User wird in die Datenbank geschrieben
 * delete()               - Der gewaehlte User wird aus der Datenbank geloescht
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * getVCard()             - Es wird eine vCard des Users als String zurueckgegeben
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
    var $actual_login;
    var $number_login;
    var $date_invalid;
    var $number_invalid;
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
        db_error($result);

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
            {
                $this->birthday = "";
            }
            else
            {
                $this->birthday = $row->usr_birthday;
            }
            $this->gender         = $row->usr_gender;
            $this->email          = $row->usr_email;
            $this->homepage       = $row->usr_homepage;
            $this->login_name     = $row->usr_login_name;
            $this->password       = $row->usr_password;
            $this->last_login     = $row->usr_last_login;
            $this->actual_login   = $row->usr_actual_login;
            $this->number_login   = $row->usr_number_login;
            $this->date_invalid   = $row->usr_date_invalid;
            $this->number_invalid = $row->usr_number_invalid;
            $this->last_change    = $row->usr_last_change;
            $this->usr_id_change  = $row->usr_usr_id_change;
            $this->valid          = $row->usr_valid;
            $this->reg_org_shortname = $row->usr_reg_org_shortname;
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
        $this->last_name      = "";
        $this->first_name     = "";
        $this->address        = "";
        $this->zip_code       = "";
        $this->city           = "";
        $this->country        = "";
        $this->phone          = "";
        $this->mobile         = "";
        $this->fax            = "";
        $this->birthday       = "";
        $this->gender         = "";
        $this->email          = "";
        $this->homepage       = "";
        $this->login_name     = NULL;
        $this->password       = NULL;
        $this->last_login     = "";
        $this->actual_login   = "";
        $this->number_login   = 0;
        $this->date_invalid   = "";
        $this->number_invalid = 0;
        $this->last_change    = "";
        $this->usr_id_change  = 0;
        $this->valid          = 1;
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
                                                        , usr_actual_login   = '$this->actual_login'
                                                        , usr_number_login   = $this->number_login
                                                        , usr_date_invalid   = '$this->date_invalid'
                                                        , usr_number_invalid = $this->number_invalid
                                                        , usr_last_change    = '$act_date'
                                                        , usr_usr_id_change  = $login_user_id 
                                                        , usr_valid          = $this->valid ";
            if(strlen($this->reg_org_shortname) == 0)
                $sql = $sql. ", usr_reg_org_shortname = NULL ";
            else
                $sql = $sql. ", usr_reg_org_shortname = '$this->reg_org_shortname' ";
            if(strlen($this->login_name) == 0)
                $sql = $sql. ", usr_login_name = NULL, usr_password = NULL ";
            else
                $sql = $sql. ", usr_login_name = '$this->login_name', usr_password = '$this->password' ";
            $sql = $sql. " WHERE usr_id = $this->id ";

            $result = mysql_query($sql, $this->db_connection);
            db_error($result);
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
                                  usr_gender, usr_email, usr_homepage, usr_last_login, usr_actual_login, 
                                  usr_number_login, usr_date_invalid, usr_number_invalid, usr_last_change, 
                                  usr_usr_id_change, usr_valid, usr_reg_org_shortname, usr_login_name, usr_password )
                         VALUES ('$this->last_name', '$this->first_name', '$this->address', '$this->zip_code',
                                 '$this->city', '$this->country', '$this->phone', '$this->mobile', '$this->fax', '$this->birthday', 
                                 '$this->gender', '$this->email', '$this->homepage', NULL, NULL, 
                                 0,  NULL, 0, '$act_date', $login_user_id, $this->valid ";
            if(strlen($this->reg_org_shortname) == 0)
                $sql = $sql. ", NULL ";
            else
                $sql = $sql. ", '$this->reg_org_shortname' ";
            if(strlen($this->login_name) == 0)
                $sql = $sql. ", NULL, NULL ) ";
            else
                $sql = $sql. ", '$this->login_name', '$this->password' ) ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result);

            $this->id = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }

    // aktuellen Benutzer loeschen   
    function delete()
    {
        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id = NULL
                    WHERE ann_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id_change = NULL
                    WHERE ann_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id = NULL
                    WHERE dat_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id_change = NULL
                    WHERE dat_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id = NULL
                    WHERE gbo_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_LINKS. " SET lnk_usr_id = NULL
                    WHERE lnk_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id = NULL
                    WHERE pho_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_change = NULL
                    WHERE pho_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_ROLES. " SET rol_usr_id_change = NULL
                    WHERE rol_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_ROLE_DEPENDENCIES. " SET rld_usr_id = NULL
                    WHERE rld_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "UPDATE ". TBL_USERS. " SET usr_usr_id_change = NULL
                    WHERE usr_usr_id_change = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "DELETE FROM ". TBL_MEMBERS. " WHERE mem_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $sql    = "DELETE FROM ". TBL_USER_DATA. " WHERE usd_usr_id = $this->id";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);
    
        $sql    = "DELETE FROM ". TBL_USERS. " 
                    WHERE usr_id = $this->id ";
        $result = mysql_query($sql, $this->db_connection);
        db_error($result);

        $this->clear();
    }
   
    // gibt die Userdaten als VCard zurueck   
    function getVCard() 
    {
        $vcard  = (string) "BEGIN:VCARD\r\n";
        $vcard .= (string) "VERSION:2.1\r\n";
        $vcard .= (string) "N:" . $this->last_name. ";". $this->first_name . ";;;\r\n";
        $vcard .= (string) "FN:". $this->first_name . " ". $this->last_name . "\r\n";
        if (strlen(trim($this->login_name)) > 0) 
        {
            $vcard .= (string) "NICKNAME:" . $this->login_name . "\r\n";
        }
        if (strlen(trim($this->phone)) > 0) 
        {
            $phonenumber = ereg_replace("[^[:digit:]]", "", $this->phone);
            $vcard .= (string) "TEL;HOME;VOICE:" . $phonenumber . "\r\n";
        }
        if (strlen(trim($this->mobile)) > 0) 
        {
            $mobilenumber = ereg_replace("[^[:digit:]]", "", $this->mobile);
            $vcard .= (string) "TEL;CELL;VOICE:" . $mobilenumber . "\r\n";
        }
        if (strlen(trim($this->fax)) > 0) 
        {
            $faxnumber = ereg_replace("[^[:digit:]]", "", $this->fax);
            $vcard .= (string) "TEL;HOME;FAX:" . $faxnumber . "\r\n";
        }
        $vcard .= (string) "ADR;HOME:;;" . $this->address . ";" . $this->city . ";;" . $this->zip_code . ";" . $this->country . "\r\n";
        if (strlen(trim($this->homepage)) > 0) 
        {
            $vcard .= (string) "URL;HOME:" . $this->homepage . "\r\n";
        }
        if (strlen(trim($this->birthday)) > 0) 
        {
            $vcard .= (string) "BDAY:" . mysqldatetime("ymd", $this->birthday) . "\r\n";
        }
        if (strlen(trim($this->email)) > 0) 
        {
            $vcard .= (string) "EMAIL;PREF;INTERNET:" . $this->email . "\r\n";
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($this->gender > 0) 
        {
            if($this->gender == 1)
            {
                $wab_gender = 2;
            }
            else
            {
                $wab_gender = 1;
            }
            $vcard .= (string) "X-WAB-GENDER:" . $wab_gender . "\r\n";
        }
        if (strlen(trim($this->last_change)) > 0) 
        {
            $vcard .= (string) "REV:" . mysqldatetime("ymdThis", $this->last_change) . "\r\n";
        }
        
        $vcard .= (string) "END:VCARD\r\n";
        return $vcard;
    }
}
?>