<?php
/******************************************************************************
 * Klasse fuer den Zugriff auf die Datenbanktabelle adm_rental_overview
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
    public function __construct(&$db, $rnt_id = 0)
    {
        parent::__construct($db, TBL_RENTAL_OVERVIEW, 'rnt', $rnt_id);
    }

    // Leihvorgang mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($rnt_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        $sql_additional_tables .= TBL_INVENTORY;
        $sql_where_condition   .= ' rnt_inv_id = inv_id ';
        return parent::readData($rnt_id, $sql_where_condition, $sql_additional_tables);
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    public function save()
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