<?php
/**
 ***********************************************************************************************
 * Class manages a data array
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class creates a list configuration object. With this object it's possible
 * to manage the configuration in the database. You can easily create new lists,
 * add new columns or remove columns. The object will only list columns of the configuration
 * which the current user is allowed to view.
 */
class DataArray
{
    /**
     * @var array<string,string> Array with all data that should be handled in this class
     */
    protected $data = array();

    /**
     * Constructor that will create an object to handle the configuration of lists.
     */
    public function __construct()
    {
    }

    public function setDataByArray(array $dataArray)
    {
        $this->data = array_merge($this->data, $dataArray);
    }
    public function setDataBySql(string $sql, array $parameters = array())
    {
        global $gDb;

        $listStatement = $gDb->queryPrepared($sql, $parameters);
        $dataSql = $listStatement->fetchAll(\PDO::FETCH_ASSOC);
        $this->data = array_merge($this->data, $dataSql);
    }
}
