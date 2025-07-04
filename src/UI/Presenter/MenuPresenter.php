<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Menu\Service\MenuService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Roles\Entity\RolesRights;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new MenuPresenter('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MenuPresenter extends PagePresenter
{
    /**
     * @var string UUID of the menu entry.
     */
    protected string $menuEntryUUID = '';

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $menuEntryUUID UUID of the menu entry.
     * @throws Exception
     */
    public function __construct(string $menuEntryUUID = '')
    {
        $this->menuEntryUUID = $menuEntryUUID;
        parent::__construct($menuEntryUUID);
    }

    /**
     * Create the data for the edit form of a menu entry.
     * @throws Exception
     */
    public function createEditForm(): void
    {
        global $gDb, $gL10n, $gCurrentSession;

        // create menu object
        $menu = new MenuEntry($gDb);

        $this->setHtmlID('adm_menu_configuration_edit');
        if ($this->menuEntryUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_MENU'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_MENU'))));
        }

        // system categories should not be renamed
        $roleViewSet[] = 0;

        if ($this->menuEntryUUID !== '') {
            $menu->readDataByUuid($this->menuEntryUUID);

            // Read current roles rights of the menu
            $display = new RolesRights($gDb, 'menu_view', $menu->getValue('men_id'));
            $roleViewSet = $display->getRolesIds();
        }

        // alle aus der DB aus lesen
        $sqlRoles = 'SELECT rol_id, rol_name, org_shortname, cat_name
                       FROM ' . TBL_ROLES . '
                 INNER JOIN ' . TBL_CATEGORIES . '
                         ON cat_id = rol_cat_id
                 INNER JOIN ' . TBL_ORGANIZATIONS . '
                         ON org_id = cat_org_id
                      WHERE rol_valid  = true
                        AND rol_system = false
                        AND cat_name_intern <> \'EVENTS\'
                   ORDER BY cat_name, rol_name';
        $rolesViewStatement = $gDb->queryPrepared($sqlRoles);

        $parentRoleViewSet = array();
        while ($rowViewRoles = $rolesViewStatement->fetch()) {
            // Each role is now added to this array
            $parentRoleViewSet[] = array(
                $rowViewRoles['rol_id'],
                $rowViewRoles['rol_name'] . ' (' . $rowViewRoles['org_shortname'] . ')',
                $rowViewRoles['cat_name']
            );
        }

        ChangelogService::displayHistoryButton($this, 'menu', 'menu', !empty($menuUUID), array('uuid' => $this->menuEntryUUID));

        // show form
        $form = new FormPresenter(
            'adm_menu_edit_form',
            'modules/menu.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('uuid' => $this->menuEntryUUID, 'mode' => 'save')),
            $this
        );

        $fieldRequired = FormPresenter::FIELD_REQUIRED;
        $fieldDefault = FormPresenter::FIELD_DEFAULT;

        if ($menu->getValue('men_standard')) {
            $fieldRequired = FormPresenter::FIELD_DISABLED;
            $fieldDefault = FormPresenter::FIELD_DISABLED;
        }

        $subMenu = MenuService::subMenu(1, (int)$menu->getValue('men_id'));

        $form->addInput(
            'men_name',
            $gL10n->get('SYS_NAME'),
            htmlentities($menu->getValue('men_name', 'database'), ENT_QUOTES),
            array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'SYS_MENU_NAME_DESC')
        );

        if ($this->menuEntryUUID !== '') {
            $form->addInput(
                'men_name_intern',
                $gL10n->get('SYS_INTERNAL_NAME'),
                $menu->getValue('men_name_intern'),
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
            );
        }

        $form->addMultilineTextInput(
            'men_description',
            $gL10n->get('SYS_DESCRIPTION'),
            $menu->getValue('men_description'),
            2,
            array('maxLength' => 4000)
        );
        $form->addSelectBox(
            'men_men_id_parent',
            $gL10n->get('SYS_MENU_LEVEL'),
            $subMenu,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => (int)$menu->getValue('men_men_id_parent')
            )
        );

        if (!$menu->getValue('men_standard')) {
            $sql = 'SELECT com_id, com_name
                      FROM ' . TBL_COMPONENTS . '
                  ORDER BY com_name';
            $form->addSelectBoxFromSql(
                'men_com_id',
                $gL10n->get('SYS_MODULE_RIGHTS'),
                $gDb,
                $sql,
                array(
                    'property' => $fieldDefault,
                    'defaultValue' => (int)$menu->getValue('men_com_id'),
                    'helpTextId' => 'SYS_MENU_MODULE_RIGHTS_DESC'
                )
            );
            $form->addSelectBox(
                'menu_view',
                $gL10n->get('SYS_VISIBLE_FOR'),
                $parentRoleViewSet,
                array(
                    'property' => $fieldDefault,
                    'defaultValue' => $roleViewSet,
                    'multiselect' => true,
                    'helpTextId' => 'SYS_MENU_RESTRICT_VISIBILITY'
                )
            );
        }

        if ((bool)$menu->getValue('men_node') === false) {
            $form->addInput(
                'men_url',
                $gL10n->get('SYS_URL'),
                $menu->getValue('men_url'),
                array('maxLength' => 2000, 'property' => $fieldRequired)
            );
        }

        $form->addInput(
            'men_icon',
            $gL10n->get('SYS_ICON'),
            $menu->getValue('men_icon'),
            array(
                'maxLength' => 100,
                'helpTextId' => $gL10n->get('SYS_ICON_FONT_DESC', array('<a href="https://icons.getbootstrap.com/" target="_blank">', '</a>')),
                'class' => 'form-control-small'
            )
        );
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $this->smarty->assign('userCreatedName', $menu->getNameOfCreatingUser());
        $this->smarty->assign('userCreatedTimestamp', $menu->getValue('men_timestamp_create'));
        $this->smarty->assign('lastUserEditedName', $menu->getNameOfLastEditingUser());
        $this->smarty->assign('lastUserEditedTimestamp', $menu->getValue('men_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for a form to add a new sub-organization to the current organization.
     * @throws Exception|\Smarty\Exception
     */
    public function createList(): void
    {
        global $gCurrentSession, $gL10n, $gDb;

        $this->setHtmlID('adm_menu_configuration');
        $this->setHeadline($gL10n->get('SYS_MENU'));

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
            $(".admidio-menu-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/menu.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url.indexOf("mode=delete") !== -1) {
                    // wait for callUrlHideElement to finish hiding the element
                    setTimeout(function() {
                        updateMoveActions("tbody.admidio-sortable", "adm_menu_entry", "admidio-menu-move");
                    }, 1000);
                } else {
                    updateMoveActions("tbody.admidio-sortable", "adm_menu_entry", "admidio-menu-move");
                }
            });
            
            updateMoveActions("tbody.admidio-sortable", "adm_menu_entry", "admidio-menu-move");
            ', true
        );

        // define link to create new menu
        $this->addPageFunctionsMenuItem(
            'menu_item_menu_new',
            $gL10n->get('SYS_CREATE_ENTRY'),
            ADMIDIO_URL . FOLDER_MODULES . '/menu.php?mode=edit',
            'bi-plus-circle-fill'
        );

        ChangelogService::displayHistoryButton($this, 'menu', 'menu');

        $menuService = new MenuService($gDb);
        $data = $menuService->getData();
        $templateRowMenuParent = array();

        foreach($data as $menuParentEntry) {
            $templateEntries = array();

            foreach($menuParentEntry['entries'] as $menuEntry) {
                $templateRowMenu = array(
                    'uuid' => $menuEntry['men_uuid'],
                    'name' => Language::translateIfTranslationStrId((string)$menuEntry['men_name']),
                    'description' => Language::translateIfTranslationStrId((string)$menuEntry['men_description']),
                    'standard' => $menuEntry['men_standard'],
                    'icon' => $menuEntry['men_icon'],
                    'url' => $menuEntry['men_url']
                );

                // add root path to link unless the full URL is given
                if (preg_match('/^http(s?):\/\//', $menuEntry['men_url']) === 0) {
                    $templateRowMenu['urlLink'] = ADMIDIO_URL . $menuEntry['men_url'];
                } else {
                    $templateRowMenu['urlLink'] = $menuEntry['men_url'];
                }

                $templateRowMenu['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('mode' => 'edit', 'uuid' => $menuEntry['men_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT')
                );

                // don't allow delete for standard menus
                if (!$menuEntry['men_standard']) {
                    $templateRowMenu['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'adm_menu_entry_' . $menuEntry['men_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('mode' => 'delete', 'uuid' => $menuEntry['men_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($templateRowMenu['name'])),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE')
                    );
                }

                $templateEntries[] = $templateRowMenu;
            }

            if(count($templateEntries) >0) {
                $templateRowMenuParent[] = array(
                    'uuid' => $menuParentEntry['men_uuid'],
                    'name' => Language::translateIfTranslationStrId((string)$menuParentEntry['men_name']),
                    'entries' => $templateEntries
                );
            }
        }

        $this->smarty->assign('list', $templateRowMenuParent);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/menu.list.tpl');
    }
}
