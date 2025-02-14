<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Menu\Service\MenuService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Service\ChangelogService;

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
            });', true
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
