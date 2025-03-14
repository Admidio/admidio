<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Inventory\ValueObjects\ItemsData;

/**
 * @brief Class manages access to database table adm_files
 *
 * With the given ID a file object is created from the data in the database table **adm_files**.
 * The class will handle the communication with the database and give easy access to the data. New
 * file could be created or existing file could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Item extends Entity
{
    /**
     * @var ItemsData object with current user field structure
     */
    protected ItemsData $mItemsData;
    /**
     * @var int the organization for which the rights are read, could be changed with method **setOrganization**
     */
    protected int $organizationId;
    /**
     * @var bool Flag if the changes to the user data should be handled by the ChangeNotification service.
     */
    protected bool $changeNotificationEnabled;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param ItemsData|null $itemFields An object of the ItemsData class with the profile field structure
     *                                  of the current organization.
     * @param int $itemId The id of the item which should be loaded. If id isn't set than an empty
     *                                  object with no specific item is created.
     * @throws Exception
     */
    public function __construct(Database $database, ?ItemsData $itemsData = null, int $itemId = 0)
    {
        $this->changeNotificationEnabled = true;

        if ($itemsData !== null) {
            $this->mItemsData = clone $itemsData; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            $this->mItemsData = new ItemsData($database, $GLOBALS['gCurrentOrgId']);
        }

        $this->organizationId = $GLOBALS['gCurrentOrgId'];
        // read also data of assigned item data
        $this->connectAdditionalTable(TBL_INVENTORY_DATA, 'ini_id', 'ind_ini_id');

        parent::__construct($database, TBL_INVENTORY_ITEMS, 'ini', $itemId);
    }
}
