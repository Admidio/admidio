<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_texts
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse dient dazu ein Textobjekt zu erstellen.
 * Texte koennen ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung.
 */
class TableText extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_texts.
     * If the id is set than the specific text will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string   $name     The recordset of the text with this name will be loaded.
     *                           If name isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $name = '')
    {
        parent::__construct($database, TBL_TEXTS, 'txt', $name);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string Returns the value of the database column.
     *                    If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        if ($columnName === 'txt_text') {
            return $this->dbColumns['txt_text'];
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if ($this->newRecord && $this->getValue('txt_org_id') === '') {
            // Insert
            $this->setValue('txt_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }
}
