<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu einen Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $user = new User($g_adm_con);
 *
 * Mit der Funktion getUser($user_id) kann nun der gewuenschte User ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
                          der adm_user oder der adm_user_fields Tabelle
 * getValue($field_name)- gibt den Wert eines Feldes zurueck
 * save($login_user_id) - User wird mit den geaenderten Daten in die Datenbank
 *                        zurueckgeschrieben bwz. angelegt
 * delete()             - Der gewaehlte User wird aus der Datenbank geloescht
 * clear()              - Die Klassenvariablen werden neu initialisiert
 * getVCard()           - Es wird eine vCard des Users als String zurueckgegeben
 * isWebmaster()        - gibt true/false zurueck, falls der User Mitglied der 
 *                        Rolle "Webmaster" ist
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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

class User
{
    var $db_connection;
    var $webmaster;
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
    
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der User-Tabelle des entsprechenden Users
    var $db_user_fields = array();  // Array ueber alle Felder der User-Fields-Tabelle des entsprechenden Users

    //User Rechte
    var $assignRolesRight;
    var $editProfile;
    var $editUser;
    var $commentGuestbookRight;
    var $editGuestbookRight;
    var $editWeblinksRight;
    var $editDownloadRight;
    var $editPhotoRight;

    // Konstruktor
    function User($connection, $user_id = 0)
    {
        $this->db_connection = $connection;
        //if($user_id > 0)
        {
            $this->getUser($user_id);
        }
        /*else
        {
            $this->clear();
        }*/
    }

    function reconnect($connection)
    {
        $this->db_connection = $connection;
    }

    // User mit der uebergebenen ID aus der Datenbank auslesen
    function getUser($user_id)
    {
        global $g_current_organization;
        $this->clear();
        
        if(is_numeric($user_id))
        {
            if($user_id > 0)
            {
                $sql = "SELECT * FROM ". TBL_USERS. " WHERE usr_id = $user_id";
                $result = mysql_query($sql, $this->db_connection);
                db_error($result,__FILE__,__LINE__);            

                if($row = mysql_fetch_array($result, MYSQL_ASSOC))
                {                
                    // Daten in das Klassenarray schieben
                    foreach($row as $key => $value)
                    {
                        if($key != "usr_photo")
                        {
                            $this->db_fields[$key] = $value;
                        }
                    }

                    // Variablen fuellen
                    $this->id         = $row['usr_id'];
                    $this->last_name  = $row['usr_last_name'];
                    $this->first_name = $row['usr_first_name'];
                    $this->address    = $row['usr_address'];
                    $this->zip_code   = $row['usr_zip_code'];
                    $this->city       = $row['usr_city'];
                    $this->country    = $row['usr_country'];
                    $this->phone      = $row['usr_phone'];
                    $this->mobile     = $row['usr_mobile'];
                    $this->fax        = $row['usr_fax'];
                    $this->birthday   = $row['usr_birthday'];
                    $this->gender         = $row['usr_gender'];
                    $this->email          = $row['usr_email'];
                    $this->homepage       = $row['usr_homepage'];
                    $this->login_name     = $row['usr_login_name'];
                    $this->password       = $row['usr_password'];
                    $this->last_login     = $row['usr_last_login'];
                    $this->actual_login   = $row['usr_actual_login'];
                    $this->number_login   = $row['usr_number_login'];
                    $this->date_invalid   = $row['usr_date_invalid'];
                    $this->number_invalid = $row['usr_number_invalid'];
                    $this->last_change    = $row['usr_last_change'];
                    $this->usr_id_change  = $row['usr_usr_id_change'];
                    $this->valid          = $row['usr_valid'];
                    $this->reg_org_shortname = $row->usr_reg_org_shortname;
                }
                
                $field_usd_value = "usd_value";
                $join_user_data  = "LEFT JOIN ". TBL_USER_DATA. "
                                      ON usd_usf_id = usf_id
                                     AND usd_usr_id = $user_id";
                $this->db_fields_changed = false;
            }
            else
            {
                $field_usd_value = "NULL as usd_value";
                $join_user_data  = "";
            }
            
            // Daten aus adm_user_data auslesen
            $sql = "SELECT usf_id, usf_cat_id, cat_name, usf_name, usf_type, usf_description, 
                           usf_disabled, usf_hidden, usf_mandatory, usf_system, $field_usd_value
                      FROM ". TBL_CATEGORIES. ", ". TBL_USER_FIELDS. "
                           $join_user_data
                     WHERE usf_cat_id = cat_id 
                       AND (  cat_org_id IS NULL
                           OR cat_org_id  = $g_current_organization->id )
                     ORDER BY cat_sequence, usf_sequence";
            $result_usf = mysql_query($sql, $this->db_connection);
            db_error($result_usf,__FILE__,__LINE__);
            error_log($sql);
            
            while($row_usf = mysql_fetch_array($result_usf))
            {
                // ein mehrdimensionales Array aufbauen, welche fuer jedes usf-Feld alle 
                // Daten des Sql-Statements beinhaltet
                for($i = 0; $i < mysql_num_fields($result_usf); $i++)
                {
                    $this->db_user_fields[$row_usf['usf_name']][mysql_field_name($result_usf, $i)] = $row_usf[$i];
                }
                // Flag, ob der Inhalt geaendert wurde, um das Update effektiver zu gestalten
                $this->db_user_fields[$row_usf['usf_name']]['changed'] = false;
                // Flag, welches angibt, ob der Wert neu hinzugefuegt wurde
                if(is_null($row_usf['usd_value']))
                {
                    $this->db_user_fields[$row_usf['usf_name']]['new'] = true;
                }
                else
                {
                    $this->db_user_fields[$row_usf['usf_name']]['new'] = false;
                }
            }
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
        $this->birthday       = NULL;
        $this->gender         = "";
        $this->email          = "";
        $this->homepage       = "";
        $this->login_name     = NULL;
        $this->password       = NULL;
        $this->last_login     = NULL;
        $this->actual_login   = NULL;
        $this->number_login   = 0;
        $this->date_invalid   = NULL;
        $this->number_invalid = 0;
        $this->last_change    = NULL;
        $this->usr_id_change  = 0;
        $this->valid          = 1;
        $this->reg_org_shortname = NULL;
        
        $this->webmaster = -1;

        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                if($key == "usr_valid")
                {
                    $this->db_fields[$key] = "1";
                }
                else
                {
                    $this->db_fields[$key] = null;
                }
            }

            foreach($this->db_user_fields as $key => $value)
            {
                $this->db_user_fields[$key] = null;
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_USERS;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                if($row['Field'] == "usr_valid")
                {
                    $this->db_fields[$row['Field']] = "1";
                }
                else
                {
                    $this->db_fields[$row['Field']] = null;
                }
            }
        }
        
        // User Rechte vorbelegen
        $this->clearRights();
    }

    // alle Rechtevariablen wieder zuruecksetzen
    function clearRights()
    {
        $this->assignRolesRight   = -1;
        $this->editProfile        = -1;
        $this->editUser           = -1;
        $this->editGuestbookRight = -1;
        $this->commentGuestbookRight = -1;
        $this->editWeblinksRight  = -1;
        $this->editDownloadRight  = -1;
        $this->editPhotoRight     = -1;
    }

    // Funktion setzt den Wert eines Profilfeldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {        
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        $field_name  = stripSlashes($field_name);
        $field_value = stripSlashes($field_value);
        
        if(strlen($field_value) == 0)
        {
            $field_value = null;
        }
        
        // Plausibilitaetspruefungen
        switch($field_name)
        {
            case "usr_id":
            case "usr_usr_id_change":
                if(is_numeric($field_value) == false 
                || $field_value == 0)
                {
                    $field_value = null;
                }                
                break;

            case "usr_id_number_login":
            case "usr_id_number_invalid":
                if(is_numeric($field_value) == false)
                {
                    $field_value = null;
                }
                break;
        }

        if(strpos($field_name, "usr_") === 0)
        {
            // Daten fuer User-Tabelle
            if($field_value != $this->db_fields[$field_name])
            {
                $this->db_fields[$field_name] = $field_value;
                $this->db_fields_changed = true;
            }
        }
        else
        {
            // Daten fuer User-Fields-Tabelle
            if($field_value != $this->db_user_fields[$field_name]['usd_value'])
            {
                if(is_null($this->db_user_fields[$field_name]['usd_value']))
                {
                    $this->db_user_fields[$field_name]['new'] = true;
                }
                else
                {
                    $this->db_user_fields[$field_name]['new'] = false;
                }
                $this->db_user_fields[$field_name]['usd_value'] = $field_value;
                $this->db_user_fields[$field_name]['changed']   = true;
            }
        }
    }
    
    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        if(strpos($field_name, "usr_") === 0)
        {
            return $this->db_fields[$field_name];
        }
        else
        {
            return $this->getProperty($field_name, "usd_value");
        }
    }    

    // Funktion gibt den Wert eines Profilfeldes zurueck
    // Property ist dabei ein Feldname aus der Tabelle adm_user_fields oder adm_user_data
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getProperty($field_name, $property)
    {
        return $this->db_user_fields[$field_name][$property];
    }    
    
    // aktuelle Userdaten in der Datenbank updaten
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann
    function update($login_user_id, $set_change_date = true)
    {
        if($this->id > 0 && $login_user_id > 0 && is_numeric($login_user_id))
        {
            if($set_change_date)
            {
                $this->last_change = date("Y-m-d H:i:s", time());
                $login_user_id     = $this->usr_id_change;
            }
            
            // PLZ darf nicht ueber prepareSQL geprueft werden, 
            // da sonst fuehrende Nullen entfernt wuerden
            $this->zip_code = mysql_escape_string(stripslashes($this->zip_code));

            $sql = "UPDATE ". TBL_USERS. " SET usr_last_name  = {0}
                                             , usr_first_name = {1}
                                             , usr_address    = {2}
                                             , usr_zip_code   = '$this->zip_code'
                                             , usr_city       = {3}
                                             , usr_country    = {4}
                                             , usr_phone      = {5}
                                             , usr_mobile     = {6}
                                             , usr_fax        = {7}
                                             , usr_birthday   = {8}
                                             , usr_gender     = {9}
                                             , usr_email      = {10}
                                             , usr_homepage   = {11}
                                             , usr_last_login = {12}
                                             , usr_actual_login   = {13}
                                             , usr_number_login   = {14}
                                             , usr_date_invalid   = {15}
                                             , usr_number_invalid = {16}
                                             , usr_last_change    = {17}
                                             , usr_usr_id_change  = $login_user_id
                                             , usr_valid          = {18}
                                             , usr_reg_org_shortname = {19}
                                             , usr_login_name     = {20}
                                             , usr_password       = {21}
                     WHERE usr_id = $this->id ";
            $sql = prepareSQL($sql, array($this->last_name, $this->first_name, $this->address,
                        $this->city, $this->country, $this->phone, $this->mobile, $this->fax, $this->birthday,
                        $this->gender, $this->email, $this->homepage, $this->last_login, $this->actual_login,
                        $this->number_login, $this->date_invalid, $this->number_invalid, $this->last_change,
                        $this->valid, $this->reg_org_shortname, $this->login_name, $this->password));
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            return 0;
        }
        return -1;
    }

    // aktuelle Userdaten neu in der Datenbank schreiben
    // Es muss die ID des eingeloggten Users uebergeben werden,
    // damit die Aenderung protokolliert werden kann (Ausnahme bei Registrierung)
    function insert($login_user_id)
    {
        if($this->id == 0  && is_numeric($login_user_id)    // neuer angelegter User
        && ($login_user_id >= 0 || $this->valid == 0))       // neuer registrierter User
        {
            $act_date = date("Y-m-d H:i:s", time());

            // PLZ darf nicht ueber prepareSQL geprueft werden, 
            // da sonst fuehrende Nullen entfernt wuerden
            $this->zip_code = mysql_escape_string(stripslashes($this->zip_code));

            $sql = "INSERT INTO ". TBL_USERS. " (usr_last_name, usr_first_name, usr_address, usr_zip_code,
                                  usr_city, usr_country, usr_phone, usr_mobile, usr_fax, usr_birthday,
                                  usr_gender, usr_email, usr_homepage, usr_last_login, usr_actual_login,
                                  usr_number_login, usr_date_invalid, usr_number_invalid, usr_last_change,
                                  usr_valid, usr_reg_org_shortname, usr_login_name, usr_password, usr_usr_id_change )
                         VALUES ({0}, {1}, {2}, '$this->zip_code', {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10}, {11}, NULL, NULL,
                                 0,  NULL, 0, '$act_date', {12}, {13}, {14}, {15}";
            // bei einer Registrierung ist die Login-User-Id nicht gefüllt
            if($login_user_id == 0)
            {
                $sql = $sql. ", NULL )";
            }
            else
            {
                $sql = $sql. ", $login_user_id )";
            }
            
            $sql = prepareSQL($sql, array($this->last_name, $this->first_name, $this->address, 
                        $this->city, $this->country, $this->phone, $this->mobile, $this->fax, $this->birthday,
                        $this->gender, $this->email, $this->homepage, $this->valid,
                        $this->reg_org_shortname, $this->login_name, $this->password));                        
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $this->id = mysql_insert_id($this->db_connection);
            return 0;
        }
        return -1;
    }
    
    // die Funktion speichert die Userdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save($login_user_id, $set_change_date = true)
    {
        if(is_numeric($login_user_id) 
        && (is_numeric($this->db_fields['usr_id']) || is_null($this->db_fields['usr_id'])))
        {
            if($set_change_date)
            {
                $this->db_fields['usr_last_change']   = date("Y-m-d H:i:s", time());
                $this->db_fields['usr_usr_id_change'] = $login_user_id;
            }
            
            if($this->db_fields_changed || is_null($this->db_fields['usr_id']))
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "usr_id" && strpos($key, "usr_") === 0) 
                    {
                        if($this->db_fields['usr_id'] == 0)
                        {
                            if(strlen($value) > 0)
                            {
                                // Daten fuer ein Insert aufbereiten
                                $sql_field_list = $sql_field_list. " $item_connection $key ";
                                if(is_numeric($value))
                                {
                                    $sql_value_list = $sql_value_list. " $item_connection $value ";
                                }
                                else
                                {
                                    $value = addSlashes($value);
                                    $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                                }
                            }
                        }
                        else
                        {
                            // Daten fuer ein Update aufbereiten
                            if(strlen($value) == 0 || is_null($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = NULL ";
                            }
                            elseif(is_numeric($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = $value ";
                            }
                            else
                            {
                                $value = addSlashes($value);
                                $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ",";
                        }
                    }
                }

                if($this->db_fields['usr_id'] == 0)
                {
                    $sql = "INSERT INTO ". TBL_USERS. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['usr_id'] = mysql_insert_id($this->db_connection);
                }
                else
                {
                    $sql = "UPDATE ". TBL_USERS. " SET $sql_field_list 
                             WHERE usr_id = ". $this->db_fields['usr_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
            }
            else
            {
                // Daten der User-Tabelle wurde nicht geaendert, dann nur Fingerabdruck aktualisieren
                $sql = "UPDATE ". TBL_USERS. " SET usr_last_change   = '". $this->db_fields['usr_last_change']. "'
                                                 , usr_usr_id_change = ". $this->db_fields['usr_usr_id_change']. "
                         WHERE usr_id = ". $this->db_fields['usr_id'];
                error_log($sql);                         
                $result = mysql_query($sql, $this->db_connection);
                db_error($result,__FILE__,__LINE__);
            }

            
            // nun noch Updates fuer alle geaenderten User-Fields machen
            foreach($this->db_user_fields as $key => $value)
            {
                if($value['changed'] == true)
                {
                    $item_connection = "";
                    $sql_field_list  = "";
                    
                    if(is_null($value['usd_value']))
                    {
                        $sql = "DELETE FROM ". TBL_USER_DATA. " 
                                 WHERE usd_usr_id = ". $this->db_fields['usr_id']. "
                                   AND usd_usf_id = ". $value['usf_id'];
                    }
                    else
                    {
                        if($value['new'] == true)
                        {
                            $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value) 
                                    VALUES (". $this->db_fields['usr_id']. ", ". $value['usf_id']. ", '". $value['usd_value']. "') ";
                        }
                        else
                        {
                            $sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = '". $value['usd_value']. "'
                                     WHERE usd_usr_id = ". $this->db_fields['usr_id']. "
                                       AND usd_usf_id = ". $value['usf_id'];
                        }
                    }
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
            }
            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }

    // aktuellen Benutzer loeschen
    function delete()
    {
        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id = NULL
                    WHERE ann_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id_change = NULL
                    WHERE ann_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id = NULL
                    WHERE dat_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id_change = NULL
                    WHERE dat_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_FOLDERS. " SET fol_usr_id = NULL
                    WHERE fol_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_FILES. " SET fil_usr_id = NULL
                    WHERE fil_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id = NULL
                    WHERE gbo_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_LINKS. " SET lnk_usr_id = NULL
                    WHERE lnk_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id = NULL
                    WHERE pho_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_change = NULL
                    WHERE pho_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_ROLES. " SET rol_usr_id_change = NULL
                    WHERE rol_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_ROLE_DEPENDENCIES. " SET rld_usr_id = NULL
                    WHERE rld_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "UPDATE ". TBL_USERS. " SET usr_usr_id_change = NULL
                    WHERE usr_usr_id_change = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_MEMBERS. " WHERE mem_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_USER_DATA. " WHERE usd_usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $sql    = "DELETE FROM ". TBL_USERS. "
                    WHERE usr_id = ". $this->db_fields['usr_id'];
        $result = mysql_query($sql, $this->db_connection);
        db_error($result,__FILE__,__LINE__);

        $this->clear();
    }

    // gibt die Userdaten als VCard zurueck
    function getVCard()
    {
        $vcard  = (string) "BEGIN:VCARD\r\n";
        $vcard .= (string) "VERSION:2.1\r\n";
        $vcard .= (string) "N:" . $this->getValue("Nachname"). ";". $this->getValue("Vorname") . ";;;\r\n";
        $vcard .= (string) "FN:". $this->getValue("Vorname") . " ". $this->getValue("Nachname") . "\r\n";
        if (strlen($this->getValue("usr_login_name")) > 0)
        {
            $vcard .= (string) "NICKNAME:" . $this->getValue("usr_login_name"). "\r\n";
        }
        if (strlen($this->getValue("Telefon")) > 0)
        {
            $vcard .= (string) "TEL;HOME;VOICE:" . $this->getValue("Telefon"). "\r\n";
        }
        if (strlen($this->getValue("Handy")) > 0)
        {
            $vcard .= (string) "TEL;CELL;VOICE:" . $this->getValue("Handy"). "\r\n";
        }
        if (strlen($this->getValue("Fax")) > 0)
        {
            $vcard .= (string) "TEL;HOME;FAX:" . $this->getValue("Fax"). "\r\n";
        }
        $vcard .= (string) "ADR;HOME:;;" . $this->getValue("Adresse"). ";" . $this->getValue("Ort"). ";;" . $this->getValue("PLZ"). ";" . $this->getValue("Land"). "\r\n";
        if (strlen($this->getValue("Homepage")) > 0)
        {
            $vcard .= (string) "URL;HOME:" . $this->getValue("Homepage"). "\r\n";
        }
        if (strlen($this->getValue("Geburtstag")) > 0)
        {
            $vcard .= (string) "BDAY:" . mysqldatetime("ymd", $this->getValue("Geburtstag")) . "\r\n";
        }
        if (strlen($this->getValue("E-Mail")) > 0)
        {
            $vcard .= (string) "EMAIL;PREF;INTERNET:" . $this->getValue("E-Mail"). "\r\n";
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($this->getValue("Geschlecht") > 0)
        {
            if($this->getValue("Geschlecht") == 1)
            {
                $wab_gender = 2;
            }
            else
            {
                $wab_gender = 1;
            }
            $vcard .= (string) "X-WAB-GENDER:" . $wab_gender . "\r\n";
        }
        if (strlen($this->getValue("usr_last_change")) > 0)
        {
            $vcard .= (string) "REV:" . mysqldatetime("ymdThis", $this->getValue("usr_last_change")) . "\r\n";
        }

        $vcard .= (string) "END:VCARD\r\n";
        return $vcard;
    }

    // Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
    function assignRoles()
    {
        if(-1 == $this->assignRolesRight && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id       = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id       = rol_id
                          AND mem_valid        = 1
                          AND rol_assign_roles = 1
                          AND rol_valid        = 1 
                          AND rol_cat_id       = cat_id
                          AND cat_org_id       = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $assign_roles = mysql_num_rows($result);

            if ( $assign_roles > 0 )
            {
                $this->assignRolesRight = 1;
            }
            else
            {
                $this->assignRolesRight = 0;
            }
        }

        if (1 == $this->assignRolesRight)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User das entsprechende Profil bearbeiten darf
    function editProfile($profileID = NULL)
    {
        if($profileID == NULL)
        {
            $profileID = $this->id;
        }

        //soll das eigene Profil bearbeitet werden?
        if($profileID == $this->id && $this->db_fields['usr_id'] > 0)
        {
            // Pruefen ob die Datenbank schon abgefragt wurde, wenn nicht dann Recht auslesen
            if($this->editProfile == -1)
            {
                global $g_current_organization;
                
                $sql    =  "SELECT *
                              FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                             WHERE mem_usr_id  = ". $this->db_fields['usr_id']. "
                               AND mem_rol_id  = rol_id
                               AND mem_valid   = 1
                               AND rol_profile = 1
                               AND rol_valid   = 1 
                               AND rol_cat_id  = cat_id
                               AND cat_org_id  = $g_current_organization->id ";
                $result = mysql_query($sql, $this->db_connection);
                db_error($result,__FILE__,__LINE__);

                $found_rows = mysql_num_rows($result);

                if($found_rows >= 1)
                {
                    $this->editProfile = 1;
                }
                else
                {
                    $this->editProfile = 0;
                }
            }

            if($this->editProfile == 1)
            {
                return true;
            }
            else
            {
                return $this->editUser();
            }

        }
        else
        {
            return $this->editUser();
        }
    }

    // Funktion prueft, ob der angemeldete User fremde Benutzerdaten bearbeiten darf

    function editUser()
    {
        global $g_current_organization;

        // prüfen ob die Userrechte schon aus der Datenbank geholt wurden
        if($this->editUser == -1 && $this->db_fields['usr_id'] > 0)
        {
            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id    = ". $this->db_fields['usr_id']. "
                          AND mem_valid     = 1
                          AND mem_rol_id    = rol_id
                          AND rol_edit_user = 1
                          AND rol_valid     = 1 
                          AND rol_cat_id    = cat_id
                          AND cat_org_id    = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $found_rows = mysql_num_rows($result);

            if($found_rows >= 1)
            {
                $this->editUser = 1;
            }
            else
            {
                $this->editUser = 0;
            }
        }

        if($this->editUser == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege kommentieren darf
    function commentGuestbookRight()
    {
         if($this->commentGuestbookRight == -1 && $this->db_fields['usr_id'] > 0)
         {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id             = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id             = rol_id
                          AND mem_valid              = 1
                          AND rol_guestbook_comments = 1
                          AND rol_valid              = 1 
                          AND rol_cat_id             = cat_id
                          AND cat_org_id             = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $edit_user = mysql_num_rows($result);

            if ( $edit_user > 0 )
            {
                $this->commentGuestbookRight = 1;
            }
            else
            {
                $this->commentGuestbookRight = 0;
            }
        }

        if($this->commentGuestbookRight == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege loeschen und editieren darf
    function editGuestbookRight()
    {
        if($this->editGuestbookRight == -1 && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id    = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id    = rol_id
                          AND mem_valid     = 1
                          AND rol_guestbook = 1
                          AND rol_valid     = 1 
                          AND rol_cat_id    = cat_id
                          AND cat_org_id    = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $edit_user = mysql_num_rows($result);

            if ( $edit_user > 0 )
            {
                $this->editGuestbookRight = 1;
            }
            else
            {
                $this->editGuestbookRight = 0;
            }
        }

        if ( $this->editGuestbookRight == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
    function editWeblinksRight()
    {
        if(-1 == $this->editWeblinksRight && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id   = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id   = rol_id
                          AND mem_valid    = 1
                          AND rol_weblinks = 1
                          AND rol_valid    = 1 
                          AND rol_cat_id   = cat_id
                          AND cat_org_id   = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $edit_weblinks = mysql_num_rows($result);

            if ( $edit_weblinks > 0 )
            {
                $this->editWeblinksRight = 1;
            }
            else
            {
                $this->editWeblinksRight = 0;
            }
        }

        if (1 == $this->editWeblinksRight)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf

    function editDownloadRight()
    {
        if(-1 == $this->editDownloadRight && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id   = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id   = rol_id
                          AND mem_valid    = 1
                          AND rol_download = 1
                          AND rol_valid    = 1 
                          AND rol_cat_id   = cat_id
                          AND cat_org_id   = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            $edit_download = mysql_num_rows($result);

            if($edit_download > 0)
            {
                $this->editDownloadRight = 1;
            }
            else
            {
                $this->editDownloadRight = 0;
            }
        }

        if (1 == $this->editDownloadRight)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf
    
    function editPhotoRight()
    {
        if(-1 == $this->editPhotoRight && $this->db_fields['usr_id'] > 0)
        {       
            global $g_current_organization;
            
            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id = rol_id
                          AND mem_valid  = 1
                          AND rol_photo  = 1
                          AND rol_valid  = 1 
                          AND rol_cat_id = cat_id
                          AND cat_org_id = $g_current_organization->id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
        
            $edit_photo = mysql_num_rows($result);
    
            if($edit_photo > 0)
            {
                $this->editPhotoRight = 1;
            }
            else
            {
                $this->editPhotoRight = 0;
            }
        }

        if (1 == $this->editPhotoRight)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function isWebmaster()
    {
        if($this->webmaster == -1 && $this->db_fields['usr_id'] > 0)
        {
            // Status wurde noch nicht ausgelesen
            global $g_current_organization;
            
            $sql    = "SELECT rol_id
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id = ". $this->db_fields['usr_id']. "
                          AND mem_valid  = 1
                          AND mem_rol_id = rol_id
                          AND rol_name   = 'Webmaster'
                          AND rol_valid  = 1 
                          AND rol_cat_id = cat_id
                          AND cat_org_id = $g_current_organization->id ";
            error_log($sql);
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);            
            
            if(mysql_num_rows($result) > 0)
            {
                $this->webmaster = 1;
            }
            else
            {
                $this->webmaster = 0;
            }
        }
        
        if ($this->webmaster == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
?>