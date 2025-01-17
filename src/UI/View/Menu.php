<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Menu\Service\MenuService;
use Admidio\UI\Component\Form;
use HtmlPage;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new Menu('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Menu extends HtmlPage
{
    /**
     * Create the data for the edit form of a menu entry.
     * @param string $menuUUID UUID of the menu entry that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $menuUUID = '')
    {
        global $gDb, $gL10n, $gCurrentSession;

        // create menu object
        $menu = new MenuEntry($gDb);

        // system categories should not be renamed
        $roleViewSet[] = 0;

        if ($menuUUID !== '') {
            $menu->readDataByUuid($menuUUID);

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

        // show form
        $form = new Form(
            'adm_menu_edit_form',
            'modules/menu.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('uuid' => $menuUUID, 'mode' => 'save')),
            $this
        );

        $fieldRequired = Form::FIELD_REQUIRED;
        $fieldDefault = Form::FIELD_DEFAULT;

        if ($menu->getValue('men_standard')) {
            $fieldRequired = Form::FIELD_DISABLED;
            $fieldDefault = Form::FIELD_DISABLED;
        }

        $subMenu = MenuService::subMenu(1, (int)$menu->getValue('men_id'));

        $form->addInput(
            'men_name',
            $gL10n->get('SYS_NAME'),
            htmlentities($menu->getValue('men_name', 'database'), ENT_QUOTES),
            array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'SYS_MENU_NAME_DESC')
        );

        if ($menuUUID !== '') {
            $form->addInput(
                'men_name_intern',
                $gL10n->get('SYS_INTERNAL_NAME'),
                $menu->getValue('men_name_intern'),
                array('maxLength' => 100, 'property' => Form::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
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
                'property' => Form::FIELD_REQUIRED,
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

        $this->smarty->assign('nameUserCreated', $menu->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $menu->getValue('men_timestamp_create'));
        $this->smarty->assign('nameLastUserEdited', $menu->getNameOfLastEditingUser());
        $this->smarty->assign('timestampLastUserEdited', $menu->getValue('men_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for a form to add a new sub-organization to the current organization.
     * @throws Exception|\Smarty\Exception
     */
    public function createList()
    {
        global $gCurrentSession, $gL10n, $gDb, gSettingsManager;

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
            });', true
        );

        // define link to create new menu
        $this->addPageFunctionsMenuItem(
            'menu_item_menu_new',
            $gL10n->get('SYS_CREATE_ENTRY'),
            ADMIDIO_URL . FOLDER_MODULES . '/menu.php?mode=edit',
            'bi-plus-circle-fill'
        );

        if ($gSettingsManager->getBool('profile_log_edit_fields')) { // TODO_RK: More fine-grained logging settings
            // show link to view change history
            $this->addPageFunctionsMenuItem(
                'menu_item_menu_change_history',
                $gL10n->get('SYS_CHANGE_HISTORY'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog.php', array('table' => 'menu')),
                'bi-clock-history'
            );
        }

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

            $templateEntries = array();

            // Get data
            while ($menuRow = $menuStatement->fetch()) {
                $templateRowMenu = array(
                    'uuid' => $menuRow['men_uuid'],
                    'name' => Language::translateIfTranslationStrId((string)$menuRow['men_name']),
                    'description' => Language::translateIfTranslationStrId((string)$menuRow['men_description']),
                    'standard' => $menuRow['men_standard'],
                    'icon' => $menuRow['men_icon'],
                    'url' => $menuRow['men_url']
                );

                // add root path to link unless the full URL is given
                if (preg_match('/^http(s?):\/\//', $menuRow['men_url']) === 0) {
                    $templateRowMenu['urlLink'] = ADMIDIO_URL . $menuRow['men_url'];
                } else {
                    $templateRowMenu['urlLink'] = $menuRow['men_url'];
                }

                $templateRowMenu['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('mode' => 'edit', 'uuid' => $menuRow['men_uuid'])),
                    'icon' => 'bi bi-pencil-square',
                    'tooltip' => $gL10n->get('SYS_EDIT')
                );

                // don't allow delete for standard menus
                if (!$menuRow['men_standard']) {
                    $templateRowMenu['actions'][] = array(
                        'dataHref' => 'callUrlHideElement(\'adm_menu_entry_' . $menuRow['men_uuid'] . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu.php', array('mode' => 'delete', 'uuid' => $menuRow['men_uuid'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                        'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($templateRowMenu['name'])),
                        'icon' => 'bi bi-trash',
                        'tooltip' => $gL10n->get('SYS_DELETE')
                    );
                }

                $templateEntries[] = $templateRowMenu;
            }

            if(count($templateEntries) >0) {
                $templateRowMenuParent[] = array(
                    'uuid' => $mainMen['men_uuid'],
                    'name' => Language::translateIfTranslationStrId((string)$mainMen['men_name']),
                    'entries' => $templateEntries
                );
            }
        }

        $this->smarty->assign('list', $templateRowMenuParent);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/menu.list.tpl');
    }
}
