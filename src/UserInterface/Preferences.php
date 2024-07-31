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
use ComponentUpdate;
use FileSystemUtils;
use HtmlPage;
use AdmException;
use PhpIniUtils;
use RuntimeException;
use SecurityUtils;
use Smarty\Exception;
use SystemInfoUtils;
use TableText;

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
                'id' => 'Common',
                'title' => $gL10n->get('SYS_COMMON'),
                'icon' => 'bi-gear-fill'
            ),
            'security' => array(
                'id' => 'Security',
                'title' => $gL10n->get('SYS_SECURITY'),
                'icon' => 'bi-shield-fill'
            ),
            'organization' => array(
                'id' => 'Organization',
                'title' => $gL10n->get('SYS_ORGANIZATION'),
                'icon' => 'bi-diagram-3-fill'
            ),
            'regional_settings' => array(
                'id' => 'RegionalSettings',
                'title' => $gL10n->get('ORG_REGIONAL_SETTINGS'),
                'icon' => 'bi-globe2'
            ),
            'registration' => array(
                'id' => 'Registration',
                'title' => $gL10n->get('SYS_REGISTRATION'),
                'icon' => 'bi-card-checklist'
            ),
            'email_dispatch' => array(
                'id' => 'EmailDispatch',
                'title' => $gL10n->get('SYS_MAIL_DISPATCH'),
                'icon' => 'bi-envelope-open-fill'
            ),
            'system_notifications' => array(
                'id' => 'SystemNotifications',
                'title' => $gL10n->get('SYS_SYSTEM_MAILS'),
                'icon' => 'bi-broadcast-pin'
            ),
            'captcha' => array(
                'id' => 'Captcha',
                'title' => $gL10n->get('SYS_CAPTCHA'),
                'icon' => 'bi-fonts'
            ),
            'admidio_update' => array(
                'id' => 'AdmidioUpdate',
                'title' => $gL10n->get('SYS_ADMIDIO_VERSION_BACKUP'),
                'icon' => 'bi-cloud-arrow-down-fill'
            ),
            'php' => array(
                'id' => 'PHP',
                'title' => $gL10n->get('SYS_PHP'),
                'icon' => 'bi-filetype-php'
            ),
            'system_information' => array(
                'id' => 'SystemInformation',
                'title' => $gL10n->get('SYS_SYSTEM_INFORMATION'),
                'icon' => 'bi-info-circle-fill'
            )
        );
        $this->accordionModulePanels = array(
            'announcements' => array(
                'id' => 'Announcements',
                'title' => $gL10n->get('SYS_ANNOUNCEMENTS'),
                'icon' => 'bi-newspaper'
            ),
            'contacts' => array(
                'id' => 'Contacts',
                'title' => $gL10n->get('SYS_CONTACTS'),
                'icon' => 'bi-person-vcard-fill'
            ),
            'documents_files' => array(
                'id' => 'DocumentsFiles',
                'title' => $gL10n->get('SYS_DOCUMENTS_FILES'),
                'icon' => 'bi-file-earmark-arrow-down-fill'
            ),
            'photos' => array(
                'id' => 'Photos',
                'title' => $gL10n->get('SYS_PHOTOS'),
                'icon' => 'bi-image-fill'
            ),
            'guestbook' => array(
                'id' => 'Guestbook',
                'title' => $gL10n->get('GBO_GUESTBOOK'),
                'icon' => 'bi-book-half'
            ),
            'groups_roles' => array(
                'id' => 'GroupsRoles',
                'title' => $gL10n->get('SYS_GROUPS_ROLES'),
                'icon' => 'bi-people-fill'
            ),
            'category_report' => array(
                'id' => 'CategoryReport',
                'title' => $gL10n->get('SYS_CATEGORY_REPORT'),
                'icon' => 'bi-list-stars'
            ),
            'messages' => array(
                'id' => 'Messages',
                'title' => $gL10n->get('SYS_MESSAGES'),
                'icon' => 'bi-envelope-fill'
            ),
            'profile' => array(
                'id' => 'Profile',
                'title' => $gL10n->get('SYS_PROFILE'),
                'icon' => 'bi-person-fill'
            ),
            'events' => array(
                'id' => 'Events',
                'title' => $gL10n->get('SYS_EVENTS'),
                'icon' => 'bi-calendar-week-fill'
            ),
            'links' => array(
                'id' => 'Links',
                'title' => $gL10n->get('SYS_WEBLINKS'),
                'icon' => 'bi-link-45deg'
            )
        );
    }

    /**
     * Generates the html of the form from the Admidio update preferences and will return the complete html.
     * @return string Returns the complete html of the form from the Admidio update preferences.
     * @throws AdmException|Exception
     */
    public function createAdmidioUpdateForm(): string
    {
        global $gDb, $gSystemComponent;

        $component = new ComponentUpdate($gDb);
        $component->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
        $updateStep = (int) $gSystemComponent->getValue('com_update_step');
        $maxStep = $component->getMaxUpdateStep();
        $updateStepText = $updateStep . ' / ' . $maxStep;
        if ($updateStep === $maxStep) {
            $updateStepColorClass = 'text-success';
        } elseif ($updateStep > $maxStep) {
            $updateStepColorClass = 'text-warning';
        } else {
            $updateStepColorClass = 'text-danger';
        }

        $this->assignSmartyVariable('admidioVersion', ADMIDIO_VERSION_TEXT);
        $this->assignSmartyVariable('updateStepColorClass', $updateStepColorClass);
        $this->assignSmartyVariable('updateStepText', $updateStepText);
        $this->assignSmartyVariable('databaseEngine', DB_ENGINE);
        $this->assignSmartyVariable('backupUrl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'backup')));
        $this->assignSmartyVariable('admidioHomepage', ADMIDIO_HOMEPAGE);

        $smarty = $this->getSmartyTemplate();
        return $smarty->fetch('preferences/preferences.admidio-update.tpl');
    }

    /**
     * Generates the html of the form from the announcements preferences and will return the complete html.
     * @return string Returns the complete html of the form from the announcements preferences.
     * @throws AdmException|Exception
     */
    public function createAnnouncementsForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formAnnouncements = new Form(
            'preferencesFormAnnouncements',
            'preferences/preferences.announcements.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Announcements')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formAnnouncements->addSelectBox(
            'announcements_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['announcements_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $formAnnouncements->addInput(
            'announcements_per_page',
            $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
            $formValues['announcements_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
        );
        $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories/categories.php', array('type' => 'ANN')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
        $formAnnouncements->addCustomContent(
            'maintainCategories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            $html,
            array('helpTextId' => 'SYS_MAINTAIN_CATEGORIES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formAnnouncements->addSubmitButton(
            'btn_save_announcements',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formAnnouncements->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.announcements.tpl');
    }

    /**
     * Generates the html of the form from the captcha preferences and will return the complete html.
     * @return string Returns the complete html of the form from the captcha preferences.
     * @throws AdmException|Exception
     */
    public function createCaptchaForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formCaptcha = new Form(
            'preferencesFormCaptcha',
            'preferences/preferences.captcha.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Captcha')),
            null,
            array('class' => 'form-preferences')
        );

        // search all available themes in theme folder
        $themes = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_THEMES, false, false, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY)));
        if (count($themes) === 0) {
            throw new AdmException('SYS_TEMPLATE_FOLDER_OPEN');
        }
        $selectBoxEntries = array(
            'pic' => $gL10n->get('ORG_CAPTCHA_TYPE_PIC'),
            'calc' => $gL10n->get('ORG_CAPTCHA_TYPE_CALC'),
            'word' => $gL10n->get('ORG_CAPTCHA_TYPE_WORDS')
        );
        $formCaptcha->addSelectBox(
            'captcha_type',
            $gL10n->get('ORG_CAPTCHA_TYPE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['captcha_type'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_CAPTCHA_TYPE_TEXT')
        );

        $fonts = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . '/adm_program/system/fonts/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
        asort($fonts);
        $formCaptcha->addSelectBox(
            'captcha_fonts',
            $gL10n->get('SYS_FONT'),
            $fonts,
            array('defaultValue' => $formValues['captcha_fonts'], 'showContextDependentFirstEntry' => false, 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_CAPTCHA_FONT')
        );
        $formCaptcha->addInput(
            'captcha_width',
            $gL10n->get('SYS_WIDTH') . ' (' . $gL10n->get('ORG_PIXEL') . ')',
            $formValues['captcha_width'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'ORG_CAPTCHA_WIDTH_DESC')
        );
        $formCaptcha->addInput(
            'captcha_lines_numbers',
            $gL10n->get('ORG_CAPTCHA_LINES_NUMBERS'),
            $formValues['captcha_lines_numbers'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 25, 'step' => 1, 'helpTextId' => 'ORG_CAPTCHA_LINES_NUMBERS_DESC')
        );
        $formCaptcha->addInput(
            'captcha_perturbation',
            $gL10n->get('ORG_CAPTCHA_DISTORTION'),
            $formValues['captcha_perturbation'],
            array('type' => 'string', 'helpTextId' => 'ORG_CAPTCHA_DISTORTION_DESC', 'class' => 'form-control-small')
        );
        $backgrounds = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_LIBS . '/securimage/backgrounds/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
        asort($backgrounds);
        $formCaptcha->addSelectBox(
            'captcha_background_image',
            $gL10n->get('ORG_CAPTCHA_BACKGROUND_IMAGE'),
            $backgrounds,
            array('defaultValue' => $formValues['captcha_background_image'], 'showContextDependentFirstEntry' => true, 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_CAPTCHA_BACKGROUND_IMAGE_DESC')
        );
        $formCaptcha->addInput(
            'captcha_background_color',
            $gL10n->get('ORG_CAPTCHA_BACKGROUND_COLOR'),
            $formValues['captcha_background_color'],
            array('maxLength' => 7, 'class' => 'form-control-small')
        );
        $formCaptcha->addInput(
            'captcha_text_color',
            $gL10n->get('ORG_CAPTCHA_CHARACTERS_COLOR'),
            $formValues['captcha_text_color'],
            array('maxLength' => 7, 'class' => 'form-control-small')
        );
        $formCaptcha->addInput(
            'captcha_line_color',
            $gL10n->get('ORG_CAPTCHA_LINE_COLOR'),
            $formValues['captcha_line_color'],
            array('maxLength' => 7, 'helpTextId' => array('ORG_CAPTCHA_COLOR_DESC', array('<a href="https://en.wikipedia.org/wiki/Web_colors">', '</a>')), 'class' => 'form-control-small')
        );
        $formCaptcha->addInput(
            'captcha_charset',
            $gL10n->get('ORG_CAPTCHA_SIGNS'),
            $formValues['captcha_charset'],
            array('maxLength' => 80, 'helpTextId' => 'ORG_CAPTCHA_SIGNS_TEXT')
        );
        $formCaptcha->addInput(
            'captcha_signature',
            $gL10n->get('ORG_CAPTCHA_SIGNATURE'),
            $formValues['captcha_signature'],
            array('maxLength' => 60, 'helpTextId' => 'ORG_CAPTCHA_SIGNATURE_TEXT')
        );
        $html = '<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
         <a id="captcha-refresh" class="admidio-icon-link" href="javascript:void(0)">
            <i class="bi bi-arrow-repeat" style="font-size: 22pt;" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_RELOAD') . '"></i></a>';
        $formCaptcha->addCustomContent(
            'captchaPreview',
            $gL10n->get('ORG_CAPTCHA_PREVIEW'),
            $html,
            array('helpTextId' => 'ORG_CAPTCHA_PREVIEW_TEXT')
        );
        $formCaptcha->addSubmitButton(
            'btn_save_captcha',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formCaptcha->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.captcha.tpl');
    }

    /**
     * Generates the html of the form from the common preferences and will return the complete html.
     * @return string Returns the complete html of the form from the common preferences.
     * @throws AdmException|Exception
     */
    public function createCommonForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formCommon = new Form(
            'preferencesFormCommon',
            'preferences/preferences.common.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Common')),
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
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formCommon->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.common.tpl');
    }

    /**
     * Generates the html of the form from the contacts preferences and will return the complete html.
     * @return string Returns the complete html of the form from the contacts preferences.
     * @throws AdmException|Exception
     */
    public function createContactsForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId;

        $formValues = $gSettingsManager->getAll();

        $formContacts = new Form(
            'preferencesFormContacts',
            'preferences/preferences.contacts.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Contacts')),
            null,
            array('class' => 'form-preferences')
        );

        // read all global lists
        $sqlData = array();
        $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                        AND NOT EXISTS (SELECT 1
                                       FROM ' . TBL_LIST_COLUMNS . '
                                       WHERE lsc_lst_id = lst_id
                                       AND lsc_special_field LIKE \'mem%\')
                   ORDER BY lst_name, lst_timestamp DESC';
        $sqlData['params'] = array($gCurrentOrgId);
        $formContacts->addSelectBoxFromSql(
            'contacts_list_configuration',
            $gL10n->get('SYS_CONFIGURATION_LIST'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['contacts_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MEMBERS_CONFIGURATION_DESC')
        );
        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100');
        $formContacts->addSelectBox(
            'contacts_per_page',
            $gL10n->get('SYS_CONTACTS_PER_PAGE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['contacts_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(25)))
        );
        $formContacts->addInput(
            'contacts_field_history_days',
            $gL10n->get('SYS_DAYS_FIELD_HISTORY'),
            $formValues['contacts_field_history_days'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'step' => 1, 'helpTextId' => 'SYS_DAYS_FIELD_HISTORY_DESC')
        );
        $formContacts->addCheckbox(
            'contacts_show_all',
            $gL10n->get('SYS_SHOW_ALL_CONTACTS'),
            (bool)$formValues['contacts_show_all'],
            array('helpTextId' => 'SYS_SHOW_ALL_CONTACTS_DESC')
        );
        $formContacts->addCheckbox(
            'contacts_user_relations_enabled',
            $gL10n->get('SYS_ENABLE_USER_RELATIONS'),
            (bool)$formValues['contacts_user_relations_enabled'],
            array('helpTextId' => 'SYS_ENABLE_USER_RELATIONS_DESC')
        );

        $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes.php">
            <i class="bi bi-person-heart"></i>' . $gL10n->get('SYS_SWITCH_TO_RELATIONSHIP_CONFIGURATION') . '</a>';
        $formContacts->addCustomContent(
            'userRelations',
            $gL10n->get('SYS_USER_RELATIONS'),
            $html,
            array('helpTextId' => 'SYS_MAINTAIN_USER_RELATION_TYPES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));

        $formContacts->addSubmitButton(
            'btn_save_contacts',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formContacts->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.contacts.tpl');
    }

    /**
     * Generates the html of the form from the documents & files preferences and will return the complete html.
     * @return string Returns the complete html of the form from the documents & files preferences.
     * @throws AdmException|Exception
     */
    public function createDocumentsFilesForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formDocumentsFiles = new Form(
            'preferencesFormDocumentsFiles',
            'preferences/preferences.documents-files.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'DocumentsFiles')),
            null,
            array('class' => 'form-preferences')
        );
        $formDocumentsFiles->addCheckbox(
            'documents_files_module_enabled',
            $gL10n->get('SYS_ENABLE_DOCUMENTS_FILES_MODULE'),
            (bool)$formValues['documents_files_module_enabled'],
            array('helpTextId' => 'SYS_ENABLE_DOCUMENTS_FILES_MODULE_DESC')
        );
        $formDocumentsFiles->addInput(
            'documents_files_max_upload_size',
            $gL10n->get('SYS_MAXIMUM_FILE_SIZE') . ' (MB)',
            $formValues['documents_files_max_upload_size'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextId' => 'SYS_MAXIMUM_FILE_SIZE_DESC')
        );
        $formDocumentsFiles->addSubmitButton(
            'btn_save_documents_files',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formDocumentsFiles->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.documents-files.tpl');
    }

    /**
     * Generates the html of the form from the email dispatch preferences and will return the complete html.
     * @return string Returns the complete html of the form from the email dispatch preferences.
     * @throws AdmException|Exception
     */
    public function createEmailDispatchForm(): string
    {
        global $gL10n, $gCurrentOrganization, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formEmailDispatch = new Form(
            'preferencesFormOrganization',
            'preferences/preferences.email-dispatch.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'EmailDispatch')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array('phpmail' => $gL10n->get('SYS_PHP_MAIL'), 'SMTP' => $gL10n->get('SYS_SMTP'));
        $formEmailDispatch->addSelectBox(
            'mail_send_method',
            $gL10n->get('SYS_SEND_METHOD'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_send_method'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_SEND_METHOD_DESC')
        );
        $formEmailDispatch->addInput(
            'mail_sendmail_address',
            $gL10n->get('SYS_SENDER_EMAIL'),
            $formValues['mail_sendmail_address'],
            array('maxLength' => 50, 'helpTextId' => array('SYS_SENDER_EMAIL_ADDRESS_DESC', array(DOMAIN)))
        );
        $formEmailDispatch->addInput(
            'mail_sendmail_name',
            $gL10n->get('SYS_SENDER_NAME'),
            $formValues['mail_sendmail_name'],
            array('maxLength' => 50, 'helpTextId' => 'SYS_SENDER_NAME_DESC')
        );

        // Add js to show or hide mail options
        $this->addJavascript('
            $(function(){
                var fieldsToHideOnSingleMode = "#mail_recipients_with_roles_group, #mail_into_to_group, #mail_number_recipients_group";
                if($("#mail_sending_mode").val() == 1) {
                    $(fieldsToHideOnSingleMode).hide();
                }
                $("#mail_sending_mode").on("change", function() {
                    if($("#mail_sending_mode").val() == 1) {
                        $(fieldsToHideOnSingleMode).hide();
                    } else {
                        $(fieldsToHideOnSingleMode).show();
                    }
                });
            });
        ');

        $selectBoxEntries = array(0 => $gL10n->get('SYS_MAIL_BULK'), 1 => $gL10n->get('SYS_MAIL_SINGLE'));
        $formEmailDispatch->addSelectBox(
            'mail_sending_mode',
            $gL10n->get('SYS_MAIL_SENDING_MODE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_sending_mode'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MAIL_SENDING_MODE_DESC')
        );

        $selectBoxEntries = array(0 => $gL10n->get('SYS_HIDDEN'), 1 => $gL10n->get('SYS_SENDER'), 2 => $gL10n->get('SYS_ADMINISTRATOR'));
        $formEmailDispatch->addSelectBox(
            'mail_recipients_with_roles',
            $gL10n->get('SYS_MULTIPLE_RECIPIENTS'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_recipients_with_roles'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MULTIPLE_RECIPIENTS_DESC')
        );
        $formEmailDispatch->addCheckbox(
            'mail_into_to',
            $gL10n->get('SYS_INTO_TO'),
            (bool)$formValues['mail_into_to'],
            array('helpTextId' => 'SYS_INTO_TO_DESC')
        );
        $formEmailDispatch->addInput(
            'mail_number_recipients',
            $gL10n->get('SYS_NUMBER_RECIPIENTS'),
            $formValues['mail_number_recipients'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_NUMBER_RECIPIENTS_DESC')
        );

        $selectBoxEntries = array('iso-8859-1' => $gL10n->get('SYS_ISO_8859_1'), 'utf-8' => $gL10n->get('SYS_UTF8'));
        $formEmailDispatch->addSelectBox(
            'mail_character_encoding',
            $gL10n->get('SYS_CHARACTER_ENCODING'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_character_encoding'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_CHARACTER_ENCODING_DESC')
        );
        $formEmailDispatch->addInput(
            'mail_smtp_host',
            $gL10n->get('SYS_SMTP_HOST'),
            $formValues['mail_smtp_host'],
            array('maxLength' => 50, 'helpTextId' => 'SYS_SMTP_HOST_DESC')
        );
        $formEmailDispatch->addCheckbox(
            'mail_smtp_auth',
            $gL10n->get('SYS_SMTP_AUTH'),
            (bool)$formValues['mail_smtp_auth'],
            array('helpTextId' => 'SYS_SMTP_AUTH_DESC')
        );
        $formEmailDispatch->addInput(
            'mail_smtp_port',
            $gL10n->get('SYS_SMTP_PORT'),
            $formValues['mail_smtp_port'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_SMTP_PORT_DESC')
        );
        $selectBoxEntries = array(
            '' => $gL10n->get('SYS_SMTP_SECURE_NO'),
            'ssl' => $gL10n->get('SYS_SMTP_SECURE_SSL'),
            'tls' => $gL10n->get('SYS_SMTP_SECURE_TLS')
        );
        $formEmailDispatch->addSelectBox(
            'mail_smtp_secure',
            $gL10n->get('SYS_SMTP_SECURE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_smtp_secure'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_SMTP_SECURE_DESC')
        );
        $selectBoxEntries = array(
            '' => $gL10n->get('SYS_AUTO_DETECT'),
            'LOGIN' => $gL10n->get('SYS_SMTP_AUTH_LOGIN'),
            'PLAIN' => $gL10n->get('SYS_SMTP_AUTH_PLAIN'),
            'CRAM-MD5' => $gL10n->get('SYS_SMTP_AUTH_CRAM_MD5')
        );
        $formEmailDispatch->addSelectBox(
            'mail_smtp_authentication_type',
            $gL10n->get('SYS_SMTP_AUTH_TYPE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_smtp_authentication_type'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_SMTP_AUTH_TYPE_DESC', array('SYS_AUTO_DETECT')))
        );
        $formEmailDispatch->addInput(
            'mail_smtp_user',
            $gL10n->get('SYS_SMTP_USER'),
            $formValues['mail_smtp_user'],
            array('maxLength' => 100, 'helpTextId' => 'SYS_SMTP_USER_DESC')
        );
        $formEmailDispatch->addInput(
            'mail_smtp_password',
            $gL10n->get('SYS_SMTP_PASSWORD'),
            $formValues['mail_smtp_password'],
            array('type' => 'password', 'maxLength' => 50, 'helpTextId' => 'SYS_SMTP_PASSWORD_DESC')
        );
        $html = '<a class="btn btn-secondary" id="send_test_mail" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'test_email')) . '">
            <i class="bi bi-envelope-fill"></i>' . $gL10n->get('SYS_SEND_TEST_MAIL') . '</a>';
        $formEmailDispatch->addCustomContent('send_test_email', $gL10n->get('SYS_TEST_MAIL'), $html, array('helpTextId' => $gL10n->get('SYS_TEST_MAIL_DESC', array($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname')))))));
        $formEmailDispatch->addSubmitButton(
            'btn_save_email_dispatch',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formEmailDispatch->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.email-dispatch.tpl');
    }

    /**
     * Generates the html of the form from the guestbook preferences and will return the complete html.
     * @return string Returns the complete html of the form from the guestbook preferences.
     * @throws AdmException|Exception
     */
    public function createGuestbookForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formGuestbook = new Form(
            'preferencesFormGuestbook',
            'preferences/preferences.guestbook.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Guestbook')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formGuestbook->addSelectBox(
            'enable_guestbook_module',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['enable_guestbook_module'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $formGuestbook->addInput(
            'guestbook_entries_per_page',
            $gL10n->get('ORG_NUMBER_OF_ENTRIES_PER_PAGE'),
            $formValues['guestbook_entries_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
        );
        $formGuestbook->addCheckbox(
            'enable_guestbook_captcha',
            $gL10n->get('ORG_ENABLE_CAPTCHA'),
            (bool)$formValues['enable_guestbook_captcha'],
            array('helpTextId' => 'GBO_CAPTCHA_DESC')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_NOBODY'),
            '1' => $gL10n->get('GBO_ONLY_VISITORS'),
            '2' => $gL10n->get('SYS_ALL')
        );
        $formGuestbook->addSelectBox(
            'enable_guestbook_moderation',
            $gL10n->get('GBO_GUESTBOOK_MODERATION'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['enable_guestbook_moderation'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'GBO_GUESTBOOK_MODERATION_DESC')
        );
        $formGuestbook->addCheckbox(
            'enable_gbook_comments4all',
            $gL10n->get('GBO_COMMENTS4ALL'),
            (bool)$formValues['enable_gbook_comments4all'],
            array('helpTextId' => 'GBO_COMMENTS4ALL_DESC')
        );
        $formGuestbook->addCheckbox(
            'enable_intial_comments_loading',
            $gL10n->get('GBO_INITIAL_COMMENTS_LOADING'),
            (bool)$formValues['enable_intial_comments_loading'],
            array('helpTextId' => 'GBO_INITIAL_COMMENTS_LOADING_DESC')
        );
        $formGuestbook->addInput(
            'flooding_protection_time',
            $gL10n->get('GBO_FLOODING_PROTECTION_INTERVALL'),
            $formValues['flooding_protection_time'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'GBO_FLOODING_PROTECTION_INTERVALL_DESC')
        );
        $formGuestbook->addSubmitButton(
            'btn_save_guestbook',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formGuestbook->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.guestbook.tpl');
    }

    /**
     * Generates the html of the form from the organization preferences and will return the complete html.
     * @return string Returns the complete html of the form from the organization preferences.
     * @throws AdmException|Exception
     */
    public function createOrganizationForm(): string
    {
        global $gDb, $gL10n, $gCurrentOrganization, $gSettingsManager, $gCurrentOrgId;

        // read organization and all system preferences values into form array
        $formValues = array_merge($gCurrentOrganization->getDbColumns(), $gSettingsManager->getAll());

        $formOrganization = new Form(
            'preferencesFormOrganization',
            'preferences/preferences.organization.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Organization')),
            null,
            array('class' => 'form-preferences')
        );
        $formOrganization->addInput(
            'org_shortname',
            $gL10n->get('SYS_NAME_ABBREVIATION'),
            $formValues['org_shortname'],
            array('property' => Form::FIELD_DISABLED, 'class' => 'form-control-small')
        );
        $formOrganization->addInput(
            'org_longname',
            $gL10n->get('SYS_NAME'),
            $formValues['org_longname'],
            array('maxLength' => 60, 'property' => Form::FIELD_REQUIRED)
        );
        $formOrganization->addInput(
            'org_homepage',
            $gL10n->get('SYS_WEBSITE'),
            $formValues['org_homepage'],
            array('maxLength' => 60)
        );
        $formOrganization->addInput(
            'email_administrator',
            $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
            $formValues['email_administrator'],
            array('type' => 'email', 'maxLength' => 50, 'helpTextId' => 'SYS_EMAIL_ADMINISTRATOR_DESC')
        );

        if ($gCurrentOrganization->countAllRecords() > 1) {
            // Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
            if (!$gCurrentOrganization->isParentOrganization()) {
                $sqlData = array();
                $sqlData['query'] = 'SELECT org_id, org_longname
                               FROM ' . TBL_ORGANIZATIONS . '
                              WHERE org_id <> ? -- $gCurrentOrgId
                                AND org_org_id_parent IS NULL
                           ORDER BY org_longname, org_shortname';
                $sqlData['params'] = array($gCurrentOrgId);
                $formOrganization->addSelectBoxFromSql(
                    'org_org_id_parent',
                    $gL10n->get('ORG_PARENT_ORGANIZATION'),
                    $gDb,
                    $sqlData,
                    array('defaultValue' => $formValues['org_org_id_parent'], 'helpTextId' => 'ORG_PARENT_ORGANIZATION_DESC')
                );
            }

            $formOrganization->addCheckbox(
                'system_organization_select',
                $gL10n->get('ORG_SHOW_ORGANIZATION_SELECT'),
                (bool)$formValues['system_organization_select'],
                array('helpTextId' => 'ORG_SHOW_ORGANIZATION_SELECT_DESC')
            );
        }

        $html = '<a class="btn btn-secondary" id="add_another_organization" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'new_org_dialog')) . '">
            <i class="bi bi-plus-circle-fill"></i>' . $gL10n->get('INS_ADD_ANOTHER_ORGANIZATION') . '</a>';
        $formOrganization->addCustomContent('new_organization', $gL10n->get('ORG_NEW_ORGANIZATION'), $html, array('helpTextId' => 'ORG_ADD_ORGANIZATION_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST')));
        $formOrganization->addSubmitButton(
            'btn_save_organization',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formOrganization->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.organization.tpl');
    }


    /**
     * Generates the html of the form from the photos preferences and will return the complete html.
     * @return string Returns the complete html of the form from the photos preferences.
     * @throws AdmException|Exception
     */
    public function createPhotosForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formPhotos = new Form(
            'preferencesFormPhotos',
            'preferences/preferences.photos.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Photos')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formPhotos->addSelectBox(
            'photo_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['photo_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $selectBoxEntries = array(
            '1' => $gL10n->get('SYS_MODAL_WINDOW'),
            '2' => $gL10n->get('SYS_SAME_WINDOW')
        );
        $formPhotos->addSelectBox(
            'photo_show_mode',
            $gL10n->get('SYS_PHOTOS_PRESENTATION'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['photo_show_mode'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_PHOTOS_PRESENTATION_DESC')
        );
        $formPhotos->addInput(
            'photo_albums_per_page',
            $gL10n->get('SYS_NUMBER_OF_ALBUMS_PER_PAGE'),
            $formValues['photo_albums_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(24)))
        );
        $formPhotos->addInput(
            'photo_thumbs_page',
            $gL10n->get('SYS_THUMBNAILS_PER_PAGE'),
            $formValues['photo_thumbs_page'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_THUMBNAILS_PER_PAGE_DESC', array(24)))
        );
        $formPhotos->addInput(
            'photo_thumbs_scale',
            $gL10n->get('SYS_THUMBNAIL_SCALING'),
            $formValues['photo_thumbs_scale'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_THUMBNAIL_SCALING_DESC', array(500)))
        );
        $formPhotos->addInput(
            'photo_show_width',
            $gL10n->get('SYS_MAX_PHOTO_SIZE_WIDTH'),
            $formValues['photo_show_width'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1)
        );
        $formPhotos->addInput(
            'photo_show_height',
            $gL10n->get('SYS_MAX_PHOTO_SIZE_HEIGHT'),
            $formValues['photo_show_height'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_MAX_PHOTO_SIZE_DESC', array(1200, 1200)))
        );
        $formPhotos->addInput(
            'photo_image_text',
            $gL10n->get('SYS_SHOW_WATERMARK'),
            $formValues['photo_image_text'],
            array('maxLength' => 60, 'helpTextId' => array('SYS_SHOW_WATERMARK_DESC', array('© ' . DOMAIN)))
        );
        $formPhotos->addInput(
            'photo_image_text_size',
            $gL10n->get('SYS_CAPTION_SIZE'),
            $formValues['photo_image_text_size'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_CAPTION_SIZE_DESC')
        );
        $formPhotos->addCheckbox(
            'photo_download_enabled',
            $gL10n->get('SYS_ENABLE_DOWNLOAD'),
            (bool)$formValues['photo_download_enabled'],
            array('helpTextId' => array('SYS_ENABLE_DOWNLOAD_DESC', array('SYS_KEEP_ORIGINAL')))
        );
        $formPhotos->addCheckbox(
            'photo_keep_original',
            $gL10n->get('SYS_KEEP_ORIGINAL'),
            (bool)$formValues['photo_keep_original'],
            array('helpTextId' => array('SYS_KEEP_ORIGINAL_DESC', array('SYS_ENABLE_DOWNLOAD')))
        );
        $formPhotos->addCheckbox(
            'photo_ecard_enabled',
            $gL10n->get('SYS_ENABLE_GREETING_CARDS'),
            (bool)$formValues['photo_ecard_enabled'],
            array('helpTextId' => 'SYS_ENABLE_GREETING_CARDS_DESC')
        );
        $formPhotos->addInput(
            'photo_ecard_scale',
            $gL10n->get('SYS_THUMBNAIL_SCALING'),
            $formValues['photo_ecard_scale'],
            array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_ECARD_MAX_PHOTO_SIZE_DESC', array(500)))
        );
        $formPhotos->addSelectBox(
            'photo_ecard_template',
            $gL10n->get('SYS_TEMPLATE'),
            $this->getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates'),
            array(
                'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $formValues['photo_ecard_template']))),
                'showContextDependentFirstEntry' => false,
                'arrayKeyIsNotValue' => true,
                'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
                'helpTextId' => 'SYS_TEMPLATE_DESC'
            )
        );
        $formPhotos->addSubmitButton(
            'btn_save_photos',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formPhotos->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.photos.tpl');
    }

    /**
     * Generates the html of the form from the PHP preferences and will return the complete html.
     * @return string Returns the complete html of the form from the PHP preferences.
     * @throws AdmException|Exception
     */
    public function createPHPForm(): string
    {
        global $gL10n;

        if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
            $phpVersionColorClass = 'text-danger';
            $phpVersionInfo = ' &rarr; ' . $gL10n->get('SYS_PHP_VERSION_REQUIRED', array(MIN_PHP_VERSION));
        } elseif (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
            $phpVersionColorClass = 'text-warning';
            $phpVersionInfo = ' &rarr; ' . $gL10n->get('SYS_PHP_VERSION_EOL', array('<a href="https://www.php.net/supported-versions.php" target="_blank">Supported Versions</a>'));
        } else {
            $phpVersionColorClass = 'text-success';
            $phpVersionInfo = '';
        }
        $this->assignSmartyVariable('phpVersionColorClass', $phpVersionColorClass);
        $this->assignSmartyVariable('phpVersionText', PHP_VERSION);
        $this->assignSmartyVariable('phpVersionInfo', $phpVersionInfo);

        $postMaxSize = PhpIniUtils::getPostMaxSize();
        if (is_infinite($postMaxSize)) {
            $postMaxSizeColorClass = 'text-warning';
            $postMaxSizeText = $gL10n->get('SYS_NOT_SET');
        } else {
            $postMaxSizeColorClass = 'text-success';
            $postMaxSizeText = FileSystemUtils::getHumanReadableBytes($postMaxSize);
        }
        $this->assignSmartyVariable('postMaxSizeColorClass', $postMaxSizeColorClass);
        $this->assignSmartyVariable('postMaxSizeText', $postMaxSizeText);

        $memoryLimit = PhpIniUtils::getMemoryLimit();
        if (is_infinite($memoryLimit)) {
            $memoryLimitColorClass = 'text-warning';
            $memoryLimitText = $gL10n->get('SYS_NOT_SET');
        } else {
            $memoryLimitColorClass = 'text-success';
            $memoryLimitText = FileSystemUtils::getHumanReadableBytes($memoryLimit);
        }
        $this->assignSmartyVariable('memoryLimitColorClass', $memoryLimitColorClass);
        $this->assignSmartyVariable('memoryLimitText', $memoryLimitText);

        if (PhpIniUtils::isFileUploadEnabled()) {
            $fileUploadsColorClass = 'text-success';
            $fileUploadsText = $gL10n->get('SYS_ON');
        } else {
            $fileUploadsColorClass = 'text-danger';
            $fileUploadsText = $gL10n->get('SYS_OFF');
        }
        $this->assignSmartyVariable('fileUploadsColorClass', $fileUploadsColorClass);
        $this->assignSmartyVariable('fileUploadsText', $fileUploadsText);

        $fileUploadMaxFileSize = PhpIniUtils::getFileUploadMaxFileSize();
        if (is_infinite($fileUploadMaxFileSize)) {
            $uploadMaxFilesizeColorClass = 'text-warning';
            $uploadMaxFilesizeText = $gL10n->get('SYS_NOT_SET');
        } else {
            $uploadMaxFilesizeColorClass = 'text-success';
            $uploadMaxFilesizeText = FileSystemUtils::getHumanReadableBytes($fileUploadMaxFileSize);
        }
        $this->assignSmartyVariable('uploadMaxFilesizeColorClass', $uploadMaxFilesizeColorClass);
        $this->assignSmartyVariable('uploadMaxFilesizeText', $uploadMaxFilesizeText);

        try {
            SecurityUtils::getRandomInt(0, 1, true);
            $prnGeneratorColorClass = 'text-success';
            $prnGeneratorText = $gL10n->get('SYS_SECURE');
            $prnGeneratorInfo = '';
        } catch (AdmException $e) {
            $prnGeneratorColorClass = 'text-danger';
            $prnGeneratorText = $gL10n->get('SYS_PRNG_INSECURE');
            $prnGeneratorInfo =  '<br />' . $e->getMessage();
        }
        $this->assignSmartyVariable('prnGeneratorColorClass', $prnGeneratorColorClass);
        $this->assignSmartyVariable('prnGeneratorText', $prnGeneratorText);
        $this->assignSmartyVariable('prnGeneratorInfo', $prnGeneratorInfo);

        $this->assignSmartyVariable('admidioUrl', ADMIDIO_URL);

        $smarty = $this->getSmartyTemplate();
        return $smarty->fetch('preferences/preferences.php.tpl');
    }

    /**
     * Generates the html of the form from the regional settings preferences and will return the complete html.
     * @return string Returns the complete html of the form from the regional settings preferences.
     * @throws AdmException|Exception
     */
    public function createRegionalSettingsForm(): string
    {
        global $gL10n, $gSettingsManager, $gTimezone;

        $formValues = $gSettingsManager->getAll();

        $formRegionalSettings = new Form(
            'preferencesFormRegionalSettings',
            'preferences/preferences.regional-settings.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'RegionalSettings')),
            null,
            array('class' => 'form-preferences')
        );
        $formRegionalSettings->addInput(
            'system_timezone',
            $gL10n->get('ORG_TIMEZONE'),
            $gTimezone,
            array('property' => Form::FIELD_DISABLED, 'class' => 'form-control-small', 'helpTextId' => 'ORG_TIMEZONE_DESC')
        );
        $formRegionalSettings->addSelectBox(
            'system_language',
            $gL10n->get('SYS_LANGUAGE'),
            $gL10n->getAvailableLanguages(),
            array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $formValues['system_language'], 'helpTextId' => array('SYS_LANGUAGE_HELP_TRANSLATION', array('<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:uebersetzen">', '</a>')))
        );
        $formRegionalSettings->addSelectBox(
            'default_country',
            $gL10n->get('SYS_DEFAULT_COUNTRY'),
            $gL10n->getCountries(),
            array('defaultValue' => $formValues['default_country'], 'helpTextId' => 'SYS_DEFAULT_COUNTRY_DESC')
        );
        $formRegionalSettings->addInput(
            'system_date',
            $gL10n->get('ORG_DATE_FORMAT'),
            $formValues['system_date'],
            array('maxLength' => 20, 'helpTextId' => array('ORG_DATE_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
        );
        $formRegionalSettings->addInput(
            'system_time',
            $gL10n->get('ORG_TIME_FORMAT'),
            $formValues['system_time'],
            array('maxLength' => 20, 'helpTextId' => array('ORG_TIME_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
        );
        $formRegionalSettings->addInput(
            'system_currency',
            $gL10n->get('ORG_CURRENCY'),
            $formValues['system_currency'],
            array('maxLength' => 20, 'helpTextId' => 'ORG_CURRENCY_DESC', 'class' => 'form-control-small')
        );
        $formRegionalSettings->addSubmitButton(
            'btn_save_regional_settings',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formRegionalSettings->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.regional-settings.tpl');
    }

    /**
     * Generates the html of the form from the registration preferences and will return the complete html.
     * @return string Returns the complete html of the form from the registration preferences.
     * @throws AdmException|Exception
     */
    public function createRegistrationForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formRegistration = new Form(
            'preferencesFormRegistration',
            'preferences/preferences.registration.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Registration')),
            null,
            array('class' => 'form-preferences')
        );
        $formRegistration->addCheckbox(
            'registration_enable_module',
            $gL10n->get('ORG_ENABLE_REGISTRATION_MODULE'),
            (bool)$formValues['registration_enable_module'],
            array('helpTextId' => 'ORG_ENABLE_REGISTRATION_MODULE_DESC')
        );
        $formRegistration->addCheckbox(
            'registration_manual_approval',
            $gL10n->get('SYS_MANUAL_APPROVAL'),
            (bool)$formValues['registration_manual_approval'],
            array('helpTextId' => array('SYS_MANUAL_APPROVAL_DESC', array('SYS_RIGHT_APPROVE_USERS')))
        );
        $formRegistration->addCheckbox(
            'registration_enable_captcha',
            $gL10n->get('ORG_ENABLE_CAPTCHA'),
            (bool)$formValues['registration_enable_captcha'],
            array('helpTextId' => 'ORG_CAPTCHA_REGISTRATION')
        );
        $formRegistration->addCheckbox(
            'registration_adopt_all_data',
            $gL10n->get('SYS_REGISTRATION_ADOPT_ALL_DATA'),
            (bool)$formValues['registration_adopt_all_data'],
            array('helpTextId' => 'SYS_REGISTRATION_ADOPT_ALL_DATA_DESC')
        );
        $formRegistration->addCheckbox(
            'registration_send_notification_email',
            $gL10n->get('ORG_EMAIL_ALERTS'),
            (bool)$formValues['registration_send_notification_email'],
            array('helpTextId' => array('ORG_EMAIL_ALERTS_DESC', array('SYS_RIGHT_APPROVE_USERS')))
        );
        $formRegistration->addSubmitButton(
            'btn_save_registration',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formRegistration->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.registration.tpl');
    }

    /**
     * Generates the html of the form from the security preferences and will return the complete html.
     * @return string Returns the complete html of the form from the security preferences.
     * @throws AdmException|Exception
     */
    public function createSecurityForm(): string
    {
        global $gL10n, $gSettingsManager;

        $formValues = $gSettingsManager->getAll();

        $formSecurity = new Form(
            'preferencesFormSecurity',
            'preferences/preferences.security.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'Security')),
            null,
            array('class' => 'form-preferences')
        );
        $formSecurity->addInput(
            'logout_minutes',
            $gL10n->get('ORG_AUTOMATIC_LOGOUT_AFTER'),
            $formValues['logout_minutes'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('ORG_AUTOMATIC_LOGOUT_AFTER_DESC', array('SYS_REMEMBER_ME')))
        );
        $selectBoxEntries = array(
            0 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_NO'),
            1 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_LOW'),
            2 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_MID'),
            3 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_HIGH'),
            4 => $gL10n->get('ORG_PASSWORD_MIN_STRENGTH_VERY_HIGH')
        );
        $formSecurity->addSelectBox(
            'password_min_strength',
            $gL10n->get('ORG_PASSWORD_MIN_STRENGTH'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['password_min_strength'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_PASSWORD_MIN_STRENGTH_DESC')
        );
        $formSecurity->addCheckbox(
            'enable_auto_login',
            $gL10n->get('ORG_LOGIN_AUTOMATICALLY'),
            (bool)$formValues['enable_auto_login'],
            array('helpTextId' => 'ORG_LOGIN_AUTOMATICALLY_DESC')
        );
        $formSecurity->addCheckbox(
            'enable_password_recovery',
            $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
            (bool)$formValues['enable_password_recovery'],
            array('helpTextId' => array('SYS_PASSWORD_FORGOTTEN_PREF_DESC', array('SYS_ENABLE_NOTIFICATIONS')))
        );
        $formSecurity->addSubmitButton(
            'btn_save_security',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formSecurity->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.security.tpl');
    }

    /**
     * Generates the html of the form from the system information preferences and will return the complete html.
     * @return string Returns the complete html of the form from the system information preferences.
     * @throws AdmException|Exception
     */
    public function createSystemInformationForm(): string
    {
        global $gL10n, $gDb, $gLogger;

        $this->assignSmartyVariable('operatingSystemName', SystemInfoUtils::getOS());
        $this->assignSmartyVariable('operatingSystemUserName', SystemInfoUtils::getUname());

        if (SystemInfoUtils::is64Bit()) {
            $architectureOSColorClass = 'text-success';
            $architectureOSText = $gL10n->get('SYS_YES');
        } else {
            $architectureOSColorClass = '';
            $architectureOSText = $gL10n->get('SYS_NO');
        }
        $this->assignSmartyVariable('architectureOSColorClass', $architectureOSColorClass);
        $this->assignSmartyVariable('architectureOSText', $architectureOSText);

        if (SystemInfoUtils::isUnixFileSystem()) {
            $unixText = $gL10n->get('SYS_YES');
        } else {
            $unixText = $gL10n->get('SYS_NO');
        }
        $this->assignSmartyVariable('unixText', $unixText);

        $this->assignSmartyVariable('directorySeparator', SystemInfoUtils::getDirectorySeparator());
        $this->assignSmartyVariable('pathSeparator', SystemInfoUtils::getPathSeparator());
        $this->assignSmartyVariable('maxPathLength', SystemInfoUtils::getMaxPathLength());

        if (version_compare($gDb->getVersion(), $gDb->getMinimumRequiredVersion(), '<')) {
            $databaseVersionColorClass = 'text-danger';
            $databaseVersionText = $gDb->getVersion();
            $databaseVersionInfo = ' &rarr; ' . $gL10n->get('SYS_DATABASE_VERSION_REQUIRED', array($gDb->getMinimumRequiredVersion()));
        } else {
            $databaseVersionColorClass = 'text-success';
            $databaseVersionText = $gDb->getVersion();
            $databaseVersionInfo = '';
        }
        $this->assignSmartyVariable('databaseVersionName', $gDb->getName() . '-' . $gL10n->get('SYS_VERSION'));
        $this->assignSmartyVariable('databaseVersionColorClass', $databaseVersionColorClass);
        $this->assignSmartyVariable('databaseVersionText', $databaseVersionText);
        $this->assignSmartyVariable('databaseVersionInfo', $databaseVersionInfo);

        if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/.htaccess')) {
            $directoryProtectionColorClass = 'text-success';
            $directoryProtectionText = $gL10n->get('SYS_SECURE');
            $directoryProtectionInfo = '';
        } else {
            $directoryProtectionColorClass = 'text-danger';
            $directoryProtectionText = '<span id="directory_protection_status">' . $gL10n->get('SYS_OFF') . '</span>';
            $directoryProtectionInfo = ' &rarr; <a id="link_directory_protection" href="#link_directory_protection" title="' . $gL10n->get('SYS_CREATE_HTACCESS') . '">' . $gL10n->get('SYS_CREATE_HTACCESS') . '</a>';
        }
        $this->assignSmartyVariable('directoryProtectionColorClass', $directoryProtectionColorClass);
        $this->assignSmartyVariable('directoryProtectionText', $directoryProtectionText);
        $this->assignSmartyVariable('directoryProtectionInfo', $directoryProtectionInfo);

        $this->assignSmartyVariable('maxProcessableImageSize', round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2) . ' ' . $gL10n->get('SYS_MEGAPIXEL'));

        if (isset($gDebug) && $gDebug) {
            $debugModeColorClass = 'text-danger';
            $debugModeText = $gL10n->get('SYS_ON');
        } else {
            $debugModeColorClass = 'text-success';
            $debugModeText = $gL10n->get('SYS_OFF');
        }
        $this->assignSmartyVariable('debugModeColorClass', $debugModeColorClass);
        $this->assignSmartyVariable('debugModeText', $debugModeText);

        if (isset($gImportDemoData) && $gImportDemoData) {
            $importModeColorClass = 'text-danger';
            $importModeText = $gL10n->get('SYS_ON');
        } else {
            $importModeColorClass = 'text-success';
            $importModeText = $gL10n->get('SYS_OFF');
        }
        $this->assignSmartyVariable('importModeColorClass', $importModeColorClass);
        $this->assignSmartyVariable('importModeText', $importModeText);

        try {
            $diskSpace = FileSystemUtils::getDiskSpace();
            $progressBarClass = '';

            $diskUsagePercent = round(($diskSpace['used'] / $diskSpace['total']) * 100, 1);
            if ($diskUsagePercent > 90) {
                $progressBarClass = ' progress-bar-danger';
            } elseif ($diskUsagePercent > 70) {
                $progressBarClass = ' progress-bar-warning';
            }
            $diskSpaceContent = '
                <div class="progress">
                    <div class="progress-bar' . $progressBarClass . '" role="progressbar" aria-valuenow="' . $diskSpace['used'] . '" aria-valuemin="0" aria-valuemax="' . $diskSpace['total'] . '" style="width: ' . $diskUsagePercent . '%;">
                        ' . FileSystemUtils::getHumanReadableBytes($diskSpace['used']) . ' / ' . FileSystemUtils::getHumanReadableBytes($diskSpace['total']) . '
                    </div>
                </div>';
        } catch (RuntimeException $exception) {
            $gLogger->error('FILE-SYSTEM: Disk space could not be determined!');

            $diskSpaceContent = $gL10n->get('SYS_DISK_SPACE_ERROR', array($exception->getMessage()));
        }
        $this->assignSmartyVariable('diskSpaceContent', $diskSpaceContent);

        $smarty = $this->getSmartyTemplate();
        return $smarty->fetch('preferences/preferences.system-information.tpl');
    }

    /**
     * Generates the html of the form from the system notifications preferences and will return the complete html.
     * @return string Returns the complete html of the form from the system notifications preferences.
     * @throws AdmException|Exception
     */
    public function createSystemNotificationsForm(): string
    {
        global $gL10n, $gDb, $gSettingsManager, $gCurrentOrgId;

        $formValues = $gSettingsManager->getAll();

        $formSystemNotification = new Form(
            'preferencesFormSystemNotifications',
            'preferences/preferences.system-notifications.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php', array('mode' => 'save', 'form' => 'SystemNotifications')),
            null,
            array('class' => 'form-preferences')
        );
        $formSystemNotification->addCheckbox(
            'system_notifications_enabled',
            $gL10n->get('SYS_ENABLE_NOTIFICATIONS'),
            (bool)$formValues['system_notifications_enabled'],
            array('helpTextId' => 'SYS_ENABLE_NOTIFICATIONS_DESC')
        );
        $formSystemNotification->addCheckbox(
            'system_notifications_new_entries',
            $gL10n->get('SYS_NOTIFICATION_NEW_ENTRIES'),
            (bool)$formValues['system_notifications_new_entries'],
            array('helpTextId' => 'SYS_NOTIFICATION_NEW_ENTRIES_DESC')
        );
        $formSystemNotification->addCheckbox(
            'system_notifications_profile_changes',
            $gL10n->get('SYS_NOTIFICATION_PROFILE_CHANGES'),
            (bool)$formValues['system_notifications_profile_changes'],
            array('helpTextId' => 'SYS_NOTIFICATION_PROFILE_CHANGES_DESC')
        );

        // read all roles of the organization
        $sqlData = array();
        $sqlData['query'] = 'SELECT rol_uuid, rol_name, cat_name
               FROM ' . TBL_ROLES . '
         INNER JOIN ' . TBL_CATEGORIES . '
                 ON cat_id = rol_cat_id
         INNER JOIN ' . TBL_ORGANIZATIONS . '
                 ON org_id = cat_org_id
              WHERE rol_valid  = true
                AND rol_system = false
                AND rol_all_lists_view = true
                AND cat_org_id = ? -- $gCurrentOrgId
                AND cat_name_intern <> \'EVENTS\'
           ORDER BY cat_name, rol_name';
        $sqlData['params'] = array($gCurrentOrgId);
        $formSystemNotification->addSelectBoxFromSql(
            'system_notifications_role',
            $gL10n->get('SYS_NOTIFICATION_ROLE'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['system_notifications_role'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NOTIFICATION_ROLE_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW')))
        );

        $text = new TableText($gDb);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_CONFIRMATION', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_CONFIRMATION', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_CONFIRMATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_NEW', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_NEW', $gL10n->get('SYS_NOTIFICATION_NEW_REGISTRATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_APPROVED', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_APPROVED', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_APPROVAL'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_REFUSED', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotification->addMultilineTextInput('SYSMAIL_REGISTRATION_REFUSED', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrgId));
        $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_NEW_PASSWORD');
        $formSystemNotification->addMultilineTextInput(
            'SYSMAIL_NEW_PASSWORD',
            $gL10n->get('ORG_SEND_NEW_PASSWORD'),
            $text->getValue('txt_text'),
            7,
            array('helpTextId' => $htmlDesc)
        );
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_PASSWORD_RESET', 'txt_org_id' => $gCurrentOrgId));
        $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_ACTIVATION_LINK');
        $formSystemNotification->addMultilineTextInput(
            'SYSMAIL_PASSWORD_RESET',
            $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
            $text->getValue('txt_text'),
            7,
            array('helpTextId' => $htmlDesc)
        );
        $formSystemNotification->addSubmitButton(
            'btn_save_system_notification',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formSystemNotification->addToSmarty($smarty);
        return $smarty->fetch('preferences/preferences.system-notifications.tpl');
    }

    /**
     * Read all file names of a folder and return an array where the file names are the keys and a readable
     * version of the file names are the values.
     * @param string $folder Server path with folder name of whom the files should be read.
     * @return array<int,string> Array with all file names of the given folder.
     */
    private function getArrayFileNames(string $folder): array
    {
        // get all files from the folder
        $files = array_keys(FileSystemUtils::getDirectoryContent($folder, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));

        foreach ($files as &$templateName) {
            $templateName = ucfirst(preg_replace('/[_-]/', ' ', str_replace(array('.tpl', '.html', '.txt'), '', $templateName)));
        }
        unset($templateName);

        return $files;
    }

    /**
     * Read all available registrations from the database and create the html content of this
     * page with the Smarty template engine and write the html output to the internal
     * parameter **$pageContent**. If no registration is found than show a message to the user.
     */
    public function show()
    {
        if ($this->preferencesPanelToShow !== '') {
            // open the modules tab if the options of a module should be shown
            if (array_key_exists($this->preferencesPanelToShow, $this->accordionModulePanels)) {
                $this->addJavascript(
                    '
                $("#tabsNavModules").attr("class", "nav-link active");
                $("#tabsModules").attr("class", "tab-pane fade show active");
                $("#collapsePreferencesModule' . $this->preferencesPanelToShow . '").attr("class", "collapse show");
                location.hash = "#admidioPanelPreferencesModule' . $this->preferencesPanelToShow . '";',
                    true
                );
            } else {
                $this->addJavascript(
                    '
                $("#tabsNavCommon").attr("class", "nav-link active");
                $("#tabsCommon").attr("class", "tab-pane fade show active");
                $("#collapsePreferencesCommon' . $this->preferencesPanelToShow . '").attr("class", "collapse show");
                location.hash = "#admidioPanelPreferencesModule' . $this->preferencesPanelToShow . '";',
                    true
                );
            }
        }

        $this->addJavascript(
            '
            var panels = ["Common", "Security", "Organization", "RegionalSettings", "Registration", "EmailDispatch", "SystemNotifications", "Captcha", "AdmidioUpdate", "PHP", "SystemInformation",
                "Announcements", "Contacts", "DocumentsFiles", "Photos", "Guestbook"];

            for(var i = 0; i < panels.length; i++) {
                $("#admidioPanelPreferences" + panels[i] + " .accordion-header").click(function (e) {
                    var id = $(this).data("preferences-panel");
                    if ($("#admidioPanelPreferences" + id + " h2").attr("aria-expanded") == "true") {
                        $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php?mode=html_form&form=" + id, function (data) {
                            $("#admidioPanelPreferences" + id + " .accordion-body").html(data);
                        });
                    }
                });

                $(document).on("submit", "#preferencesForm" + panels[i], formSubmit);
            }

            $(document).on("click", "#captcha-refresh", (function() {
                document.getElementById("captcha").src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php?" + Math.random();
            }));

            $(document).on("click", "#link_check_for_update", (function() {
                var admVersionContent = $("#admidio_version_content");

                admVersionContent.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/update_check.php", {mode: "2"}, function(htmlVersion) {
                    admVersionContent.html(htmlVersion);
                });
                return false;
            }));

            $(document).on("click", "#link_directory_protection", (function() {
                var dirProtectionStatus = $("#directory_protection_status");

                dirProtectionStatus.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php", {mode: "htaccess"}, function(statusText) {
                    var directoryProtection = dirProtectionStatus.parent().parent().parent();
                    directoryProtection.html("<span class=\"text-success\"><strong>" + statusText + "</strong></span>");
                });
                return false;
            }));',
            true
        );

        $this->assignSmartyVariable('accordionCommonPanels', $this->accordionCommonPanels);
        $this->assignSmartyVariable('accordionModulePanels', $this->accordionModulePanels);
        $this->addTemplateFile('preferences/preferences.tpl');

        parent::show();
    }
}
