<?php
/******************************************************************************
 * Factory-Klasse welches das relevante Datenbankobjekt erstellt
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Database
{
    // Funktion erstellt die Schnittstelle zur entsprechenden Datenbank

    public static function createDatabaseObject($db_type)
    {
        switch ($db_type)
        {
			case 'mysql':
				require_once(SERVER_PATH. '/adm_program/system/db/db_mysql.php');
				return new DBMySQL();
				
			case 'postgresql':
				require_once(SERVER_PATH. '/adm_program/system/db/db_postgresql.php');
				return new DBPostgreSQL();
                
            default:
                return false;
        }
    }
}

?>