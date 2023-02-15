<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
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
     * @param string $nodeTextId A unique id for this menu node.
     * @param string $nodeName   The name of this menu node that should be displayed for the user
     */
    public function __construct($nodeTextId, $nodeName)
    {
        $this->textId = $nodeTextId;
        $this->name   = Language::translateIfTranslationStrId($nodeName);
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
     * Add a new item to this menu node. If a dropdown menu item should be created than $parentMenuItemId must be set
     * to each entry of the dropdown. If a badge should be shown at this menu item than set the $badgeCount.
     * @param string $id          A id string for the menu item. That will be used as html id tag.
     *                            It should be unique within this menu node.
     * @param string $name        Name of the menu node that will also shown in the menu
     * @param string $url         The url of this menu item that will be called if someone click the menu item
     * @param string $icon        An icon that will be shown together with the name in the menu
     * @param string $parentMenuItemId The id of the parent item to which this item will be added.
     * @param string $badgeCount  If set > 0 than a small badge with the number will be shown after the menu item name
     * @param string $description A optional description of the menu node that could be shown in some output cases
     * @param string $componentId Optional the component id could be set
     */
    public function addItem($id, $name, $url, $icon, $parentMenuItemId = '', $badgeCount = 0, $description = '', $componentId = 0)
    {
        $node['men_id'] = $this->count();
        $node['men_name_intern'] = $id;
        $node['men_com_id'] = $componentId;

        // translate name and description
        $node['men_name'] = Language::translateIfTranslationStrId($name);
        $node['men_description'] = Language::translateIfTranslationStrId((string) $description);

        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $url) === 0 && strpos($url, 'javascript:') !== 0) {
            $url = ADMIDIO_URL . $url;
        }
        $node['men_url'] = $url;

        if ((string) $icon === '') {
            $icon = 'fa-trash-alt invisible';
        }
        $node['men_icon'] = $icon;
        $node['badge_count'] = $badgeCount;

        if ($parentMenuItemId === '') {
            $this->nodeEntries[$node['men_name_intern']] = $node;
        } else {
            $this->nodeEntries[$parentMenuItemId]['sub_items'][] = $node;
        }
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
     * Create the html code of the menu node as a html list. If a node has sub items than
     * a dropdown will be created.
     * @param bool $mainMenu Flag, if the menu node should be added to the main menu
     * @return string Html code of the menu.
     */
    public function getHtml($mainMenu = false)
    {
        $html = '';
        $linkClasses = '';

        if ($this->count() > 0) {
            if($mainMenu) {
                $html .= '<div class="admidio-menu-header">'.$this->name.'</div>
                            <ul class="nav admidio-menu-node flex-column mb-0">';
            } else {
                $html .= '<ul class="nav admidio-menu-function-node">';
                $linkClasses = ' btn btn-secondary ';
            }

            foreach ($this->nodeEntries as $menuEntry) {
                $htmlBadge = '';
                $htmlIcon = Image::getIconHtml((string) $menuEntry['men_icon'], $menuEntry['men_name']);

                if ($menuEntry['badge_count'] > 0) {
                    $htmlBadge = '<span class="badge badge-light">' . $menuEntry['badge_count'] . '</span>';
                }

                if (isset($menuEntry['sub_items'])) {
                    $html .= '
                    <li class="nav-item dropdown">
                        <a id="'.$menuEntry['men_name_intern'].'" class="nav-link ' . $linkClasses . ' dropdown-toggle" data-toggle="dropdown"
                            href="#" role="button" aria-haspopup="true" aria-expanded="false">
                            ' . $htmlIcon . $menuEntry['men_name'] . $htmlBadge . '
                        </a>
                        <div class="dropdown-menu dropdown-menu-left">';
                    foreach ($menuEntry['sub_items'] as $subMenuEntry) {
                        $htmlSubBadge = '';
                        $htmlSubIcon = Image::getIconHtml((string) $subMenuEntry['men_icon'], $subMenuEntry['men_name']);

                        if ($subMenuEntry['badge_count'] > 0) {
                            $htmlSubBadge = '<span class="badge badge-light">' . $subMenuEntry['badge_count'] . '</span>';
                        }

                        $html .= '
                                <a id="'.$subMenuEntry['men_name_intern'].'" class="dropdown-item" href="'.$subMenuEntry['men_url'].'">
                                    ' . $htmlSubIcon . $subMenuEntry['men_name'] . $htmlSubBadge . '
                                </a>';
                    }
                    $html .= '</div>
                    </li>';
                } else {
                    $html .= '
                    <li class="nav-item">
                        <a id="'.$menuEntry['men_name_intern'].'" class="nav-link ' . $linkClasses . '" href="'.$menuEntry['men_url'].'">
                            ' . $htmlIcon . $menuEntry['men_name'] . $htmlBadge . '
                        </a>
                    </li>';
                }
            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Load all entries of that node from the database table **adm_menu**. Therefore each entry
     * must have stored the $nodeId as the mem_mem_id_parent. The entries will be stored within
     * the internal array $nodeEntries.
     * @param int $nodeId The database id of the node menu entry
     */
    public function loadFromDatabase($nodeId)
    {
        global $gDb, $gValidLogin, $gL10n;

        $sql = 'SELECT men_id, men_com_id, men_name_intern, men_name, men_description, men_url, men_icon, com_name_intern
                  FROM '.TBL_MENU.'
             LEFT JOIN '.TBL_COMPONENTS.'
                    ON com_id = men_com_id
                 WHERE men_men_id_parent =  ? -- $nodeId
              ORDER BY men_men_id_parent DESC, men_order';

        $nodesStatement = $gDb->queryPrepared($sql, array($nodeId));

        while ($node = $nodesStatement->fetch(PDO::FETCH_ASSOC)) {
            if ((int) $node['men_com_id'] === 0 || Component::isVisible($node['com_name_intern'])) {
                if ($this->menuItemIsVisible($node['men_id'])) {
                    $badgeCount = 0;

                    // special case because there are different links if you are logged in or out for mail
                    if ($gValidLogin && $node['men_name_intern'] === 'mail') {
                        // get number of unread messages for user
                        $message = new TableMessage($gDb);
                        $badgeCount = $message->countUnreadMessageRecords($GLOBALS['gCurrentUserId']);

                        $menuUrl  = ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php';
                        $menuIcon = 'fa-comments';
                        $menuName = $gL10n->get('SYS_MESSAGES');
                    } else {
                        $menuUrl  = $node['men_url'];
                        $menuIcon = $node['men_icon'];
                        $menuName = $node['men_name'];
                    }

                    $this->addItem($node['men_name_intern'], $menuName, $menuUrl, $menuIcon, '', $badgeCount, $node['men_description'], $node['men_com_id']);
                }
            }
        }

        // if only the overview entry exists, than don't show anly menu item
        if (count($this->nodeEntries) === 1 && $this->nodeEntries[key($this->nodeEntries)]['men_name_intern'] === 'overview') {
            $this->nodeEntries = array();
        }
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

        if ($menuId > 0) {
            // Read current roles rights of the menu
            $displayMenu = new RolesRights($gDb, 'menu_view', $menuId);
            $rolesDisplayRight = $displayMenu->getRolesIds();

            // check for right to show the menu
            if (count($rolesDisplayRight) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the translated name of this node.
     * @param string $name Translated name of the node.
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
