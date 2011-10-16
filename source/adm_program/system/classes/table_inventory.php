<?php
/******************************************************************************
 * Klasse fuer den Zugriff auf die Datenbanktabelle adm_inventory
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
    public function __construct(&$db, $inv_id = 0)
    {
        parent::__construct($db, TBL_INVENTORY, 'inv', $inv_id);
    }


    // die Methode wird innerhalb von delete() aufgerufen
    public function delete()
    {
		$this->db->startTransaction();

        //erst einmal alle vorhanden Leihvorgaenge zu diesem Inventareintrag loeschen...
        $sql = "DELETE FROM ". TBL_RENTAL_OVERVIEW. " WHERE rnt_inv_id = ". $this->getValue("inv_id");
        $result = $this->db->query($sql);

        $returnCode = parent::delete();
		$this->db->startTransaction();
		return $returnCode;
    }

    //Gibt alle Inventargegenstaende, die der Benutzer sehen darf zurueck
    public function getAllInventoryItems()
    {


    }

    //Liest den Eintrag zu einer uebergebenen inv_id aus der DB
	public function readData($inv_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
		global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        if(is_numeric($inv_id))
        {
            $sql_additional_tables .= TBL_CATEGORIES. ", ". TBL_ROLES;
            $sql_where_condition   .= '    inv_cat_id = cat_id
                                       AND inv_rol_id = rol_id
                                       AND cat_org_id = '. $gCurrentOrganization->getValue('org_id');
            return parent::readData($inv_id, $sql_where_condition, $sql_additional_tables);
        }

        //pruefen ob das Inventarobjekt ueberhaupt ausgelesen werden darf
        if (!$this->getValue('inv_rentable')) {

			//Da das Inventarobjekt nicht verleihbar ist, muss geprueft werden,
			// ob der aktuelle Benutzer es sehen darf.
			if (!$gValidLogin) {
				//Der Benutzer ist nicht eingeloggt, also bekommt er nichts zu sehen
				$this->clear();
			} else {
				//Rolle ueberpruefen
				$sql_rights = "SELECT count(*)
	                         FROM ". TBL_MEMBERS. "
	                        WHERE mem_rol_id = ". $this->getValue("inv_rol_id"). "
	                          AND mem_usr_id = ". $gCurrentUser->getValue("usr_id"). "
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

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    public function save($updateFingerPrint = true)
    {
        global $gCurrentUser;

        if($this->new_record)
        {
            $this->setValue("inv_timestamp_create", DATETIME_NOW);
            $this->setValue("inv_usr_id_create", $gCurrentUser->getValue("usr_id"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("inv_timestamp_create")) + 900)
            || $gCurrentUser->getValue("usr_id") != $this->getValue("inv_usr_id_create") )
            {
                $this->setValue("inv_timestamp_change", DATETIME_NOW);
                $this->setValue("inv_usr_id_change", $gCurrentUser->getValue("usr_id"));
            }
        }
        parent::save($updateFingerPrint);
    }
}
?>