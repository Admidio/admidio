<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $user = new User($g_db);
 *
 * Mit der Funktion getUser($user_id) kann nun der gewuenschte User ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * getUser($user_id)    - ermittelt die Daten des uebergebenen Benutzers
 * clear()              - Die Klassenvariablen werden neu initialisiert
 * setValue($field_name, $field_value) 
 *                      - setzt einen Wert fuer ein bestimmtes Feld
 *                        der adm_user oder der adm_user_fields Tabelle
 * getValue($field_name)- gibt den Wert eines Feldes aus adm_user oder der 
 *                        adm_user_fields zurueck
 * getProperty($field_name, $property) 
 *                      - gibt den Inhalt einer Eigenschaft eines Feldes zurueck.
 *                        Dies kann die usf_id, usf_type, cat_id, cat_name usw. sein
 * save($set_change_date = true) 
 *                      - User wird mit den geaenderten Daten in die Datenbank
 *                        zurueckgeschrieben bwz. angelegt
 * delete()             - Der gewaehlte User wird aus der Datenbank geloescht
 * getVCard()           - Es wird eine vCard des Users als String zurueckgegeben
 * isWebmaster()        - gibt true/false zurueck, falls der User Mitglied der 
 *                        Rolle "Webmaster" ist
 * viewProfile			- Ueberprueft ob der User das Profil eines uebrgebenen
 * 						  Users einsehen darf
 * viewRole				- Ueberprueft ob der User eine Uebergebene Rolle(Liste)
 * 						  einsehen darf
 * vielAllRolese		- Ueberprueft ob der User das Recht besitzt alle Rolle
 * 						  (Listen) einsehen darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class User extends TableAccess
{
    var $webmaster;
    var $b_set_last_change;         // Kennzeichen, ob User und Zeitstempel der aktuellen Aenderung gespeichert werden sollen
    
    var $db_user_fields = array();  // Array ueber alle Felder der User-Fields-Tabelle des entsprechenden Users
    var $roles_rights   = array();  // Array ueber alle Rollenrechte mit dem entsprechenden Status des Users
	var $list_view_rights   = array();  // Array ueber Listenrechte einzelner Rollen
    
    // Konstruktor
    function User(&$db, $user_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USERS;
        $this->column_praefix = "usr";
        
        if(strlen($user_id) > 0)
        {
            $this->getUser($user_id);
        }
        else
        {
            $this->clear();
        }
    }

    // User mit der uebergebenen ID aus der Datenbank auslesen
    function getUser($user_id)
    {
        $this->readData($user_id);
                    
        // user_data-Array aufbauen
        $this->fillUserFieldArray($user_id);
    }
    
    function fillUserFieldArray($user_id = 0)
    {
        global $g_current_organization;
        
        if(is_numeric($user_id) && $user_id > 0)
        {        
            $field_usd_value = "usd_value";
            $join_user_data  = "LEFT JOIN ". TBL_USER_DATA. "
                                  ON usd_usf_id = usf_id
                                 AND usd_usr_id = $user_id";
        }
        else
        {
            $field_usd_value = "NULL as usd_value";
            $join_user_data  = "";
        }
        
        // Daten aus adm_user_data auslesen
        $sql = "SELECT usf_id, cat_id, cat_name, usf_name, usf_type, usf_description, 
                       usf_disabled, usf_hidden, usf_mandatory, usf_system, $field_usd_value
                  FROM ". TBL_CATEGORIES. ", ". TBL_USER_FIELDS. "
                       $join_user_data
                 WHERE usf_cat_id = cat_id 
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = ". $g_current_organization->getValue("org_id"). " )
                 ORDER BY cat_sequence, usf_sequence";
        $result_usf = $this->db->query($sql);

        while($row_usf = $this->db->fetch_array($result_usf))
        {
            // ein mehrdimensionales Array aufbauen, welche fuer jedes usf-Feld alle 
            // Daten des Sql-Statements beinhaltet
            for($i = 0; $i < $this->db->num_fields($result_usf); $i++)
            {
                $this->db_user_fields[$row_usf['usf_name']][$this->db->field_name($result_usf, $i)] = $row_usf[$i];
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

    // alle Klassenvariablen wieder zuruecksetzen
    // die Methode wird innerhalb von clear() aufgerufen
    function _clear()
    {
        $this->webmaster = -1;
        $this->b_set_last_change = true;
        
        // neue User sollten i.d.R. auf valid stehen (Ausnahme Registrierung)
        $this->setValue("usr_valid", 1);
        
        // user_data-Array initialisieren
        if(count($this->db_user_fields) > 0)
        {
            foreach($this->db_user_fields as $key => $value)
            {
                $this->db_user_fields[$key]['usd_value'] = "";
                $this->db_user_fields[$key]['new']       = true;
                $this->db_user_fields[$key]['changed']   = false;
            }
        }
        else
        {
            // user_data-Array aufbauen
            $this->fillUserFieldArray();
        }
        
        // User Rechte vorbelegen
        $this->clearRights();
    }

    // alle Rechtevariablen wieder zuruecksetzen
    function clearRights()
    {
        // die Array-Keys muessen genauso wie die DB-Spalten heissen
        $this->roles_rights['rol_announcements'] = -1;
        $this->roles_rights['rol_approve_users'] = -1;
        $this->roles_rights['rol_assign_roles']  = -1;
        $this->roles_rights['rol_dates']         = -1;
        $this->roles_rights['rol_download']      = -1;
        $this->roles_rights['rol_edit_user']     = -1;
        $this->roles_rights['rol_guestbook']     = -1;
        $this->roles_rights['rol_guestbook_comments'] = -1;
        $this->roles_rights['rol_photo']         = -1;
        $this->roles_rights['rol_profile']       = -1;
        $this->roles_rights['rol_weblinks']      = -1;
        $this->roles_rights['rol_all_lists_view']= -1;
    }

    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Methode wird innerhalb von setValue() aufgerufen
    function _setValue($field_name, $field_value)
    {        
        if(strpos($field_name, "usr_") !== 0)
        {
            // Daten fuer User-Fields-Tabelle
            if($field_value != $this->db_user_fields[$field_name]['usd_value'])
            {
                if(strlen($this->db_user_fields[$field_name]['usd_value']) == 0)
                {
                    $this->db_user_fields[$field_name]['new'] = true;
                }
                else
                {
                    $this->db_user_fields[$field_name]['new'] = false;
                }
                
                // Homepage noch mit http vorbelegen
                if($this->getProperty($field_name, "usf_type") == "URL")
                {
                    if(substr_count(strtolower($field_value), "http://")  == 0
                    || substr_count(strtolower($field_value), "https://") == 0 )
                    {
                        $field_value = "http://". $field_value;
                    }
                }
                $this->db_user_fields[$field_name]['usd_value'] = $field_value;
                $this->db_user_fields[$field_name]['changed']   = true;
            }
        }
    }
    
    // Methode prueft, ob evtl. ein Wert aus der User-Fields-Tabelle
    // angefordert wurde und gibt diesen zurueck
    // die Funktion wird innerhalb von getValue() aufgerufen
    function _getValue($field_name)
    {
        if(strpos($field_name, "usr_") === 0)
        {
            $value = $this->db_fields[$field_name];
        }
        else
        {
            $value = $this->getProperty($field_name, "usd_value");
        }
        return $value;
    }    

    // Methode gibt den Wert eines Profilfeldes zurueck
    // Property ist dabei ein Feldname aus der Tabelle adm_user_fields oder adm_user_data
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getProperty($field_name, $property)
    {
        return $this->db_user_fields[$field_name][$property];
    }    
    
    // die Funktion speichert die Userdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function _save()
    {
        if($this->b_set_last_change)
        {
            global $g_current_user;
            $this->setValue("usr_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("usr_usr_id_change", $g_current_user->getValue("usr_id"));
        }

        $this->b_set_last_change = true;
    }
    
    // Methode wird erst nach dem Speichern des Users aufgerufen und speichert
    // alle Profilfelder aus adm_user_fields
    function _afterSave()
    {
        // nun noch Updates fuer alle geaenderten User-Fields machen
        foreach($this->db_user_fields as $key => $value)
        {
            if($value['changed'] == true)
            {
                $item_connection = "";
                $sql_field_list  = "";                

                if(strlen($value['usd_value']) == 0)
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
                        $this->db_user_fields[$key]['new'] = false;
                    }
                    else
                    {
                        $sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = '". $value['usd_value']. "'
                                 WHERE usd_usr_id = ". $this->db_fields['usr_id']. "
                                   AND usd_usf_id = ". $value['usf_id'];
                    }
                }
                $result = $this->db->query($sql);
                $this->db_user_fields[$key]['changed'] = false;
            }
        }
    }

    // Referenzen zum aktuellen Benutzer loeschen
    // die Methode wird innerhalb von delete() aufgerufen
    function _delete()
    {
        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id = NULL
                    WHERE ann_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_usr_id_change = NULL
                    WHERE ann_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id = NULL
                    WHERE dat_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_DATES. " SET dat_usr_id_change = NULL
                    WHERE dat_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_FOLDERS. " SET fol_usr_id = NULL
                    WHERE fol_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_FILES. " SET fil_usr_id = NULL
                    WHERE fil_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id = NULL
                    WHERE gbo_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_GUESTBOOK. " SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_LINKS. " SET lnk_usr_id = NULL
                    WHERE lnk_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id = NULL
                    WHERE pho_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_change = NULL
                    WHERE pho_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ROLES. " SET rol_usr_id_change = NULL
                    WHERE rol_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_ROLE_DEPENDENCIES. " SET rld_usr_id = NULL
                    WHERE rld_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "UPDATE ". TBL_USERS. " SET usr_usr_id_change = NULL
                    WHERE usr_usr_id_change = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_MEMBERS. " WHERE mem_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);
        
        $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);

        $sql    = "DELETE FROM ". TBL_USER_DATA. " WHERE usd_usr_id = ". $this->db_fields['usr_id'];
        $this->db->query($sql);
        
        return true;
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
        if (strlen($this->getValue("usr_photo")) > 0)
        {
            $vcard .= (string) "PHOTO;ENCODING=BASE64;TYPE=JPEG:".base64_encode($this->getValue("usr_photo")). "\r\n";
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
    
    // Funktion prueft, ob der User das uebergebene Rollenrecht besitzt
    function checkRolesRight($right)
    {
		if($this->roles_rights[$right] == -1 && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;

            $sql    = "SELECT *
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id = ". $this->db_fields['usr_id']. "
                          AND mem_rol_id = rol_id
                          AND mem_valid  = 1
                          AND $right     = 1
                          AND rol_valid  = 1 
                          AND rol_cat_id = cat_id
                          AND cat_org_id = ". $g_current_organization->getValue("org_id");
            $this->db->query($sql);
            $num_rows = $this->db->num_rows();

            if($num_rows > 0)
            {
                $this->roles_rights[$right] = 1;
            }
            else
            {
                $this->roles_rights[$right] = 0;
            }
        }

        if ($this->roles_rights[$right] == 1)
        {
            return true;
        }
        else
        {
            return false;
        }
    }    

    // Funktion prueft, ob der angemeldete User Ankuendigungen anlegen und bearbeiten darf
    function editAnnouncements()
    {
        return $this->checkRolesRight('rol_announcements');
    }

    // Funktion prueft, ob der angemeldete User Registrierungen bearbeiten und zuordnen darf
    function approveUsers()
    {
        return $this->checkRolesRight('rol_approve_users');
    }

    // Funktion prueft, ob der angemeldete User Rollen zuordnen, anlegen und bearbeiten darf
    function assignRoles()
    {
        return $this->checkRolesRight('rol_assign_roles');
    }
    
    // Funktion prueft, ob der angemeldete User Termine anlegen und bearbeiten darf
    function editDates()
    {
        return $this->checkRolesRight('rol_dates');
    }
    
    // Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf
    function editDownloadRight()
    {
        return $this->checkRolesRight('rol_download');
    }
    
    // Funktion prueft, ob der angemeldete User das entsprechende Profil bearbeiten darf
    function editProfile($profileID = NULL)
    {
        if($profileID == NULL)
        {
            $profileID = $this->db_fields['usr_id'];
        }

        //soll das eigene Profil bearbeitet werden?
        if($profileID == $this->db_fields['usr_id'] && $this->db_fields['usr_id'] > 0)
        {
            $edit_profile = $this->checkRolesRight('rol_profile');

            if($edit_profile == 1)
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
        return $this->checkRolesRight('rol_edit_user');
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege loeschen und editieren darf
    function editGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook');
    }
    
    // Funktion prueft, ob der angemeldete User Gaestebucheintraege kommentieren darf
    function commentGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook_comments');
    }
    
    // Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf    
    function editPhotoRight()
    {
        return $this->checkRolesRight('rol_photo');
    }

    // Funktion prueft, ob der User ein Profil einsehen darf    
    function viewProfile($usr_id)
    {
		//Hat ein User Profileedit rechte, darf er es nauerlich auch sehen
		if($this->editProfile($usr_id))
		{
			$view_profile = true;
		}
		else //ist das nicht der Fall, alle Rollen des uebergeben Users aufrufen, und fuer diese die Funktion viewRole aufrufen
		{
		 	$view_profile = false;
			global $g_current_organization;
            
            $sql    = "SELECT rol_id
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id = ".$usr_id. "
                          AND mem_valid  = 1
                          AND mem_rol_id = rol_id
                          AND rol_valid  = 1 
                          AND rol_cat_id = cat_id
                          AND cat_org_id = ". $g_current_organization->getValue("org_id");
            $this->db->query($sql);
			
            if($this->db->num_rows() > 0)
            {             
				while($check_role = $this->db->fetch_array())
                {
					//Rollenrechte fur Rolle ueberpruefen
					if($this->viewRole($check_role[0]))
					{
						$view_profile = true;
					}
				}
            }
            else
            {
                $view_profile = false;
            }
		}
		return $view_profile;
    }
    
    //Ueberprueft ob der User das Recht besitzt alle Rolle (Listen) einsehen darf 
    function viewAllRoles()
    {
    	if($this->roles_rights['rol_all_lists_view'] == -1 && $this->db_fields['usr_id'] > 0)
        {
            global $g_current_organization;
            
            $sql    = "SELECT rol_id
                         FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                        WHERE mem_usr_id = ". $this->db_fields['usr_id']. "
                          AND mem_valid  = 1
                          AND mem_rol_id = rol_id
						  AND rol_all_lists_view  = 1
                          AND rol_valid  = 1 
                          AND rol_cat_id = cat_id
                          AND cat_org_id = ". $g_current_organization->getValue("org_id");
            $this->db->query($sql);           
            
            if($this->db->num_rows() > 0)
            {
                $this->roles_rights['rol_all_lists_view'] = 1;
            }
            else
            {
                $this->roles_rights['rol_all_lists_view'] = 0;
            }
        }
        //Falls er das Recht hat alle Listen einzusehen true zurueckgeben
        if ($this->roles_rights['rol_all_lists_view'] == 1)
        {
            return true;
        } 
 		else
 		{
			return false;
 		}   
    }
    
    // Funktion prueft, ob der angemeldete User eine bestimmte oder alle Listen einsehen darf    
    function viewRole($rol_id)
    {
    	$view_role = false;
		//Zunaechst abfrage ob der User durch irgendeine Rolle das Recht bekommt alle Listen einzusehen
		if($this->viewAllRoles())
		{
			$view_role = true;
		}
        else  //Falls er das Recht nicht hat Kontrolle fuer eine bestimmte Rolle
        {
            //nachschauen ob Wert fuer Rolle schon ins Array geschrieben wurde
            if(array_key_exists  ($rol_id, $this->list_view_rights))
            {
				//Falls ja sehen ob Recht besteht und entsprechenden Wert zuückgeben
				if($this->list_view_rights[$rol_id] == 1)
				{
					$view_role = true;
				}
            }
            else
            {
	            //Falls noch nicht im Array erfasst -> SQL-Abfrage ueber alle Rollen
	            global $g_current_organization;
	            $sql    = "SELECT rol_id, rol_this_list_view
	                         FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
	                        WHERE rol_valid  = 1 
	                          AND rol_cat_id = cat_id
	                          AND cat_org_id = ". $g_current_organization->getValue("org_id");
	            $result = $this->db->query($sql);
	            
	            while($row = $this->db->fetch_array($result))
	            {
	            	$this->list_view_rights[$row['rol_id']] = $row['rol_this_list_view'];
					
					// fuer die aktuell gesuchte Rolle schon mal den Status merken
					if($row['rol_id'] == $rol_id && $row['rol_this_list_view'] == 1)
					{
						$view_role = true;
					}
	            }
            }
        }
        return $view_role;
    }

    // Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
    function editWeblinksRight()
    {
        return $this->checkRolesRight('rol_weblinks');
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
                          AND cat_org_id = ". $g_current_organization->getValue("org_id");
            $this->db->query($sql);           
            
            if($this->db->num_rows() > 0)
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