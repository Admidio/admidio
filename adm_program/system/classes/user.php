<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * readUserData()       - baut ein Array mit allen Profilfeldern und
 *                        den entsprechenden Werten des Users auf
 * clearUserFieldArray($delete_db_data = false)
 *                      - der Inhalt der Profilfelder wird geloescht, 
 *                        die Objekte mit DB-Struktur aber nicht !
 * getProperty($field_name, $property)
 *                      - gibt den Inhalt einer Eigenschaft eines Feldes zurueck.
 *                        Dies kann die usf_id, usf_type, cat_id, cat_name usw. sein
 * getPropertyById($field_id, $property)
 * getListViewRights()  - Liefert ein Array mit allen Rollen und der 
 *                        Berechtigung, ob der User die Liste einsehen darf
 *                      - aehnlich getProperty, allerdings suche ueber usf_id
 * getVCard()           - Es wird eine vCard des Users als String zurueckgegeben
 * viewProfile          - Ueberprueft ob der User das Profil eines uebrgebenen
 *                        Users einsehen darf
 * viewRole             - Ueberprueft ob der User eine uebergebene Rolle(Liste)
 *                        einsehen darf
 * isWebmaster()        - gibt true/false zurueck, falls der User Mitglied der
 *                        Rolle "Webmaster" ist
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_users.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_user_data.php');

class User extends TableUsers
{
    protected $webmaster;

    public $userFieldData       = array(); // Array ueber alle Userdatenobjekte mit den entsprechenden Feldeigenschaften
    public $roles_rights     = array(); // Array ueber alle Rollenrechte mit dem entsprechenden Status des Users
    protected $list_view_rights = array(); // Array ueber Listenrechte einzelner Rollen => Zugriff nur über getListViewRights()
    protected $role_mail_rights = array(); // Array ueber Mailrechte einzelner Rollen

    // Konstruktor
    public function __construct(&$db, $usr_id = 0)
    {
        parent::__construct($db, $usr_id);
    }

    // Methode prueft, ob der User das uebergebene Rollenrecht besitzt und setzt das Array mit den Flags,
    // welche Rollen der User einsehen darf
    public function checkRolesRight($right = '')
    {
        global $g_l10n;

        if($this->getValue('usr_id') > 0)
        {
            if(count($this->roles_rights) == 0)
            {
                global $g_current_organization;
                $tmp_roles_rights  = array('rol_assign_roles'  => '0', 'rol_approve_users' => '0',
                                           'rol_announcements' => '0', 'rol_dates' => '0',
                                           'rol_download'      => '0', 'rol_edit_user' => '0',
                                           'rol_guestbook'     => '0', 'rol_guestbook_comments' => '0',
                                           'rol_inventory'     => '0',
                                           'rol_mail_to_all'   => '0',
                                           'rol_photo'         => '0', 'rol_profile' => '0',
                                           'rol_weblinks'      => '0', 'rol_all_lists_view' => '0');

                // Alle Rollen der Organisation einlesen und ggf. Mitgliedschaft dazu joinen
                $sql = 'SELECT *
                          FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                          LEFT JOIN '. TBL_MEMBERS. '
                            ON mem_usr_id  = '. $this->getValue('usr_id'). '
                           AND mem_rol_id  = rol_id
                           AND mem_begin  <= \''.DATE_NOW.'\'
                           AND mem_end     > \''.DATE_NOW.'\'
                         WHERE rol_valid   = 1
                           AND rol_cat_id  = cat_id
                           AND (  cat_org_id = '. $g_current_organization->getValue('org_id').' 
                               OR cat_org_id IS NULL ) ';
                $this->db->query($sql);

                while($row = $this->db->fetch_array())
                {
                    // Rechte nur beruecksichtigen, wenn auch Rollenmitglied
                    if($row['mem_usr_id'] > 0)
                    {
                        // Rechte der Rollen in das Array uebertragen,
                        // falls diese noch nicht durch andere Rollen gesetzt wurden
                        foreach($tmp_roles_rights as $key => $value)
                        {
                            if($value == '0' && $row[$key] == '1')
                            {
                                $tmp_roles_rights[$key] = '1';
                            }
                        }
                    }

                    // Webmasterflag setzen
                    if($row['mem_usr_id'] > 0 && $row['rol_name'] == $g_l10n->get('SYS_WEBMASTER'))
                    {
                        $this->webmaster = 1;
                    }

                    // Listenansichtseinstellung merken
                    // Leiter duerfen die Rolle sehen
                    if($row['mem_usr_id'] > 0 && ($row['rol_this_list_view'] > 0 || $row['mem_leader'] == 1))
                    {
                        // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    elseif($row['rol_this_list_view'] == 2)
                    {
                        // andere Rollen anschauen, wenn jeder sie sehen darf
                        $this->list_view_rights[$row['rol_id']] = 1;
                    }
                    else
                    {
                        $this->list_view_rights[$row['rol_id']] = 0;
                    }

                    // Mailrechte setzen
                    // Leiter duerfen der Rolle Mails schreiben
                    if($row['mem_usr_id'] > 0 && ($row['rol_mail_this_role'] > 0 || $row['mem_leader'] == 1))
                    {
                        // Mitgliedschaft bei der Rolle und diese nicht gesperrt, dann anschauen
                        $this->role_mail_rights[$row['rol_id']] = 1;
                    }
                    elseif($row['rol_mail_this_role'] >= 2)
                    {
                        // andere Rollen anschauen, wenn jeder sie sehen darf
                        $this->role_mail_rights[$row['rol_id']] = 1;
                    }
                    else
                    {
                        $this->role_mail_rights[$row['rol_id']] = 0;
                    }
                }
                $this->roles_rights = $tmp_roles_rights;

                // ist das Recht 'alle Listen einsehen' gesetzt, dann dies auch im Array bei allen Rollen setzen
                if($this->roles_rights['rol_all_lists_view'])
                {
                    foreach($this->list_view_rights as $key => $value)
                    {
                        $this->list_view_rights[$key] = 1;
                    }
                }

                // ist das Recht 'allen Rollen EMails schreiben' gesetzt, dann dies auch im Array bei allen Rollen setzen
                if($this->roles_rights['rol_mail_to_all'])
                {
                    foreach($this->role_mail_rights as $key => $value)
                    {
                        $this->role_mail_rights[$key] = 1;
                    }
                }

            }

            if(strlen($right) == 0 || $this->roles_rights[$right] == 1)
            {
                return true;
            }
        }
        return 0;
    }

    // alle Klassenvariablen wieder zuruecksetzen
    public function clear()
    {
        parent::clear();

        // die Daten der Profilfelder werden geloescht, die Struktur bleibt
        $this->clearUserFieldArray();

        $this->webmaster = 0;

        // neue User sollten i.d.R. auf valid stehen (Ausnahme Registrierung)
        $this->setValue('usr_valid', 1);

        // Arrays initialisieren
        $this->roles_rights = array();
        $this->list_view_rights = array();
        $this->role_mail_rights = array();
    }
    
    // der Inhalt der Felder wird geloescht, die Objekte mit DB-Struktur nur auf Wunsch
    public function clearUserFieldArray($delete_db_data = false)
    {
        // Jedes Feld durchgehen und alle Inhalte der Tabelle adm_user_data entfernen
        foreach($this->userFieldData as $field_name => $object)
        {
            $this->userFieldData[$field_name]->clearFieldData();
        }
        
        // Daten in der DB auch loeschen
        if($delete_db_data)
        {
            $sql    = 'DELETE FROM '. TBL_USER_DATA. ' WHERE usd_usr_id = '. $this->getValue('usr_id');
            $this->db->query($sql);
        }
    }
    
    // Liefert ein Array mit allen Rollen und der Berechtigung, ob der User die Liste einsehen darf
    public function getListViewRights()
    {
        $this->checkRolesRight();
        return $this->list_view_rights;
    }

    // Methode gibt den Wert eines Profilfeldes zurueck
    // Property ist dabei ein Feldname aus der Tabelle adm_user_fields oder adm_user_data
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    public function getProperty($field_name, $property, $format = '')
    {
        if(isset($this->userFieldData[$field_name]))
        {
            return $this->userFieldData[$field_name]->getValue($property, $format);
        }
		
		// if id-field not exists then return zero
		if(strpos($property, '_id') > 0)
		{
			return 0;
		}
        return '';
    }

    // aehnlich getProperty, allerdings suche ueber usf_id
    public function getPropertyById($field_id, $property, $format = '')
    {
        foreach($this->userFieldData as $field)
        {
            if($field->getValue('usf_id') == $field_id)
            {
                return $field->getValue($property, $format);
            }
        }
        return '';
    }

    // Methode prueft, ob evtl. ein Wert aus der User-Fields-Tabelle
    // angefordert wurde und gibt diesen zurueck
    public function getValue($field_name, $format = '')
    {
        if(strpos($field_name, 'usr_') === 0)
        {
            return parent::getValue($field_name, $format);
        }
        else
        {
            return $this->getProperty($field_name, 'usd_value', $format);
        }
    }

    // gibt die Userdaten als VCard zurueck
    // da das Windows-Adressbuch einschliesslich XP kein UTF8 verarbeiten kann, alles in ISO-8859-1 ausgeben
    public function getVCard()
    {
        global $g_current_user, $g_preferences;

        $editAllUsers = $g_current_user->editProfile($this->getValue('usr_id'));

        $vcard  = (string) "BEGIN:VCARD\r\n";
        $vcard .= (string) "VERSION:2.1\r\n";
        if($editAllUsers || ($editAllUsers == false && $this->userFieldData['FIRST_NAME']->getValue('usf_hidden') == 0))
        {
            $vcard .= (string) "N;CHARSET=ISO-8859-1:" . utf8_decode($this->getValue('LAST_NAME')). ";". utf8_decode($this->getValue('FIRST_NAME')) . ";;;\r\n";
        }
        if($editAllUsers || ($editAllUsers == false && $this->userFieldData['LAST_NAME']->getValue('usf_hidden') == 0))
        {
            $vcard .= (string) "FN;CHARSET=ISO-8859-1:". utf8_decode($this->getValue('FIRST_NAME')) . " ". utf8_decode($this->getValue('LAST_NAME')) . "\r\n";
        }
        if (strlen($this->getValue('usr_login_name')) > 0)
        {
            $vcard .= (string) "NICKNAME;CHARSET=ISO-8859-1:" . utf8_decode($this->getValue("usr_login_name")). "\r\n";
        }
        if (strlen($this->getValue('PHONE')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['PHONE']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "TEL;HOME;VOICE:" . $this->getValue('PHONE'). "\r\n";
        }
        if (strlen($this->getValue('MOBILE')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['MOBILE']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "TEL;CELL;VOICE:" . $this->getValue('MOBILE'). "\r\n";
        }
        if (strlen($this->getValue('FAX')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['FAX']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "TEL;HOME;FAX:" . $this->getValue('FAX'). "\r\n";
        }
        if($editAllUsers || ($editAllUsers == false && $this->userFieldData['ADDRESS']->getValue('usf_hidden') == 0 && $this->userFieldData['CITY']->getValue('usf_hidden') == 0
        && $this->userFieldData['POSTCODE']->getValue('usf_hidden') == 0  && $this->userFieldData['COUNTRY']->getValue('usf_hidden') == 0))
        {
            $vcard .= (string) "ADR;CHARSET=ISO-8859-1;HOME:;;" . utf8_decode($this->getValue('ADDRESS')). ";" . utf8_decode($this->getValue('CITY')). ";;" . utf8_decode($this->getValue('POSTCODE')). ";" . utf8_decode($this->getValue('COUNTRY')). "\r\n";
        }
        if (strlen($this->getValue('WEBSITE')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['WEBSITE']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "URL;HOME:" . $this->getValue('WEBSITE'). "\r\n";
        }
        if (strlen($this->getValue('BIRTHDAY')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['BIRTHDAY']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "BDAY:" . $this->getValue('BIRTHDAY', 'Ymd') . "\r\n";
        }
        if (strlen($this->getValue('EMAIL')) > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['EMAIL']->getValue('usf_hidden') == 0)))
        {
            $vcard .= (string) "EMAIL;PREF;INTERNET:" . $this->getValue('EMAIL'). "\r\n";
        }
        if (file_exists(SERVER_PATH.'/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg') && $g_preferences['profile_photo_storage'] == 1)
        {
            $img_handle = fopen (SERVER_PATH. '/adm_my_files/user_profile_photos/'.$this->getValue('usr_id').'.jpg', 'rb');
            $vcard .= (string) "PHOTO;ENCODING=BASE64;TYPE=JPEG:".base64_encode(fread ($img_handle, filesize (SERVER_PATH. "/adm_my_files/user_profile_photos/".$this->getValue("usr_id").".jpg"))). "\r\n";
            fclose($img_handle);
        }
        if (strlen($this->getValue('usr_photo')) > 0 && $g_preferences['profile_photo_storage'] == 0)
        {
            $vcard .= (string) "PHOTO;ENCODING=BASE64;TYPE=JPEG:".base64_encode($this->getValue("usr_photo")). "\r\n";
        }
        // Geschlecht ist nicht in vCard 2.1 enthalten, wird hier fuer das Windows-Adressbuch uebergeben
        if ($this->getValue('GENDER') > 0
        && ($editAllUsers || ($editAllUsers == false && $this->userFieldData['GENDER']->getValue('usf_hidden') == 0)))
        {
            if($this->getValue('GENDER') == 1)
            {
                $wab_gender = 2;
            }
            else
            {
                $wab_gender = 1;
            }
            $vcard .= (string) "X-WAB-GENDER:" . $wab_gender . "\r\n";
        }
        if (strlen($this->getValue('usr_timestamp_change')) > 0)
        {
            $vcard .= (string) "REV:" . $this->getValue('usr_timestamp_change', 'ymdThis') . "\r\n";
        }

        $vcard .= (string) "END:VCARD\r\n";
        return $vcard;
    }

    public function readData($usr_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        parent::readData($usr_id, $sql_where_condition, $sql_additional_tables);

        $this->readUserData();
    }
    
    // bei setValue werden die Werte nicht auf Gueltigkeit geprueft
    public function noValueCheck()
    {
        foreach($this->userFieldData as $field)
        {
            $field->noValueCheck();
        }
    }

    // baut ein Array mit allen Profilfeldern und den entsprechenden Werten des Users auf
    public function readUserData()
    {
    	global $g_current_organization;

        if($this->getValue('usr_id') > 0)
        {
            $join_user_data  = 'LEFT JOIN '. TBL_USER_DATA. '
                                  ON usd_usf_id = usf_id
                                 AND usd_usr_id = '. $this->getValue('usr_id');
        }
        else
        {
            $join_user_data  = '';
        }

        $sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
                       '.$join_user_data.'
                 WHERE usf_cat_id = cat_id
                   AND (  cat_org_id IS NULL
                       OR cat_org_id  = '. $g_current_organization->getValue('org_id'). ' )
                 ORDER BY cat_sequence ASC, usf_sequence ASC ';
        $usf_result   = $this->db->query($sql);

        while($usf_row = $this->db->fetch_array($usf_result))
        {
            if(isset($this->userFieldData[$usf_row['usf_name_intern']]) == false)
            {
                $this->userFieldData[$usf_row['usf_name_intern']] = new TableUserData($this->db);
            }
            $this->userFieldData[$usf_row['usf_name_intern']]->setArray($usf_row);
        }
    }

    public function save($updateFingerPrint = true)
    {
        global $g_current_session;
        $fields_changed = $this->columnsValueChanged;

        parent::save($updateFingerPrint);

        // jetzt noch die einzelnen Spalten sichern
        foreach($this->userFieldData as $field)
        {
            // update nur machen, wenn auch noetig
            if($field->getValue('usd_id') > 0 || strlen($field->getValue('usd_value')) > 0)
            {
                // wird das Feld neu gefuellt, dann auch User-ID setzen
                if(strlen($field->getValue('usd_usr_id')) == 0
                && strlen($field->getValue('usd_value')) > 0)
                {
                	// PHP4 liefert nur eine Kopie des Objekts, aus diesem Grund muss die Aenderung direkt auf das 
                	// richtige Objekt verwiesen werden
                	$this->userFieldData[$field->getValue('usf_name_intern')]->setValue('usd_usr_id', $this->getValue('usr_id'));
                    $this->userFieldData[$field->getValue('usf_name_intern')]->setValue('usd_usf_id', $field->getValue('usf_id'));
                    $this->userFieldData[$field->getValue('usf_name_intern')]->new_record = true;
                }

                // existiert schon ein Wert und dieser wird entfernt, dann auch DS loeschen
                if($field->getValue('usd_id') > 0
                && strlen($field->getValue('usd_value')) == 0)
                {
                    $this->userFieldData[$field->getValue('usf_name_intern')]->delete();
                }
                else
                {
                    $this->userFieldData[$field->getValue('usf_name_intern')]->save();
                }
            }
        }

        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject($this->getValue('usr_id'));
        }
    }

    // interne Methode, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    public function setValue($field_name, $field_value)
    {
        global $g_current_user;
        $return_code  = true;
        $update_field = false;

        if(strpos($field_name, 'usr_') !== 0)
        {
            // Daten fuer User-Fields-Tabelle

            // gesperrte Felder duerfen nur von Usern mit dem Rollenrecht 'alle Benutzerdaten bearbeiten' geaendert werden
            // bei Registrierung muss die Eingabe auch erlaubt sein
            if((  $this->getProperty($field_name, 'usf_disabled') == 1
               && $g_current_user->editUsers() == true)
            || $this->getProperty($field_name, 'usf_disabled') == 0
            || ($g_current_user->getValue('usr_id') == 0 && $this->getValue('usr_id') == 0))
            {
                // versteckte Felder duerfen nur von Usern mit dem Rollenrecht 'alle Benutzerdaten bearbeiten' geaendert werden
                // oder im eigenen Profil
                if((  $this->getProperty($field_name, 'usf_hidden') == 1
                   && $g_current_user->editUsers() == true)
                || $this->getProperty($field_name, 'usf_hidden') == 0
                || $g_current_user->getValue('usr_id') == $this->getValue('usr_id'))
                {
                    $update_field = true;
                }
            }

            // nur Updaten, wenn sich auch der Wert geaendert hat
            if($update_field == true
            && $field_value  != $this->userFieldData[$field_name]->getValue('usd_value'))
            {
                $return_code = $this->userFieldData[$field_name]->setValue('usd_value', $field_value);
            }
        }
        else
        {
            $return_code = parent::setValue($field_name, $field_value);
        }
        return $return_code;
    }

    // Funktion prueft, ob der angemeldete User Ankuendigungen anlegen und bearbeiten darf
    public function editAnnouncements()
    {
        return $this->checkRolesRight('rol_announcements');
    }

    // Funktion prueft, ob der angemeldete User Registrierungen bearbeiten und zuordnen darf
    public function approveUsers()
    {
        return $this->checkRolesRight('rol_approve_users');
    }

    // Funktion prueft, ob der angemeldete User Rollen zuordnen, anlegen und bearbeiten darf
    public function assignRoles()
    {
        return $this->checkRolesRight('rol_assign_roles');
    }

    //Ueberprueft ob der User das Recht besitzt, alle Rollenlisten einsehen zu duerfen
    public function viewAllLists()
    {
        return $this->checkRolesRight('rol_all_lists_view');
    }

    //Ueberprueft ob der User das Recht besitzt, allen Rollenmails zu zusenden
    public function mailAllRoles()
    {
        return $this->checkRolesRight('rol_mail_to_all');
    }

    // Funktion prueft, ob der angemeldete User Termine anlegen und bearbeiten darf
    public function editDates()
    {
        return $this->checkRolesRight('rol_dates');
    }

    // Funktion prueft, ob der angemeldete User Downloads hochladen und verwalten darf
    public function editDownloadRight()
    {
        return $this->checkRolesRight('rol_download');
    }

    // Funktion prueft, ob der angemeldete User das entsprechende Profil bearbeiten darf
    public function editProfile($profileID = NULL)
    {
        if($profileID == NULL)
        {
            $profileID = $this->getValue('usr_id');
        }

        //soll das eigene Profil bearbeitet werden?
        if($profileID == $this->getValue('usr_id') && $this->getValue('usr_id') > 0)
        {
            $edit_profile = $this->checkRolesRight('rol_profile');

            if($edit_profile == 1)
            {
                return true;
            }
            else
            {
                return $this->editUsers();
            }

        }
        else
        {
            return $this->editUsers();
        }
    }

    // Funktion prueft, ob der angemeldete User fremde Benutzerdaten bearbeiten darf
    public function editUsers()
    {
        return $this->checkRolesRight('rol_edit_user');
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege loeschen und editieren darf
    public function editGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook');
    }

    // Funktion prueft, ob der angemeldete User Gaestebucheintraege kommentieren darf
    public function commentGuestbookRight()
    {
        return $this->checkRolesRight('rol_guestbook_comments');
    }

	// Funktion prueft, ob der angemeldete User Inventargegenstaende verwalten darf
    public function editInventoryRight()
    {
        return $this->checkRolesRight('rol_inventory');
    }

    // Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf
    public function editPhotoRight()
    {
        return $this->checkRolesRight('rol_photo');
    }

    // Funktion prueft, ob der angemeldete User Weblinks anlegen und editieren darf
    public function editWeblinksRight()
    {
        return $this->checkRolesRight('rol_weblinks');
    }

    // Funktion prueft, ob der User ein Profil einsehen darf
    public function viewProfile($usr_id)
    {
        global $g_current_organization;
        $view_profile = false;

        //Hat ein User Profileedit rechte, darf er es natuerlich auch sehen
        if($this->editProfile($usr_id))
        {
            $view_profile = true;
        }
        else
        {
            // Benutzer, die alle Listen einsehen duerfen, koennen auch alle Profile sehen
            if($this->viewAllLists())
            {
                $view_profile = true;
            }
            else
            {
                $sql    = 'SELECT rol_id, rol_this_list_view
                             FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                            WHERE mem_usr_id = '.$usr_id. '
                              AND mem_begin <= \''.DATE_NOW.'\'
                              AND mem_end    > \''.DATE_NOW.'\'
                              AND mem_rol_id = rol_id
                              AND rol_valid  = 1
                              AND rol_cat_id = cat_id
                              AND (  cat_org_id = '. $g_current_organization->getValue('org_id').'
                                  OR cat_org_id IS NULL ) ';
                $this->db->query($sql);

                if($this->db->num_rows() > 0)
                {
                    while($row = $this->db->fetch_array())
                    {
                        if($row['rol_this_list_view'] == 2)
                        {
                            // alle angemeldeten Benutzer duerfen Rollenlisten/-profile sehen
                            $view_profile = true;
                        }
                        elseif($row['rol_this_list_view'] == 1
                        && isset($this->list_view_rights[$row['rol_id']]))
                        {
                            // nur Rollenmitglieder duerfen Rollenlisten/-profile sehen
                            $view_profile = true;
                        }
                    }
                }
            }
        }
        return $view_profile;
    }

    // Methode prueft, ob der angemeldete User eine bestimmte oder alle Listen einsehen darf
    public function viewRole($rol_id)
    {
        $view_role = false;
        // Abfrage ob der User durch irgendeine Rolle das Recht bekommt alle Listen einzusehen
        if($this->viewAllLists())
        {
            $view_role = true;
        }
        else
        {
            // Falls er das Recht nicht hat Kontrolle ob fuer eine bestimmte Rolle
            if(isset($this->list_view_rights[$rol_id]) && $this->list_view_rights[$rol_id] > 0)
            {
                $view_role = true;
            }
        }
        return $view_role;
    }

	// Methode prueft, ob der angemeldete User einer bestimmten oder allen Rolle E-Mails zusenden darf
    public function mailRole($rol_id)
    {
        $mail_role = false;
        // Abfrage ob der User durch irgendeine Rolle das Recht bekommt alle Listen einzusehen
        if($this->mailAllRoles())
        {
            $mail_role = true;
        }
        else
        {
            // Falls er das Recht nicht hat Kontrolle ob fuer eine bestimmte Rolle
            if(isset($this->role_mail_rights[$rol_id]) && $this->role_mail_rights[$rol_id] > 0)
            {
                $mail_role = true;
            }
        }
        return $mail_role;
    }

    // Methode liefert true zurueck, wenn der User Mitglied der Rolle "Webmaster" ist
    public function isWebmaster()
    {
        $this->checkRolesRight();
        return $this->webmaster;
    }
}
?>