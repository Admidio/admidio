<?php
namespace Admidio\Menu\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Roles\Entity\RolesRights;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MenuService
{
    protected MenuEntry $menuRessource;
    protected Database $db;
    protected string $UUID;

    /**
     * Constructor that will create an object of a recordset of the table adm_lists.
     * If the id is set than the specific list will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $menuUUID UUID if the menu ressource that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $menuUUID = '')
    {
        $this->db = $database;
        $this->menuRessource = new MenuEntry($database);
        $this->UUID = $menuUUID;

        if ($menuUUID !== '') {
            $this->menuRessource->readDataByUuid($menuUUID);
        }
    }

    /**
     * Read the parent menu entries in an array and adds a sub array with all entries of the parent.
     * The returned array contains the following information:
     * men_id, men_uuid, men_name,
     * entries [men_id, men_uuid, men_men_id_parent, men_name, men_description, men_standard, men_url, men_icon]
     * @return array Returns an array with all menu entries and their parents.
     * @throws Exception
     */
    public function getData(): array
    {
        global $gDb;

        $templateRowMenuParent = array();

        $sql = 'SELECT men_id, men_uuid, men_name
                  FROM ' . TBL_MENU . '
                 WHERE men_men_id_parent IS NULL
              ORDER BY men_order';
        $mainMenStatement = $gDb->queryPrepared($sql);

        while ($mainMen = $mainMenStatement->fetch()) {
            $sql = 'SELECT men_id, men_uuid, men_men_id_parent, men_name, men_description, men_standard, men_url, men_icon
                      FROM ' . TBL_MENU . '
                     WHERE men_men_id_parent = ? -- $mainMen[\'men_id\']
                  ORDER BY men_men_id_parent DESC, men_order';
            $menuStatement = $gDb->queryPrepared($sql, array($mainMen['men_id']));

            // Get data
            $templateEntries = $menuStatement->fetchAll();

            if(count($templateEntries) >0) {
                $templateRowMenuParent[] = array_merge($mainMen, array('entries' => $templateEntries));
            }
        }

        return $templateRowMenuParent;
    }

    /**
     * Save data from the menu form into the database.
     * @throws Exception
     */
    public function save()
    {
        global $gCurrentSession;

        // check form field input and sanitized it from malicious content
        $menuEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $menuEditForm->validate($_POST);

        // within standard menu items the url should not be changed
        if ($this->menuRessource->getValue('men_standard')) {
            $_POST['men_com_id'] = $this->menuRessource->getValue('men_com_id');
            $_POST['men_url'] = $this->menuRessource->getValue('men_url');
        }

        // check url here because it could be a real url or a relative local url
        if (!StringUtils::strValidCharacters($_POST['men_url'], 'url')
            && !preg_match('=^[^*;:~<>|\"\\\\]+$=', $_POST['men_url'])) {
            throw new Exception('SYS_URL_INVALID_CHAR', array('SYS_URL'));
        }

        $this->db->startTransaction();

        // write form values in menu object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'men_')) {
                $this->menuRessource->setValue($key, $value);
            }
        }

        if($this->menuRessource->save()) {
            // save changed roles rights of the menu
            if (isset($_POST['menu_view'])) {
                $menuViewRoles = array_map('intval', $_POST['menu_view']);
            } else {
                $menuViewRoles = array();
            }

            $rightMenuView = new RolesRights($this->db, 'menu_view', $this->menuRessource->getValue('men_id'));
            $rightMenuView->saveRoles($menuViewRoles);
        }

        $this->db->endTransaction();
    }

    /**
     * Creates an array with the structure of the menu nodes. Each entry has the menu entry name and
     * a prefix of lines that will represent the level of the menu node.
     * @param int $level
     * @param int $menuID
     * @param int|null $parentID
     * @param array<int,string> $menuList
     * @return string[] Returns an array with all parent menu entries
     * @throws Exception
     */
    static function subMenu(int $level, int $menuID, int|null $parentID = null, array $menuList = array()): array
    {
        global $gDb;

        $sqlConditionParentId = '';
        $queryParams = array($menuID);

        // Erfassen des auszugebenden MenuPresenter
        if ($parentID > 0) {
            $sqlConditionParentId .= ' AND men_men_id_parent = ? -- $parentID';
            $queryParams[] = $parentID;
        } else {
            $sqlConditionParentId .= ' AND men_men_id_parent IS NULL';
        }

        $sql = 'SELECT *
                  FROM ' . TBL_MENU . '
                 WHERE men_node = true
                   AND men_id  <> ? -- $menu->getValue(\'men_id\')
                       ' . $sqlConditionParentId;
        $childStatement = $gDb->queryPrepared($sql, $queryParams);

        $parentMenu = new MenuEntry($gDb);
        $einschub = str_repeat('&nbsp;', $level * 3) . '&#151;&nbsp;';

        while ($menuEntry = $childStatement->fetch()) {
            $parentMenu->clear();
            $parentMenu->setArray($menuEntry);

            // add entry to array of all menus
            $menuList[(int)$parentMenu->getValue('men_id')] = $einschub . $parentMenu->getValue('men_name');

            MenuService::subMenu(++$level, $menuID, (int)$parentMenu->getValue('men_id'), $menuList);
        }

        return $menuList;
    }
}
