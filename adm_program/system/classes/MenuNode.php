<?php
/**
 ***********************************************************************************************
 * Class manages the entries of one menu node
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
class MenuNode
{
    protected $textId;
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
    public function countEntries()
    {
        return count($this->nodeEntries);
    }

    public function getHtmlSidebar()
    {
        $html = '';

        if($this->countEntries() > 0)
        {
            $html .= '<h3 id="head_'.$this->textId.'">'.$this->name.'</h3>';
            $html .= '<ul id="menu_'.$this->textId.'" class="list-unstyled admidio-menu btn-group-vertical">';

            foreach($this->nodeEntries as $menuEntry)
            {
                $iconHtml = Image::getIconHtml($menuEntry['men_icon'], $menuEntry['men_name']);
                $html .= '
                    <li>
                        <a id="lmenu_'.$this->textId.'_'.$menuEntry['men_name_intern'].'" class="btn" href="'.$menuEntry['men_url'].'">
                            ' . $iconHtml . $menuEntry['men_name'] . '
                        </a>
                    </li>';

            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Load the menu node from the database table adm_menu
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
                // Read current roles rights of the menu
                $displayMenu = new RolesRights($gDb, 'menu_view', $node['men_id']);
                $rolesDisplayRight = $displayMenu->getRolesIds();

                // check for right to show the menu
                if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                {
                    continue;
                }
/*
                $menuName = Language::translateIfTranslationStrId($node['men_name']);
                $menuDescription = Language::translateIfTranslationStrId($node['men_description']);
                $menuUrl = $node['men_url'];

                if (strlen($node['men_icon']) > 2)
                {
                    $menuIcon = $node['men_icon'];
                }
*/
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

                $this->nodeEntries[$node['men_id']] = $node;
            }
        }
    }
}
