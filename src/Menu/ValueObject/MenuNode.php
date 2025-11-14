<?php
namespace Admidio\Menu\ValueObject;

use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Service\RegistrationService;
use Admidio\Roles\Entity\RolesRights;

/**
 * @brief Create a menu node from database and serve several output formats
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
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MenuNode
{
    /**
     * @var string Internal id of the node. Should be the value of mem_name_intern from adm_menu.
     */
    protected string $textId;

    /**
     * @var string The name of the node that will be shown as the head of the list. Should be the value of mem_name from adm_menu.
     */
    protected string $name;

    /**
     * @var array Array with all entries of this node
     */
    protected array $nodeEntries = array();

    /**
     * constructor
     * @param string $nodeTextId A unique id for this menu node.
     * @param string $nodeName The name of this menu node that should be displayed for the user
     * @throws Exception
     */
    public function __construct(string $nodeTextId, string $nodeName = '')
    {
        $this->textId = $nodeTextId;
        if ($nodeName !== '') {
            $this->name = Language::translateIfTranslationStrId($nodeName);
        }
    }

    /**
     * Count the number of entries from this node
     * @return int Number of entries from this node
     */
    public function count(): int
    {
        return count($this->nodeEntries);
    }

    /**
     * Add a new item to this menu node. If a dropdown menu item should be created than $parentMenuItemId must be set
     * to each entry of the dropdown. If a badge should be shown at this menu item than set the $badgeCount.
     * @param string $id ID string for the menu item. That will be used as html id tag.
     *                            It should be unique within this menu node.
     * @param string $name Name of the menu node that will also be shown in the menu
     * @param string $url The url of this menu item that will be called if someone click the menu item
     * @param string $icon An icon that will be shown together with the name in the menu
     * @param string $parentMenuItemId The id of the parent item to which this item will be added.
     * @param int $badgeCount If set > 0 than a small badge with the number will be shown after the menu item name
     * @param string $description An optional description of the menu node that could be shown in some output cases
     * @throws Exception
     */
    public function addItem(string $id, string $name, string $url, string $icon, string $parentMenuItemId = '', int $badgeCount = 0, string $description = '')
    {
        $node['id'] = $id;

        // translate name and description
        $node['name'] = Language::translateIfTranslationStrId($name);
        $node['description'] = Language::translateIfTranslationStrId($description);

        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $url) === 0 && strpos($url, 'javascript:') !== 0) {
            $url = ADMIDIO_URL . $url;
        }
        $node['url'] = $url;

        if ($icon === '') {
            $icon = 'bi bi-trash invisible';
        }
        if (strpos($icon, 'bi-') !== false) {
            $node['icon'] = 'bi ' . $icon;
        } elseif (strpos($icon, 'bi') !== false) {
            $node['icon'] = $icon;
        } else {
            $node['icon'] = 'bi bi-' . $icon;
        }
        $node['badgeCount'] = $badgeCount;

        if ($parentMenuItemId === '') {
            $this->nodeEntries[$id] = $node;
        } else {
            $this->nodeEntries[$parentMenuItemId]['items'][] = $node;
        }
    }

    /**
     * Get all the items of this node as an array.
     * @return array Array with all entries of this node
     */
    public function getAllItems(): array
    {
        return $this->nodeEntries;
    }

    /**
     * Get the translated name of this node.
     * @return string Name of this node.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Load all entries of that node from the database table **adm_menu**. Therefore, each entry
     * must have stored the $nodeId as the mem_mem_id_parent. The entries will be stored within
     * the internal array $nodeEntries.
     * @param int $nodeId The database id of the node menu entry
     * @throws Exception
     */
    public function loadFromDatabase(int $nodeId)
    {
        global $gDb, $gValidLogin;

        $sql = 'SELECT men_id, men_com_id, men_name_intern, men_name, men_description, men_url, men_icon, com_name_intern
                  FROM '.TBL_MENU.'
             LEFT JOIN '.TBL_COMPONENTS.'
                    ON com_id = men_com_id
                 WHERE men_men_id_parent =  ? -- $nodeId
              ORDER BY men_men_id_parent DESC, men_order';

        $nodesStatement = $gDb->queryPrepared($sql, array($nodeId));

        while ($node = $nodesStatement->fetch(\PDO::FETCH_ASSOC)) {
            if ((int) $node['men_com_id'] === 0 || Component::isVisible($node['com_name_intern'])) {
                if ($this->menuItemIsVisible($node['men_id'])) {
                    $badgeCount = 0;

                    if ($node['men_name_intern'] === 'messages' && $gValidLogin) {
                        // get number of unread messages for user
                        $message = new \Admidio\Messages\Entity\Message($gDb);
                        $badgeCount = $message->countUnreadMessageRecords($GLOBALS['gCurrentUserId']);
                    } elseif ($node['men_name_intern'] === 'registration') {
                        $registration = new RegistrationService($gDb);
                        $badgeCount = count($registration->findAll());
                    }

                    $this->addItem($node['men_name_intern'], $node['men_name'], $node['men_url'], (string) $node['men_icon'], '', $badgeCount, (string) $node['men_description']);
                }
            }
        }

        // if only the overview entry exists, then don't show any menu item
        if (count($this->nodeEntries) === 1 && $this->nodeEntries[key($this->nodeEntries)]['id'] === 'overview') {
            $this->nodeEntries = array();
        }
    }

    /**
     * This method checks if a special menu item of the current node is visible for the current user.
     * Therefor this method checks if roles are assigned to the menu item and if the current
     * user is a member of at least one of these roles.
     * @param int $menuId The id of the menu item that should be checked if it's visible.
     * @return bool Return true if the menu item is visible to the current user.
     * @throws Exception
     */
    public function menuItemIsVisible(int $menuId): bool
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
    public function setName(string $name)
    {
        $this->name = $name;
    }
}
