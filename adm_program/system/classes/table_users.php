<?php
/******************************************************************************
 * Class manages access to database table adm_users
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * updateLoginData()    - Anzahl Logins hochsetzen, Datum aktualisieren und
 *                        ungueltige Logins zuruecksetzen
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/libs/phpass/passwordhash.php');

class TableUsers extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $usr_id = 0)
    {
        parent::__construct($db, TBL_USERS, 'usr', $usr_id);
    }

    // Anzahl Logins hochsetzen, Datum aktualisieren und ungueltige Logins zuruecksetzen
    public function updateLoginData()
    {
        $this->setValue('usr_last_login',   $this->getValue('usr_actual_login', 'Y-m-d H:i:s'));
        $this->setValue('usr_number_login', $this->getValue('usr_number_login') + 1);
        $this->setValue('usr_actual_login', DATETIME_NOW);
        $this->setValue('usr_date_invalid', NULL);
        $this->setValue('usr_number_invalid', 0);
        $this->save(false); // Zeitstempel nicht aktualisieren
    }

    // alle Klassenvariablen wieder zuruecksetzen
    public function clear()
    {
        parent::clear();

        // new user should be valid (except registration)
        $this->setValue('usr_valid', 1);
        $this->columnsValueChanged = false;
    }

    // Referenzen zum aktuellen Benutzer loeschen
    // die Methode wird innerhalb von delete() aufgerufen
    public function delete()
    {
        $this->db->startTransaction();

        $sql    = 'UPDATE '. TBL_ANNOUNCEMENTS. ' SET ann_usr_id_create = NULL
                    WHERE ann_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_ANNOUNCEMENTS. ' SET ann_usr_id_change = NULL
                    WHERE ann_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_DATES. ' SET dat_usr_id_create = NULL
                    WHERE dat_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_DATES. ' SET dat_usr_id_change = NULL
                    WHERE dat_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_FOLDERS. ' SET fol_usr_id = NULL
                    WHERE fol_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_FILES. ' SET fil_usr_id = NULL
                    WHERE fil_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_GUESTBOOK. ' SET gbo_usr_id_create = NULL
                    WHERE gbo_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_GUESTBOOK. ' SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_LINKS. ' SET lnk_usr_id_create = NULL
                    WHERE lnk_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_LINKS. ' SET lnk_usr_id_change = NULL
                    WHERE lnk_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_LISTS. ' SET lst_usr_id = NULL
                    WHERE lst_global = 1
                      AND lst_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_PHOTOS. ' SET pho_usr_id_create = NULL
                    WHERE pho_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_PHOTOS. ' SET pho_usr_id_change = NULL
                    WHERE pho_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_ROLES. ' SET rol_usr_id_create = NULL
                    WHERE rol_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_ROLES. ' SET rol_usr_id_change = NULL
                    WHERE rol_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_ROLE_DEPENDENCIES. ' SET rld_usr_id = NULL
                    WHERE rld_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_USERS. ' SET usr_usr_id_create = NULL
                    WHERE usr_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_USERS. ' SET usr_usr_id_change = NULL
                    WHERE usr_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_LIST_COLUMNS. '
                    WHERE lsc_lst_id IN (SELECT lst_id FROM '. TBL_LISTS. ' WHERE lst_usr_id = '.$this->getValue('usr_id').' AND lst_global = 0)';
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_LISTS. ' WHERE lst_global = 0 AND lst_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_GUESTBOOK_COMMENTS. ' WHERE gbc_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_MEMBERS. ' WHERE mem_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_AUTO_LOGIN. ' WHERE atl_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_SESSIONS. ' WHERE ses_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_USER_DATA. ' WHERE usd_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $return = parent::delete();

        $this->db->endTransaction();
        return $return;
    }

    // validates the value and adapts it if necessary
    public function setValue($field_name, $field_value, $check_value = true)
    {
        // encode Passwort with phpAss
        if(($field_name == 'usr_password' || $field_name == 'usr_new_password') && strlen($field_value) < 30)
        {
            $check_value    = false;
            $passwordHasher = new PasswordHash(9, true);
            $field_value    = $passwordHasher->HashPassword($field_value);
        }
		// username should not contain special characters
		elseif($field_name == 'usr_login_name')
		{
			if (strlen($field_value) > 0 && strValidCharacters($field_value, 'noSpecialChar') == false)
			{
				return false;
			}
		}

        return parent::setValue($field_name, $field_value);
    }
}
?>