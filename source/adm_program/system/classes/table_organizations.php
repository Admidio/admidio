<?php 
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Objekt einer Organisation zu erstellen. 
 * Eine Organisation kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableOrganizations extends TableAccess
{
    // Konstruktor
    public function __construct(&$db, $organization = '')
    {
        parent::__construct($db, TBL_ORGANIZATIONS, 'org', $organization);
    }

    // Organisation mit der uebergebenen ID oder der Kurzbezeichnung aus der Datenbank auslesen
    public function readData($organization, $sql_where_condition = '', $sql_additional_tables = '')
    {
        // wurde org_shortname uebergeben, dann die SQL-Bedingung anpassen
        if(is_numeric($organization) == false)
        {
            $organization = addslashes($organization);
            $sql_where_condition .= ' org_shortname LIKE "'.$organization.'" ';
        }
        
        return parent::readData($organization, $sql_where_condition, $sql_additional_tables);
    }
}
?>