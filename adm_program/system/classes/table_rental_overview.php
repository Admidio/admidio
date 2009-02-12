<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_rental_overview
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Leihvorgang zu erstellen.
 * Eine Leihvorgang kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class TableRentalOverview extends TableAccess
{
    // Konstruktor
    function TableRentalOverview(&$db, $rnt_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_RENTAL_OVERVIEW;
        $this->column_praefix = "rnt";

        if($rnt_id > 0)
        {
            $this->readData($rnt_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Leihvorgang mit der uebergebenen ID aus der Datenbank auslesen
    function readData($rnt_id)
    {
        $tables    = TBL_INVENTORY;
        $condition = "       rnt_inv_id = inv_id
                         AND rnt_id     = $rnt_id ";
        parent::readData($rnt_id, $condition, $tables);
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_user;

        if($this->new_record)
        {
            $this->setValue("rnt_timestamp_create", DATETIME_NOW);
            $this->setValue("rnt_usr_id_create", $g_current_user->getValue("usr_id"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue("rnt_timestamp_create")) + 900)
            || $g_current_user->getValue("usr_id") != $this->getValue("rnt_usr_id_create") )
            {
                $this->setValue("rnt_timestamp_change", DATETIME_NOW);
                $this->setValue("rnt_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
    }
}
?>