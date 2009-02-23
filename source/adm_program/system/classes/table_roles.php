<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_roles
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Rollenobjekt zu erstellen.
 * Eine Rolle kann ueber diese Klasse in der Datenbank verwaltet werden.
 * Dazu werden die Informationen der Rolle sowie der zugehoerigen Kategorie
 * ausgelesen. Geschrieben werden aber nur die Rollendaten
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * setInactive()          - setzt die Rolle auf inaktiv
 * setActive()            - setzt die Rolle wieder auf aktiv
 * countVacancies($count_leaders = false) - gibt die freien Plaetze der Rolle zurueck
 *                          dies ist interessant, wenn rol_max_members gesetzt wurde
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableRoles extends TableAccess
{
	// Alle konfigurierbare Werte für die Bezahlzeitraeume
	// Null oder 0 ist auch erlaubt, bedeutet aber dass kein Zeitraum konfiguriert ist
	var $role_cost_periods = array(-1,1,2,4,12);

    // Konstruktor
    function TableRoles(&$db, $role = '')
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ROLES;
        $this->column_praefix = 'rol';

        if(strlen($role) > 0)
        {
            $this->readData($role);
        }
        else
        {
            $this->clear();
        }
    }

    // Rolle mit der uebergebenen ID oder dem Rollennamen aus der Datenbank auslesen
    function readData($role)
    {
        global $g_current_organization;

        if(is_numeric($role))
        {
            $condition = ' rol_id = '.$role;
        }
        else
        {
            $role = addslashes($role);
            $condition = ' rol_name LIKE "'.$role.'" ';
        }

        $tables    = TBL_CATEGORIES;
        $condition = $condition. ' AND rol_cat_id = cat_id
                                   AND cat_org_id = '. $g_current_organization->getValue('org_id');
        parent::readData($role, $condition, $tables);
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_user, $g_current_session;
        $fields_changed = $this->columnsValueChanged;


        if($this->new_record)
        {
            $this->setValue('rol_timestamp_create', DATETIME_NOW);
            $this->setValue('rol_usr_id_create', $g_current_user->getValue('usr_id'));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('rol_timestamp_create')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('rol_usr_id_create') )
            {
                $this->setValue('rol_timestamp_change', DATETIME_NOW);
                $this->setValue('rol_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }

        parent::save();

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
        }
    }

    // Loescht die Abhaengigkeiten zur Rolle und anschliessend die Rolle selbst...
    function delete()
    {
        global $g_current_session;

        // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
        // eine Rechteaenderung vorgenommen wurde
        $g_current_session->renewUserObject();

        // die Rolle 'Webmaster' darf nicht geloescht werden
        if($this->getValue('rol_name') != 'Webmaster')
        {
            $sql    = 'DELETE FROM '. TBL_ROLE_DEPENDENCIES. '
                        WHERE rld_rol_id_parent = '. $this->getValue('rol_id'). '
                           OR rld_rol_id_child  = '. $this->getValue('rol_id');
            $this->db->query($sql);

            $sql    = 'DELETE FROM '. TBL_MEMBERS. '
                        WHERE mem_rol_id = '. $this->getValue('rol_id');
            $this->db->query($sql);

            //Auch die Inventarpositionen zur Rolle muessen geloescht werden
            //Alle Inventarpositionen auslesen, die von der Rolle angelegt wurden
        	$sql_inventory = 'SELECT *
                              FROM '. TBL_INVENTORY. '
							  WHERE inv_rol_id = '. $this->getValue('rol_id');
        	$result_inventory = $this->db->query($sql_subfolders);

	        while($row_inventory = $this->db->fetch_object($result_inventory))
	        {
	            //Jeder Verleihvorgang zu den einzlenen Inventarpositionen muss geloescht werden
	            $sql    = 'DELETE FROM '. TBL_RENTAL_OVERVIEW. '
                        	WHERE rnt_inv_id = '. $row_inventory->inv_id;
            	$this->db->query($sql);
	        }

			//Jetzt koennen auch die abhaengigen Inventarposition geloescht werden
        	$sql    = 'DELETE FROM '. TBL_INVENTORY. '
                        WHERE inv_rol_id = '. $this->getValue('rol_id');
            $this->db->query($sql);

            return parent::delete();
        }
        else
        {
            return false;
        }
    }
    
    function getCostPeriode()
    {
        return $this->role_cost_periods;
    }

    // aktuelle Rolle wird auf inaktiv gesetzt
    function setInactive()
    {
        global $g_current_session;

        // die Rolle 'Webmaster' darf nicht auf inaktiv gesetzt werden
        if($this->getValue('rol_name') != 'Webmaster')
        {
            $sql    = 'UPDATE '. TBL_MEMBERS. ' SET mem_end   = "'.DATE_NOW.'"
                        WHERE mem_rol_id = '. $this->getValue('rol_id'). '
                          AND mem_begin <= "'.DATE_NOW.'"
                          AND mem_end    > "'.DATE_NOW.'" ';
            $this->db->query($sql);

            $sql    = 'UPDATE '. TBL_ROLES. ' SET rol_valid = 0
                        WHERE rol_id = '. $this->getValue('rol_id');
            $this->db->query($sql);

            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();

            return 0;
        }
        return -1;
    }

    // aktuelle Rolle wird auf aktiv gesetzt
    function setActive()
    {
        global $g_current_session;

        // die Rolle 'Webmaster' ist immer aktiv
        if($this->getValue('rol_name') != 'Webmaster')
        {
            $sql    = 'UPDATE '. TBL_MEMBERS. ' SET mem_end   = "9999-12-31"
                        WHERE mem_rol_id = '. $this->getValue('rol_id');
            $this->db->query($sql);

            $sql    = 'UPDATE '. TBL_ROLES. ' SET rol_valid = 1
                        WHERE rol_id = '. $this->getValue('rol_id');
            $this->db->query($sql);

            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();

            return 0;
        }
        return -1;
    }

    // die Funktion gibt die Anzahl freier Plaetze zurueck
    // ist rol_max_members nicht gesetzt so wird immer 999 zurueckgegeben
    function countVacancies($count_leaders = false)
    {
        if($this->getValue('rol_max_members') > 0)
        {
            $sql    = 'SELECT mem_usr_id FROM '. TBL_MEMBERS. '
                        WHERE mem_rol_id = '. $this->getValue('rol_id'). '
                          AND mem_begin <= "'.DATE_NOW.'"
                          AND mem_end    > "'.DATE_NOW.'"';
            if($count_leaders == false)
            {
                $sql = $sql. ' AND mem_leader = 0 ';
            }
            $this->db->query($sql);

            $num_members = $this->db->num_rows();
            return $this->getValue('rol_max_members') - $num_members;
        }
        return 999;
    }
	
	// die Funktion gibt die deutsche Bezeichnung für die Beitragszeitraeume wieder
	function getRolCostPeriodDesc($my_rol_cost_period)
	{
		if($my_rol_cost_period == -1)
		{
			return 'einmalig';
		}
		elseif($my_rol_cost_period == 1)
		{
			return 'jährlich';
		}
		elseif($my_rol_cost_period == 2)
		{
			return 'halbjährlich';
		}
		elseif($my_rol_cost_period == 4)
		{
			return 'vierteljährlich';
		}
		elseif($my_rol_cost_period == 12)
		{
			return 'monatlich';
		}
		else
		{
			return '--';
		}
	}
}
?>