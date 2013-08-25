<?php
/******************************************************************************
 * Factory class that creates the relevant database object
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Database
{
	// method creates the interface to the relevant database
    public static function createDatabaseObject($dbType)
    {
        switch ($dbType)
        {
			case 'mysql':
				return new DBMySQL();
				
			case 'postgresql':
				return new DBPostgreSQL();
                
            default:
                return false;
        }
    }
}

?>