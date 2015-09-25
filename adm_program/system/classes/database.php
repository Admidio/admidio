<?php
/******************************************************************************
 * Factory class that creates the relevant database object
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

/**
 * Class Database
 */
class Database
{
    /**
     * method creates the interface to the relevant database
     * @param string $dbType
     * @return DBMySQL|DBPostgreSQL|false
     */
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
