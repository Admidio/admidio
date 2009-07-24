<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_inventory
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Inventarobjekt zu erstellen.
 * Ein Eintrag in der Inventartabelle kann ueber diese Klasse verwaltet werden
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableInventory extends TableAccess
{
    // Konstruktor
    function TableInventory(&$db, $inv_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_INVENTORY;
        $this->column_praefix = 'inv';

        if($inv_id > 0)
        {
            $this->readData($inv_id);
        }
        else
        {
            $this->clear();
        }
    }


    //Liest den Eintrag zu einer uebergebenen inv_id aus der DB
	function readData($inv_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
		global $g_current_organization, $g_current_user, $g_valid_login;

        if(is_numeric($inv_id))
        {
            $sql_additional_tables .= TBL_CATEGORIES. ", ". TBL_ROLES;
            $sql_where_condition   .= '    inv_cat_id = cat_id
                                       AND inv_rol_id = rol_id
                                       AND cat_org_id = '. $g_current_organization->getValue('org_id');
            parent::readData($inv_id, $sql_where_condition, $sql_additional_tables);
        }

        //pruefen ob das Inventarobjekt ueberhaupt ausgelesen werden darf
        if (!$this->getValue('inv_rentable')) {

			//Da das Inventarobjekt nicht verleihbar ist, muss geprueft werden,
			// ob der aktuelle Benutzer es sehen darf.
			if (!$g_valid_login) {
				//Der Benutzer ist nicht eingeloggt, also bekommt er nichts zu sehen
				$this->clear();
			} else {
				//Rolle ueberpruefen
				$sql_rights = "SELECT count(*)
	                         FROM ". TBL_MEMBERS. "
	                        WHERE mem_rol_id = ". $this->getValue("inv_rol_id"). "
	                          AND mem_usr_id = ". $g_current_user->getValue("usr_id"). "
	                          AND mem_begin <= '".DATE_NOW."'
	                          AND mem_end    > '".DATE_NOW."'";
                $result_rights = $this->db->query($sql_rights);
                $row_rights = $this->db->fetch_array($result_rights);
                $row_count  = $row_rights[0];

                //Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Inventarobjekt besitzt
                //wird ebenfalls ein leeres Objekt zurueckgegeben
                if ($row_count == 0)
                {
                    $this->clear();
                }
			}
        }

    }


    //Gibt alle Inventargegenstaende, die der Benutzer sehen darf zurueck
    function getAllInventoryItems()
    {


    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_user;

        if($this->new_record)
        {
            $this->setValue("inv_timestamp_create", DATETIME_NOW);
            $this->setValue("inv_usr_id_create", $g_current_user->getValue("usr_id"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("inv_timestamp_create")) + 900)
            || $g_current_user->getValue("usr_id") != $this->getValue("inv_usr_id_create") )
            {
                $this->setValue("inv_timestamp_change", DATETIME_NOW);
                $this->setValue("inv_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
    }

    // die Methode wird innerhalb von delete() aufgerufen
    function delete()
    {
        //erst einmal alle vorhanden Leihvorgaenge zu diesem Inventareintrag loeschen...
        $sql = "DELETE FROM ". TBL_RENTAL_OVERVIEW. " WHERE rnt_inv_id = ". $this->getValue("inv_id");
        $result = $this->db->query($sql);

        return parent::delete();
    }
}
?>