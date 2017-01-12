<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_links
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableWeblink
 *
 * This class creates objects of the database table links.
 * You can read, change and create weblinks in the database.
 */
class TableWeblink extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_links.
     * If the id is set than the specific weblink will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $lnkId    The recordset of the weblink with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $lnkId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'lnk_cat_id');

        parent::__construct($database, TBL_LINKS, 'lnk', $lnkId);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return int|string Returns the value of the database column.
     *                    If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'lnk_description')
        {
            if (!isset($this->dbColumns['lnk_description']))
            {
                $value = '';
            }
            elseif ($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['lnk_description']));
            }
            else
            {
                $value = $this->dbColumns['lnk_description'];
            }
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        // if text is a translation-id then translate it
        if ($columnName === 'cat_name' && $format !== 'database' && strpos($value, '_') === 3)
        {
            $value = $gL10n->get(admStrToUpper($value));
        }

        return $value;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if ($columnName === 'lnk_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }

        if ($columnName === 'lnk_url' && $newValue !== '')
        {
            $newValue = admFuncCheckUrl($newValue);

            if ($newValue === false)
            {
                return false;
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
