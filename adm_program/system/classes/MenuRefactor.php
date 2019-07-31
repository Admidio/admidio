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

/**
 * Create, modify and display menus. Each menu item is defined by
 *
 *      - $id   : identifier of the menu item
 *      - $link : URL, relative to the admidio root directory, starting with a /
 *                or full URL with http or https protocol
 *      - $text : menu text
 *      - $icon : URL, relative to the theme plugin, starting with a /
 *              : or full URL with http or https protocol
 *      - $desc : (optional) long description of the menu item
 */
class MenuRefactor
{
    /**
     * @var array Array with the main nodes and their entries
     */
    protected $menuNodes = array();

    public function __construct()
    {
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
     * Initialise the member parameters
     */
    public function clear()
    {
        $this->menuNodes = array();
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
     * Create the html code of the menu as a list. There are different
     * parameters to change the look of the menu.
     * @param bool $mediaView If set to true than the menu will be shown in the style of bootstrap media object
     *                        https://getbootstrap.com/docs/4.3/components/media-object/
     * @return string Html code of the menu.
     */
    public function getHtml($mediaView = false)
    {
        $html = '';

        foreach($this->menuNodes as $menuNode)
        {
            $html .= $menuNode->getHtml($mediaView);
        }

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

    /**
     * Create the html menu from the internal array that must be filled before.
     * You have the option to create a simple menu with icon and link or
     * a more complex menu with submenu and description text.
     * @param bool $complex Create a @b simple menu as default. If you set the param to **true**
     *                      then you will create a menu with submenus and description
     * @return string Return the html code of the form.
     */
    public function show($complex = false)
    {
        if (count($this->items) === 0)
        {
            return '';
        }

        $html = '';

        if ($complex)
        {
            $html .= '<h2 id="head_'.$this->id.'_complex">'.$this->title.'</h2>';
            $html .= '<ul id="menu_'.$this->id.'_complex" class="list-unstyled admidio-media-menu">'; // or class="media-list"
        }
        else
        {
            $html .= '<h3 id="head_'.$this->id.'">'.$this->title.'</h3>';
            $html .= '<ul id="menu_'.$this->id.'" class="list-unstyled admidio-menu btn-group-vertical">';
        }

        // now create each menu item
        foreach($this->items as $item)
        {
            if ($complex)
            {
                if($item['id'] !== 'overview')
                {
                    $html .= '
                        <li class="media">
                            <div class="media-left">
                                <a id="menu_'.$this->id.'_'.$item['id'].'" href="'.$item['link'].'">
                                    <i class="fas fa-fw '.$item['icon'].' fa-2x"></i>
                                </a>
                            </div>
                            <div class="media-body">
                                <h4 class="media-heading">
                                    <a id="lmenu_'.$this->id.'_'.$item['id'].'" href="'.$item['link'].'">'.$item['text'].'</a>
                                </h4>
                                <p>'.$item['desc'].'</p>
                            </div>
                        </li>'; // closes "div.media-body" and "li.media"
                }
            }
            else
            {
                $iconHtml = Image::getIconHtml($item['icon'], $item['text']);
                $html .= '
                    <li>
                        <a id="lmenu_'.$this->id.'_'.$item['id'].'" class="btn" href="'.$item['link'].'">
                            ' . $iconHtml . $item['text'] . '
                        </a>
                    </li>';
            }
        }

        $html .= '</ul>'; // closes main-menu "menu.list-unstyled"

        return $html;
    }
}
