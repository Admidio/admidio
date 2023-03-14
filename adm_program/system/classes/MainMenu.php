<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create menu from database and serve several output formats
 *
 * This class will read the menu structure from the database table **adm_menu** and stores each main
 * node as a MenuNode object within an internal array. There are several output methods to use the
 * menu within the layout. You can create a simple html list, a bootstrap media object list or
 * add it to an existing navbar.
 *
 * **Code examples**
 * ```
 * // create an object for the menu and show a html list
 * $menuList = new Menu();
 * $html = $menuList->getHtml();
 *
 *
 * // create an object for the menu and add the menu to the navbar
 * $navbar = new HtmlNavbar('navbar', 'Example');
 * $menuNavbar = new Menu();
 * $menuList->addToNavbar($navbar);
 * ```
 */
class MainMenu
{
    /**
     * @var array Array with the main nodes and their entries
     */
    protected $menuNodes;

    /**
     * @var array Multidimensional array with the complete menu structure.
     */
    protected $menuItems;

    /**
     * @var bool Flag to remember if the menu must be reloaded from database
     */
    protected $menuLoaded;

    /**
     * @var bool Flag to remember if the function node was already added to the menu
     */
    protected $functionsNodeAdded;

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
        return count($this->menuNodes);
    }

    /**
     * Initialise the member parameters of this class. This method should also be called if
     * the menu structure should be reloaded from database.
     */
    public function initialize()
    {
        $this->menuNodes          = array();
        $this->menuItems        = array();
        $this->menuLoaded         = false;
        $this->functionsNodeAdded = false;
    }

    /**
     * Returns an array with all menu items. The array has the following structure:
     * Array ( [0] => Array (
     *      [id] => modules
     *      [name] => Module
     *      [entries] => Array (
     *          [overview] => Array (
     *              [id] => overview
     *              [name] => Übersicht
     *              [description] =>
     *              [url] => http://localhost/GitHub/admidio/adm_program/overview.php
     *              [icon] => fa-home [badge_count] => 0 )
     *          [announcements] => Array (
     *              [id] => announcements
     *              [name] => Ankündigungen
     *              ... )
     *          )
     *    )
     * )
     * @return array Array with all entries of this node
     */
    public function getAllMenuItems(): array
    {
        if (!$this->menuLoaded) {
            $this->loadFromDatabase();
        }

        return $this->menuItems;
    }

    /**
     * Create the html code of the menu as a list. The different menu nodes will be created by the html method
     * of the subclass MenuNode.
     * @return string Html code of the menu.
     */
    public function getHtml(): string
    {
        if (!$this->menuLoaded) {
            $this->loadFromDatabase();
        }

        $html = '<nav class="admidio-menu-list collapse" id="admidio-main-menu">';

        foreach ($this->menuNodes as $menuNode) {
            $html .= $menuNode->getHtml(true);
        }

        $html .= '</nav>';

        return $html;
    }

    /**
     * Get all MenuNodes of the current Menu.
     * It also loads the menu from the adm_menu database table if it has not already been done.
     * @return array Array with the main nodes and their entries
     */
    public function getAllNodes(): array
    {
        if (!$this->menuLoaded) {
            $this->loadFromDatabase();
        }

        return $this->menuNodes;
    }

    /**
     * Load the menu from the database table adm_menu
     */
    public function loadFromDatabase()
    {
        global $gDb;

        $countMenuNodes = $this->countMainNodes();
        $this->menuLoaded = true;

        $sql = 'SELECT men_id, men_name, men_name_intern
                  FROM '.TBL_MENU.'
                 WHERE men_men_id_parent IS NULL
              ORDER BY men_order';

        $mainNodesStatement = $gDb->queryPrepared($sql);

        while ($mainNodes = $mainNodesStatement->fetch()) {
            $countMenuNodes++;
            $this->menuNodes[$countMenuNodes] = new MenuNode($mainNodes['men_name_intern'], $mainNodes['men_name']);
            $this->menuNodes[$countMenuNodes]->loadFromDatabase($mainNodes['men_id']);
            $this->menuItems[]= array(
                'id' => $mainNodes['men_name_intern'],
                'name' => $mainNodes['men_name'],
                'entries' => $this->menuNodes[$countMenuNodes]->getEntries());
        }
    }

    /**
     * Removes the functions node from the current menu
     */
    public function removeFunctionsNode()
    {
        if ($this->functionsNodeAdded) {
            array_shift($this->menuNodes);
            $this->functionsNodeAdded = false;
        }
    }
}
