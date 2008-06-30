<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_categories
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $category = new Category($g_db);
 *
 * Mit der Funktion getCategory($cat_id) kann das gewuenschte Feld ausgelesen
 * werden.
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Kategorie wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben oder angelegt
 * delete()               - Die gewaehlte Rolle wird aus der Datenbank geloescht
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class Category extends TableAccess
{
    var $calc_sequence;

    // Konstruktor
    function Category(&$db, $cat_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_CATEGORIES;
        $this->column_praefix = "cat";
        
        if($cat_id > 0)
        {
            $this->getCategory($cat_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    function getCategory($cat_id)
    {
        $this->readData($cat_id);
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization;
        
        $this->calc_sequence = false;
            
        if($this->new_record
        || $this->db_fields_infos['cat_org_id']['changed'] == true)
        {
            if($this->db_fields['cat_org_id'] > 0)
            {
                $org_condition = " AND (  cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                                       OR cat_org_id IS NULL ) ";
            }
            else
            {
               $org_condition = " AND cat_org_id IS NULL ";
            }
            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = "SELECT COUNT(*) as count FROM ". TBL_CATEGORIES. "
                     WHERE cat_type = '". $this->db_fields['cat_type']. "'
                           $org_condition ";
            $this->db->query($sql);

            $row = $this->db->fetch_array();

            $this->setValue("cat_sequence", $row['count'] + 1);
            
            if($this->db_fields['cat_org_id'] == 0)
            {
                // eine Orga-uebergreifende Kategorie ist immer am Anfang, also Kategorien anderer Orgas nach hinten schieben
                $sql = "UPDATE ". TBL_CATEGORIES. " SET cat_sequence = cat_sequence + 1
                         WHERE cat_type = '". $this->db_fields['cat_type']. "'
                           AND cat_org_id IS NOT NULL ";
                $this->db->query($sql);                 
            }
        }
    }

    // Methode wird erst nach dem Speichern der Profilfelder aufgerufen
    function _afterSave()
    {
        global $g_current_session;
        
        if($this->db_fields_changed && $this->db_fields['cat_type'] == 'USF' && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, 
            // da Aenderungen in den Profilfeldern vorgenommen wurden 
            $g_current_session->renewUserObject();
        }
    }
    
    // interne Funktion, die die Referenzen bearbeitet, wenn die Kategorie geloescht wird
    // die Funktion wird innerhalb von delete() aufgerufen
    function _delete()
    {
        global $g_current_session;
        
        // Luecke in der Reihenfolge schliessen
        $sql = "UPDATE ". TBL_CATEGORIES. " SET cat_sequence = cat_sequence - 1 
                 WHERE (  cat_org_id = ". $g_current_session->getValue("ses_org_id"). "
                       OR cat_org_id IS NULL )
                   AND cat_sequence > ". $this->getValue("cat_sequence"). "
                   AND cat_type     = '". $this->getValue("cat_type"). "'";
        $this->db->query($sql);

        // Abhaenigigkeiten loeschen
        if($this->db_fields['cat_type'] == 'ROL')
        {
            $sql    = "DELETE FROM ". TBL_ROLES. "
                        WHERE rol_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
        }
        elseif($this->db_fields['cat_type'] == 'LNK')
        {
            $sql    = "DELETE FROM ". TBL_LINKS. "
                        WHERE lnk_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
        }
        elseif($this->db_fields['cat_type'] == 'USF')
        {
            $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                        WHERE usf_cat_id = ". $this->db_fields['cat_id'];
            $this->db->query($sql);
            
            // einlesen aller Userobjekte der angemeldeten User anstossen, 
            // da Aenderungen in den Profilfeldern vorgenommen wurden 
            $g_current_session->renewUserObject();
        }
        return true;    
    }
}
?>