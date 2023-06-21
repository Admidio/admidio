<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_relations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class manages the set, update and delete in the table adm_user_relations
 */
class TableMessageContent extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $mscId    The recordset of the relation with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $mscId = 0)
    {
        parent::__construct($database, TBL_MESSAGES_CONTENT, 'msc', $mscId);
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
        if ($columnName === 'msc_message') {
            if ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['msc_message']));
            } elseif($this->dbColumns['msc_message'] != strip_tags($this->dbColumns['msc_message'])) {
                // text contains html
                $value = htmlspecialchars_decode(stripslashes(SecurityUtils::encodeHTML($this->dbColumns['msc_message'])));
            } else {
                // simple plain text than replace the line breaks
                $value = nl2br(SecurityUtils::encodeHTML($this->dbColumns['msc_message']));
            }

            return $value;
        }

        return parent::getValue($columnName, $format);
    }
}
