<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createContentRegistrationList();
 * $page->show();
 * ```
 */

namespace Admidio\UserInterface;
use Admidio\UserInterface\Form;
use FileSystemUtils;
use HtmlPage;
use AdmException;
use SecurityUtils;
use Smarty\Exception;

class Preferences extends HtmlPage
{
    /**
     * @var array Array with all possible accordion entries for the system preferences.
     *            Each accordion entry consists of an array that has the following structure:
     *            array('id' => 'xzy', 'title' => 'xyz', 'icon' => 'xyz')
     */
    protected array $accordionCommonPanels = array();
    /**
     * @var array Array with all possible accordion entries for the modules preferences.
     *            Each accordion entry consists of an array that has the following structure:
     *            array('id' => 'xzy', 'title' => 'xyz', 'icon' => 'xyz')
     */
    protected array $accordionModulePanels = array();
    /**
     * @var string Name of the preference panel that should be shown after page loading.
     *             If this parameter is empty then show the common preferences.
     */
    protected string $preferencesPanelToShow = '';

    /**
     * Constructor that initialize the class member parameters
     * @throws AdmException
     */
    public function __construct(string $id, string $headline = '')
    {
        $this->initialize();

        parent::__construct($id, $headline);
    }

    /**
     * @throws AdmException
     */
    private function initialize(): void
    {
        global $gL10n;

        $this->accordionCommonPanels = array(
            'common' => array(
                'id' => 'common',
                'title' => $gL10n->get('SYS_COMMON'),
                'icon' => 'bi-gear-fill'
            ),
            'security' => array(
                'id' => 'security',
                'title' => $gL10n->get('SYS_SECURITY'),
                'icon' => 'bi-shield-fill'
            ),
            'organization' => array(
                'id' => 'organization',
                'title' => $gL10n->get('SYS_ORGANIZATION'),
                'icon' => 'bi-diagram-3-fill'
            ),
            'regional_settings' => array(
                'id' => 'regional_settings',
                'title' => $gL10n->get('ORG_REGIONAL_SETTINGS'),
                'icon' => 'bi-globe2'
            ),
            'registration' => array(
                'id' => 'registration',
                'title' => $gL10n->get('SYS_REGISTRATION'),
                'icon' => 'bi-card-checklist'
            ),
            'email_dispatch' => array(
                'id' => 'email_dispatch',
                'title' => $gL10n->get('SYS_MAIL_DISPATCH'),
                'icon' => 'bi-envelope-open-fill'
            ),
            'system_notification' => array(
                'id' => 'system_notification',
                'title' => $gL10n->get('SYS_SYSTEM_MAILS'),
                'icon' => 'bi-broadcast-pin'
            ),
            'captcha' => array(
                'id' => 'captcha',
                'title' => $gL10n->get('SYS_CAPTCHA'),
                'icon' => 'bi-fonts'
            ),
            'admidio_update' => array(
                'id' => 'admidio_update',
                'title' => $gL10n->get('SYS_ADMIDIO_VERSION_BACKUP'),
                'icon' => 'bi-cloud-arrow-down-fill'
            ),
            'php' => array(
                'id' => 'php',
                'title' => $gL10n->get('SYS_PHP'),
                'icon' => 'bi-filetype-php'
            ),
            'system_information' => array(
                'id' => 'system_information',
                'title' => $gL10n->get('ORG_SYSTEM_INFORMATION'),
                'icon' => 'bi-info-circle-fill'
            )
        );
        $this->accordionModulePanels = array(
            'announcements' => array(
                'id' => 'announcements',
                'title' => $gL10n->get('SYS_ANNOUNCEMENTS'),
                'icon' => 'bi-newspaper'
            ),
            'contacts' => array(
                'id' => 'contacts',
                'title' => $gL10n->get('SYS_CONTACTS'),
                'icon' => 'bi-person-vcard-fill'
            ),
            'documents_files' => array(
                'id' => 'documents_files',
                'title' => $gL10n->get('SYS_DOCUMENTS_FILES'),
                'icon' => 'bi-file-earmark-arrow-down-fill'
            ),
            'photos' => array(
                'id' => 'photos',
                'title' => $gL10n->get('SYS_PHOTOS'),
                'icon' => 'bi-image-fill'
            ),
            'guestbook' => array(
                'id' => 'guestbook',
                'title' => $gL10n->get('GBO_GUESTBOOK'),
                'icon' => 'bi-book-half'
            ),
            'groups_roles' => array(
                'id' => 'groups_roles',
                'title' => $gL10n->get('SYS_GROUPS_ROLES'),
                'icon' => 'bi-people-fill'
            ),
            'category_report' => array(
                'id' => 'category_report',
                'title' => $gL10n->get('SYS_CATEGORY_REPORT'),
                'icon' => 'bi-list-stars'
            ),
            'messages' => array(
                'id' => 'messages',
                'title' => $gL10n->get('SYS_MESSAGES'),
                'icon' => 'bi-envelope-fill'
            ),
            'profile' => array(
                'id' => 'profile',
                'title' => $gL10n->get('SYS_PROFILE'),
                'icon' => 'bi-person-fill'
            ),
            'events' => array(
                'id' => 'events',
                'title' => $gL10n->get('SYS_EVENTS'),
                'icon' => 'bi-calendar-week-fill'
            ),
            'links' => array(
                'id' => 'links',
                'title' => $gL10n->get('SYS_WEBLINKS'),
                'icon' => 'bi-link-45deg'
            )
        );
    }

    /**
     * @throws AdmException
     * @throws Exception
     * @throws \Exception
     */
    public function createCommonForm()
    {
        global $gL10n, $gCurrentOrganization, $gSettingsManager;

        // read organization and all system preferences values into form array
        $formValues = array_merge($gCurrentOrganization->getDbColumns(), $gSettingsManager->getAll());

        $formCommon = new Form(
            'common_preferences_form',
            'preferences/preferences.common.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('form' => 'common')),
            null,
            array('class' => 'form-preferences')
        );

        // search all available themes in theme folder
        $themes = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_THEMES, false, false, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY)));
        if (count($themes) === 0) {
            throw new AdmException('SYS_TEMPLATE_FOLDER_OPEN');
        }
        $formCommon->addSelectBox(
            'theme',
            $gL10n->get('ORG_ADMIDIO_THEME'),
            $themes,
            array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $formValues['theme'], 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_ADMIDIO_THEME_DESC')
        );
        $formCommon->addInput(
            'homepage_logout',
            $gL10n->get('SYS_HOMEPAGE') . ' (' . $gL10n->get('SYS_VISITORS') . ')',
            $formValues['homepage_logout'],
            array('maxLength' => 250, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'ORG_HOMEPAGE_VISITORS')
        );
        $formCommon->addInput(
            'homepage_login',
            $gL10n->get('SYS_HOMEPAGE') . ' (' . $gL10n->get('ORG_REGISTERED_USERS') . ')',
            $formValues['homepage_login'],
            array('maxLength' => 250, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'ORG_HOMEPAGE_REGISTERED_USERS')
        );
        $formCommon->addCheckbox(
            'enable_rss',
            $gL10n->get('ORG_ENABLE_RSS_FEEDS'),
            (bool)$formValues['enable_rss'],
            array('helpTextId' => 'ORG_ENABLE_RSS_FEEDS_DESC')
        );
        $formCommon->addCheckbox(
            'system_cookie_note',
            $gL10n->get('SYS_COOKIE_NOTE'),
            (bool)$formValues['system_cookie_note'],
            array('helpTextId' => 'SYS_COOKIE_NOTE_DESC')
        );
        $formCommon->addCheckbox(
            'system_search_similar',
            $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'),
            (bool)$formValues['system_search_similar'],
            array('helpTextId' => 'ORG_SEARCH_SIMILAR_NAMES_DESC')
        );
        $selectBoxEntries = array(0 => $gL10n->get('SYS_DONT_SHOW'), 1 => $gL10n->get('SYS_FIRSTNAME_LASTNAME'), 2 => $gL10n->get('SYS_USERNAME'));
        $formCommon->addSelectBox(
            'system_show_create_edit',
            $gL10n->get('ORG_SHOW_CREATE_EDIT'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['system_show_create_edit'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_SHOW_CREATE_EDIT_DESC')
        );
        $formCommon->addInput(
            'system_url_data_protection',
            $gL10n->get('SYS_DATA_PROTECTION'),
            $formValues['system_url_data_protection'],
            array('maxLength' => 250, 'helpTextId' => 'SYS_DATA_PROTECTION_DESC')
        );
        $formCommon->addInput(
            'system_url_imprint',
            $gL10n->get('SYS_IMPRINT'),
            $formValues['system_url_imprint'],
            array('maxLength' => 250, 'helpTextId' => 'SYS_IMPRINT_DESC')
        );
        $formCommon->addCheckbox(
            'system_js_editor_enabled',
            $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'),
            (bool)$formValues['system_js_editor_enabled'],
            array('helpTextId' => 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC')
        );
        $formCommon->addCheckbox(
            'system_browser_update_check',
            $gL10n->get('ORG_BROWSER_UPDATE_CHECK'),
            (bool)$formValues['system_browser_update_check'],
            array('helpTextId' => 'ORG_BROWSER_UPDATE_CHECK_DESC')
        );
        $formCommon->addSubmitButton(
            'btn_save_common',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        $smarty = $this->getSmartyTemplate();
        $formCommon->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.common.tpl');
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     * @throws \Exception
     */
    public function show()
    {
        if ($this->preferencesPanelToShow !== '') {
            // open the modules tab if the options of a module should be shown
            if (array_key_exists($this->preferencesPanelToShow, $this->accordionModulePanels)) {
                $this->addJavascript(
                    '
                $("#tabs_nav_modules").attr("class", "nav-link active");
                $("#tabs-modules").attr("class", "tab-pane fade show active");
                $("#collapse_' . $this->preferencesPanelToShow . '").attr("class", "collapse show");
                location.hash = "#" + "panel_' . $this->preferencesPanelToShow . '";',
                    true
                );
            } else {
                $this->addJavascript(
                    '
                $("#tabs_nav_common").attr("class", "nav-link active");
                $("#tabs-common").attr("class", "tab-pane fade show active");
                $("#collapse_' . $this->preferencesPanelToShow . '").attr("class", "collapse show");
                location.hash = "#" + "panel_' . $this->preferencesPanelToShow . '";',
                    true
                );
            }
        }

        $this->addJavascript(
            '
            $("#admidio-panel-common-preferences-common .accordion-header").click(function (e) {
                if ($("#admidio-panel-common-preferences-common h2").attr("aria-expanded") == "true") {
                    $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php?mode=html_form&form=common", function (data) {
                        $("#collapse-common-preferences-common .accordion-body").html(data);
                    });
                }
            });

            $(document).on("submit", "#common_preferences_form", formSubmit);

            $("#captcha-refresh").click(function() {
                document.getElementById("captcha").src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php?" + Math.random();
            });

            $("#link_check_for_update").click(function() {
                var admVersionContent = $("#admidio_version_content");

                admVersionContent.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/update_check.php", {mode: "2"}, function(htmlVersion) {
                    admVersionContent.html(htmlVersion);
                });
                return false;
            });

            $("#link_directory_protection").click(function() {
                var dirProtectionStatus = $("#directory_protection_status");

                dirProtectionStatus.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php", {mode: "htaccess"}, function(statusText) {
                    var directoryProtection = dirProtectionStatus.parent().parent().parent();
                    directoryProtection.html("<span class=\"text-success\"><strong>" + statusText + "</strong></span>");
                });
                return false;
            });',
            true
        );

        $this->assignSmartyVariable('accordionCommonPanels', $this->accordionCommonPanels);
        $this->assignSmartyVariable('accordionModulePanels', $this->accordionModulePanels);
        $this->addTemplateFile('preferences/preferences.tpl');

        parent::show();
    }
}
