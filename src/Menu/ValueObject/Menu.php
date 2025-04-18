<?php
namespace Admidio\Menu\ValueObject;

use Admidio\Infrastructure\Exception;

/**
 * @brief Create menu from database and serve several output formats
 *
 * This class will read the menu structure from the database table **adm_menu** and stores each main
 * node as a MenuNode object within an internal array. There are several output methods to use the
 * menu within the layout. You can create a simple html list, a bootstrap media object list or
 * add it to an existing navbar.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Menu
{
    /**
     * @var array Multidimensional array with the complete menu structure.
     */
    protected array $menuItems;
    /**
     * @var bool Flag to remember if the menu must be reloaded from database
     */
    protected bool $menuLoaded;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Count the number of main nodes from this menu
     * @return int Number of nodes from this menu
     */
    public function countMainNodes(): int
    {
        return count($this->menuItems);
    }

    /**
     * Returns an array with all menu items. The array has the following structure:
     * Array ( [0] => Array (
     *      [id] => modules
     *      [name] => Module
     *      [items] => Array (
     *          [overview] => Array (
     *              [id] => overview
     *              [name] => Übersicht
     *              [description] =>
     *              [url] => http://localhost/GitHub/admidio/modules/overview.php
     *              [icon] => bi-house-door-fill
     *              [badgeCount] => 0 )
     *          [announcements] => Array (
     *              [id] => announcements
     *              [name] => Ankündigungen
     *              ... )
     *          )
     *    )
     * )
     * @return array Array with all entries of this node
     * @throws Exception
     */
    public function getAllMenuItems(): array
    {
        if (!$this->menuLoaded) {
            $this->loadFromDatabase();
        }

        return $this->menuItems;
    }

    /**
     * Initialise the member parameters of this class. This method should also be called if
     * the menu structure should be reloaded from database.
     */
    public function initialize()
    {
        $this->menuItems  = array();
        $this->menuLoaded = false;
    }

    /**
     * Load the menu from the database table adm_menu
     * @throws Exception
     */
    public function loadFromDatabase()
    {
        global $gDb;

        $this->menuLoaded = true;

        $sql = 'SELECT men_id, men_name, men_name_intern
                  FROM '.TBL_MENU.'
                 WHERE men_men_id_parent IS NULL
              ORDER BY men_order';

        $mainNodesStatement = $gDb->queryPrepared($sql);

        while ($mainNodes = $mainNodesStatement->fetch()) {
            $menuNodes = new MenuNode($mainNodes['men_name_intern'], $mainNodes['men_name']);
            $menuNodes->loadFromDatabase($mainNodes['men_id']);

            if ($menuNodes->count() > 0) {
                $this->menuItems[] = array(
                    'id' => $mainNodes['men_name_intern'],
                    'name' => $menuNodes->getName(),
                    'items' => $menuNodes->getAllItems());
            }
        }
    }
}
