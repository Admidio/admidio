<?php
/**
 ***********************************************************************************************
 * Class manages display of menus
 *
 * @copyright 2004-2019 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class Menu
{
    /**
     * @var array Array with the main nodes and their entries
     */
    protected $menuNodes;
    
    /**
     * @var bool Flag to remember if data must be reloaded from database
     */
    protected $loadData;    

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Add the main nodes as a dropdown control to the navbar and assign all
     * entries of the main node as elements of that dropdown.
     * @param HtmlNavbar $navbar The navbar object to which the menu should be added.
     */
    public function addToNavbar(HtmlNavbar &$navbar)
    {
        foreach($this->menuNodes as $menuNode)
        {
            if($menuNode->count() > 0)
            {
                $navbar->addItem(
                    'menu_item_'.$menuNode->getTextId(), '', $menuNode->getName(),
                    'fa-align-justify', 'right', 'navbar', 'admidio-default-menu-item'
                );

                // now add each entry of the node to the navbar dropdown
                foreach($menuNode->getEntries() as $menuEntry)
                {
                    $navbar->addItem(
                        $menuEntry['men_name_intern'], $menuEntry['men_url'], $menuEntry['men_name'], $menuEntry['men_icon'], 'right',
                        'menu_item_'.$menuNode->getTextId(), 'admidio-default-menu-item'
                    );
                }
            }
        }
    }

    /**
     * Initialise the member parameters of this class
     */
    public function initialize()
    {
        $this->menuNodes = array();
        $this->loadData  = true;
    }

    /**
     * Count the number of main nodes from this menu
     * @return int Number of nodes from this menu
     */
    public function countMainNodes()
    {
        if($this->loadData)
        {
            $this->loadFromDatabase();
        }

        return count($this->menuNodes);
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
        if($this->loadData)
        {
            $this->loadFromDatabase();
        }

        $html = '<div class="admidio-menu-list">';

        foreach($this->menuNodes as $menuNode)
        {
            $html .= $menuNode->getHtml($mediaView);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Load the menu from the database table adm_menu
     */
    public function loadFromDatabase()
    {
        global $gDb, $gLogger;

        $countMenuNodes = 0;
        $sql = 'SELECT men_id, men_name, men_name_intern
                  FROM '.TBL_MENU.'
                 WHERE men_men_id_parent IS NULL
              ORDER BY men_order';

        $mainNodesStatement = $gDb->queryPrepared($sql);

        while ($mainNodes = $mainNodesStatement->fetch())
        {
            $this->menuNodes[$countMenuNodes] = new MenuNode($mainNodes['men_name_intern'], $mainNodes['men_name']);
            $this->menuNodes[$countMenuNodes]->loadFromDatabase($mainNodes['men_id']);

            $countMenuNodes++;
        }
        $gLogger->error(print_r($this->menuNodes, true));
    }
}
