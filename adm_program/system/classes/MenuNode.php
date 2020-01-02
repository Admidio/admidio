<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2019 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

 /**
 * Create a menu node from database and serve several output formats
 *
 * This class will create a menu node. The data will be read from the database table **adm_menu**.
 * All entries of this menu node will be added to an internal array. There is a method to get
 * a html menu list of that node. This class will be used with the Menu class to read each main
 * node of the menu as a separate MenuNode object.
 *
 * **Code example**
 * ```
 * // create an object for the menu and show a html list
 * $menuNode = new MenuNode('my_internal_name', 'My visible node name');
 * $menuNodes->loadFromDatabase(4711);
 * $html = $menuNode->getHtml();
 * ```
 */
class MenuNode
{
    /**
     * @var array Internal id of the node. Should be the value of mem_name_intern from adm_menu.
     */
    protected $textId;

    /**
     * @var array The name of the node that will be shown as the head of the list. Should be the value of mem_name from adm_menu.
     */
    protected $name;

    /**
     * @var array Array with all entries of this node
     */
    protected $nodeEntries = array();

    /**
     * constructor
     * @param string $nodeName The name of this menu node
     */
    public function __construct($nodeTextId, $nodeName)
    {
        $this->textId = $nodeTextId;
        $this->name   = $nodeName;
    }

    /**
     * Count the number of entries from this node
     * @return int Number of entries from this node
     */
    public function count()
    {
        return count($this->nodeEntries);
    }

    /**
     * Get the entries of this node as an array.
     * @return array Array with all entries of this node
     */
    public function getEntries()
    {
        return $this->nodeEntries;
    }

    /**
     * Get the translated name of this node.
     * @return string Name of this node.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the internal name of this node.
     * @return string The internal name e.g. '''modules''' of this node
     */
    public function getTextId()
    {
        return $this->textId;
    }

    /**
     * Create the html code of the menu as a html list. There are different
     * parameters to change the look of the menu.
     * @param bool $mediaView If set to true than the menu will be shown in the style of bootstrap media object
     *                        https://getbootstrap.com/docs/4.3/components/media-object/
     * @return string Html code of the menu.
     */
    public function getHtml($mediaView = false)
    {
        $html = '';
/*
        if($this->count() > 0)
        {
            $html .= '<h3 id="head_'.$this->textId.'">'.$this->name.'</h3>';

            if($mediaView)
            {
                $html .= '<ul id="menu_'.$this->textId.'" class="list-unstyled admidio-media-menu">';
            }
            else
            {
                $html .= '<ul id="menu_'.$this->textId.'" class="list-unstyled admidio-menu btn-group-vertical">';
            }

            foreach($this->nodeEntries as $menuEntry)
            {
                if($mediaView)
                {
                    if($menuEntry['men_name_intern'] !== 'overview') // overview should not be shown in detailed list, because it's the detailed list
                    {
                        $iconHtml = Image::getIconHtml($menuEntry['men_icon'], $menuEntry['men_name'], 'fa-2x');
                        $html .= '
                        <li class="media">
                            <div class="media-left">
                                <a id="menu_'.$this->textId.'_'.$menuEntry['men_name_intern'].'" href="'.$menuEntry['men_url'].'">
                                    '.$iconHtml.'
                                </a>
                            </div>
                            <div class="media-body">
                                <h4 class="media-heading">
                                    <a id="lmenu_'.$this->textId.'_'.$menuEntry['men_name_intern'].'" href="'.$menuEntry['men_url'].'">'.$menuEntry['men_name'].'</a>
                                </h4>
                                <p>'.$menuEntry['men_description'].'</p>
                            </div>
                        </li>';
                    }
                }
                else
                {
                    $iconHtml = Image::getIconHtml($menuEntry['men_icon'], $menuEntry['men_name']);
                    $html .= '
                    <li>
                        <a id="lmenu_'.$this->textId.'_'.$menuEntry['men_name_intern'].'" class="btn" href="'.$menuEntry['men_url'].'">
                            ' . $iconHtml . $menuEntry['men_name'] . '
                        </a>
                    </li>';
                }
            }

            $html .= '</ul>';
        }*/

        if($this->count() > 0)
        {
            $html .= '<div class="admidio-menu-header">'.$this->name.'</div>
            
            <ul class="nav flex-column bg-white mb-0">';

            foreach($this->nodeEntries as $menuEntry)
            {
                $iconHtml = Image::getIconHtml($menuEntry['men_icon'], $menuEntry['men_name']);
                $html .= '
                <li class="nav-item">
                    <a class="nav-link text-dark font-italic bg-light" href="'.$menuEntry['men_url'].'">
                        ' . $iconHtml . $menuEntry['men_name'] . '
                    </a>
                </li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * This method checks if a special menu item of the current node is visible for the current user.
     * Therefor this method checks if roles are assigned to the menu item and if the current
     * user is a member of at least one of this roles.
     * @param menuId The id of the menu item that should be checked if it's visible.
     * @return bool Return true if the menu item is visible to the current user.
     */
    public function menuItemIsVisible($menuId)
    {
        global $gDb, $gCurrentUser;

        if($menuId > 0)
        {
            // Read current roles rights of the menu
            $displayMenu = new RolesRights($gDb, 'menu_view', $menuId);
            $rolesDisplayRight = $displayMenu->getRolesIds();

            // check for right to show the menu
            if (count($rolesDisplayRight) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Load all entries of that node from the database table **adm_menu**. Therefore each entry
     * must have stored the $nodeId as the mem_mem_id_parent. The entries will be stored within
     * the internal array $nodeEntries.
     * @param int $nodeId The database id of the node menu entry
     */
    public function loadFromDatabase($nodeId)
    {
        global $gDb, $gCurrentUser, $gValidLogin;

        $countMenuNodes = 0;
        $sql = 'SELECT men_id, men_com_id, men_name_intern, men_name, men_description, men_url, men_icon, com_name_intern
                  FROM '.TBL_MENU.'
             LEFT JOIN '.TBL_COMPONENTS.'
                    ON com_id = men_com_id
                 WHERE men_men_id_parent =  ? -- $nodeId
              ORDER BY men_men_id_parent DESC, men_order';

        $nodesStatement = $gDb->queryPrepared($sql, array($nodeId));

        while ($node = $nodesStatement->fetch(PDO::FETCH_ASSOC))
        {
            if ((int) $node['men_com_id'] === 0 || Component::isVisible($node['com_name_intern']))
            {
                if($this->menuItemIsVisible($node['men_id']))
                {
                    // special case because there are different links if you are logged in or out for mail
                    /*if ($gValidLogin && $node['men_name_intern'] === 'mail')
                    {
                        $unreadBadge = self::getUnreadMessagesBadge();
    
                        $menuUrl = ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php';
                        $menuIcon = 'fa-comments';
                        $menuName = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                    }*/
    
                    // translate name and description
                    $node['men_name'] = Language::translateIfTranslationStrId($node['men_name']);
                    $node['men_description'] = Language::translateIfTranslationStrId($node['men_description']);
    
                    // add root path to link unless the full URL is given
                    if (preg_match('/^http(s?):\/\//', $node['men_url']) === 0)
                    {
                        $node['men_url'] = ADMIDIO_URL . $node['men_url'];
                    }
    
                    if (strlen($node['men_icon']) === 0)
                    {
                        $node['men_icon'] = 'fa-trash-alt admidio-opacity-0';
                    }
    
                    $this->nodeEntries[$node['men_id']] = $node;
                }
            }
        }
        
        // if only the overview entry exists, than don't show anly menu item
        if(count($this->nodeEntries) === 1 && $this->nodeEntries[key($this->nodeEntries)]['men_name_intern'] === 'overview')
        {
            $this->nodeEntries = array();
        }
    }
}
