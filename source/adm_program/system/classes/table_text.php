<?php
/******************************************************************************
 * Class manages access to database table adm_texts
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Textobjekt zu erstellen.
 * Texte koennen ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung.
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableText extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_texts. 
	 *  If the id is set than the specific text will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $name The recordset of the text with this name will be loaded. If name isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $name = '')
    {
        parent::__construct($db, TBL_TEXTS, 'txt', $name);
    }
    
    /** Get the value of a column of the database table.
     *  If the value was manipulated before with @b setValue than the manipulated value is returned.
     *  @param $columnName The name of the database column whose value should be read
     *  @param $format For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                 For text columns the format can be @b plain that would return the original database value without any transformations
     *  @return Returns the value of the database column.
     *          If the value was manipulated before with @b setValue than the manipulated value is returned.
     */ 
    public function getValue($columnName, $format = '')
    {
        if($columnName == 'txt_text')
        {
            return $this->dbColumns['txt_text'];
        }
        else
        {
            return parent::getValue($columnName, $format);
        }
    }

	/** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
	 *  a new record or if only an update is neccessary. The update statement will only update
	 *  the changed columns. If the table has columns for creator or editor than these column
	 *  with their timestamp will be updated.
	 *  For new records the organization will be set per default.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
	 */
    public function save($updateFingerPrint = true)
    {
        if($this->new_record && strlen($this->getValue('txt_org_id')) == 0)
        {
            // Insert
            global $gCurrentOrganization;
            $this->setValue('txt_org_id', $gCurrentOrganization->getValue('org_id'));
        }
        parent::save($updateFingerPrint);
    }    
}
?>