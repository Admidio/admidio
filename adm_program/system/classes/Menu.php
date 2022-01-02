<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create menu from database and serve several output formats
 *
 * This class will read the menu structure from the database table **adm_menu** and stores each main
 * node as a MenuNode object within an internal array. There are severel output methods to use the
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
class Menu
{
    /**
     * @var array Array with the main nodes and their entries
     */
    protected $menuNodes;

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
     * Adds an additional menu node with page specific functions to the first place of this menu.
     * An existing page specific functions menu will be removed before the new one is added.
     * @param MenuNode $node A object of the class MenuNode
     */
    public function addFunctionsNode(MenuNode &$node)
    {
        if ($this->functionsNodeAdded) {
            array_shift($this->menuNodes);
        }

        $this->functionsNodeAdded = true;
        array_unshift($this->menuNodes, $node);
    }

    /**
     * Add each main node as a dropdown control to the navbar and assign all
     * entries of the main node as elements of that dropdown.
     * @param HtmlNavbar $navbar The HtmlNavbar object to which the menu should be added.
     */
    public function addToNavbar(HtmlNavbar &$navbar)
    {
        foreach ($this->menuNodes as $menuNode) {
            if ($menuNode->count() > 0) {
                $navbar->addItem(
                    'menu_item_'.$menuNode->getTextId(),
                    '',
                    $menuNode->getName(),
                    'fa-align-justify',
                    'right',
                    'navbar',
                    'admidio-default-menu-item'
                );

                // now add each entry of the node to the navbar dropdown
                foreach ($menuNode->getEntries() as $menuEntry) {
                    $navbar->addItem(
                        $menuEntry['men_name_intern'],
                        $menuEntry['men_url'],
                        $menuEntry['men_name'],
                        $menuEntry['men_icon'],
                        'right',
                        'menu_item_'.$menuNode->getTextId(),
                        'admidio-default-menu-item'
                    );
                }
            }
        }
    }

    /**
     * Count the number of main nodes from this menu
     * @return int Number of nodes from this menu
     */
    public function countMainNodes()
    {
        return count($this->menuNodes);
    }

    /**
     * Initialise the member parameters of this class. This method should also be called if
     * the menu structure should be reladed from database.
     */
    public function initialize()
    {
        $this->menuNodes          = array();
        $this->menuLoaded         = false;
        $this->functionsNodeAdded = false;
    }

    /**
     * Create the html code of the menu as a list. There are different
     * parameters to change the look of the menu.
     * @param bool $mediaView If set to true than the menu will be shown in the style of bootstrap media object
     *                        https://getbootstrap.com/docs/4.3/components/media-object/
     * @return string Html code of the menu.
     */
    public function getHtml($mediaView = false)
    {
        if (!$this->menuLoaded) {
            $this->loadFromDatabase();
        }

        $html = '<nav class="admidio-menu-list collapse" id="admidio-main-menu">';

        foreach ($this->menuNodes as $menuNode) {
            $html .= $menuNode->getHtml($mediaView);
        }

        $html .= '</nav>';

        return $html;
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
