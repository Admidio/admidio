<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_users
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * updateLoginData()    - Anzahl Logins hochsetzen, Datum aktualisieren und
 *                        ungueltige Logins zuruecksetzen
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableUsers extends TableAccess
{
    var $real_password;     // Unverschluesseltes Passwort. Ist nur gefuellt, wenn gerade das Passwort gesetzt wurde
    var $b_set_last_change; // Kennzeichen, ob User und Zeitstempel der aktuellen Aenderung gespeichert werden sollen

    // Konstruktor
    function TableUsers(&$db, $usr_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_USERS;
        $this->column_praefix = 'usr';

        if(strlen($usr_id) > 0)
        {
            $this->readData($usr_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Anzahl Logins hochsetzen, Datum aktualisieren und ungueltige Logins zuruecksetzen
    function updateLoginData()
    {
        $this->setValue('usr_last_login',   $this->getValue('usr_actual_login'));
        $this->setValue('usr_number_login', $this->getValue('usr_number_login') + 1);
        $this->setValue('usr_actual_login', DATETIME_NOW);
        $this->setValue('usr_date_invalid', NULL);
        $this->setValue('usr_number_invalid', 0);
        $this->b_set_last_change = false;
        $this->save();
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        parent::clear();

        $this->b_set_last_change = true;

        // neue User sollten i.d.R. auf valid stehen (Ausnahme Registrierung)
        $this->setValue('usr_valid', 1);
    }

    function setValue($field_name, $field_value)
    {
        // Passwortfelder sollten verschluesselt als md5-Hash gespeichert werden
        if(($field_name == 'usr_password' || $field_name == 'usr_new_password') && strlen($field_value) < 30)
        {
            // Passwort verschluesselt und unverschluesselt speichern
            $this->real_password = $field_value;
            $field_value = md5($field_value);
        }

        return parent::setValue($field_name, $field_value);
    }

    // die Funktion speichert die Userdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        global $g_current_user;
        $fields_changed = $this->columnsValueChanged;

        if($this->b_set_last_change)
        {
            if($this->new_record)
            {
                $this->setValue('usr_timestamp_create', DATETIME_NOW);
                $this->setValue('usr_usr_id_create', $g_current_user->getValue('usr_id'));
            }
            else
            {
                // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
                if(time() > (strtotime($this->getValue('usr_timestamp_create')) + 900)
                || $g_current_user->getValue('usr_id') != $this->getValue('usr_usr_id_create') )
                {
                    $this->setValue('usr_timestamp_change', DATETIME_NOW);
                    $this->setValue('usr_usr_id_change', $g_current_user->getValue('usr_id'));
                }
            }
        }

        $this->b_set_last_change = true;
        parent::save();
    }

    // Referenzen zum aktuellen Benutzer loeschen
    // die Methode wird innerhalb von delete() aufgerufen
    function delete()
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

        $sql    = 'UPDATE '. TBL_GUESTBOOK. ' SET gbo_usr_id = NULL
                    WHERE gbo_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_GUESTBOOK. ' SET gbo_usr_id_change = NULL
                    WHERE gbo_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);
/*
        $sql    = 'UPDATE '. TBL_INVENTORY. ' SET inv_usr_id_create = NULL
                    WHERE inv_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_INVENTORY. ' SET inv_usr_id_change = NULL
                    WHERE inv_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);
*/
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
/*
        $sql    = 'UPDATE '. TBL_RENTAL_OVERVIEW. ' SET rnt_usr_id_create = NULL
                    WHERE rnt_usr_id_create = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'UPDATE '. TBL_RENTAL_OVERVIEW. ' SET rnt_usr_id_change = NULL
                    WHERE rnt_usr_id_change = '. $this->getValue('usr_id');
        $this->db->query($sql);
*/
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
                    WHERE lsc_lst_id IN (SELECT lst_id FROM adm_lists WHERE lst_usr_id = '.$this->getValue('usr_id').' AND lst_global = 0)';
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_LISTS. ' WHERE lst_global = 0 AND lst_usr_id = '. $this->getValue('usr_id');
        $this->db->query($sql);

        $sql    = 'DELETE FROM '. TBL_GUESTBOOK_COMMENTS. ' WHERE gbc_usr_id = '. $this->getValue('usr_id');
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
}
?>