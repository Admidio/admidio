<?php
namespace Admidio\UI\Presenter;

use Admidio\Changelog\Service\ChangelogService;
use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\SSO\Service\KeyService;
use RuntimeException;

/**
 * @brief Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate HTML output with available registrations
 * $page = new ModuleRegistration('admidio-registration', $headline);
 * $page->createRegistrationList();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PreferencesPresenter extends PagePresenter
{
    /**
     * @var array Array with all possible entries for the preferences.
     *            Each entry consists of an array that has the following structure:
     *            array ('key' => 'xzy', 'label' => 'xyz', 'panels' => array('id' => 'xyz', 'title' => 'xyz', 'icon' => 'xyz'))
     *
     *            There are thwo different visualizations of the preferences:
     *              1) a nested tab structure (main tabs created by 'key' and 'label' and sub tabs created by 'panels')
     *              2) a accordion structure when the @media query (max-width: 768px) is active ('key' and 'label' are used for card header
     *                 and 'panels' for accordions inside the card)
     */
    protected array $preferenceTabs = array();
    /**
     * @var string Name of the preference panel that should be shown after page loading.
     *             If this parameter is empty, then show the common preferences.
     */
    protected string $preferencesPanelToShow = '';

    /**
     * Constructor that initializes the class member parameters
     * @throws Exception
     */
    public function __construct(string $panel = '')
    {
        global $gL10n;

        $this->initialize();
        $this->setPanelToShow($panel);

        $this->setHtmlID('adm_preferences');
        $this->setHeadline($gL10n->get('SYS_SETTINGS'));

        parent::__construct();
    }

    /**
     * @throws Exception
     */
    private function initialize(): void
    {
        global $gL10n;
        $this->preferenceTabs = array(
            // === 1) System ===
            array(
                'key'    => 'system',
                'label'  => $gL10n->get('SYS_SYSTEM'),
                'panels' => array(
                    array('id'=>'common',               'title'=>$gL10n->get('SYS_COMMON'),                 'icon'=>'bi-gear-fill',                     'subcards'=>false),
                    array('id'=>'design',               'title'=>$gL10n->get('SYS_DESIGN'),                 'icon'=>'bi-palette',                       'subcards'=>false),
                    array('id'=>'regional_settings',    'title'=>$gL10n->get('ORG_REGIONAL_SETTINGS'),      'icon'=>'bi-globe2',                        'subcards'=>false),
                    array('id'=>'changelog',            'title'=>$gL10n->get('SYS_CHANGE_HISTORY'),         'icon'=>'bi-clock-history',                 'subcards'=>false),
                    array('id'=>'system_information',   'title'=>$gL10n->get('SYS_INFORMATIONS'),           'icon'=>'bi-info-circle-fill',              'subcards'=>true),
                ),
            ),

            // === 2) Login and Security ===
            array(
                'key'    => 'login_security',
                'label'  =>  $gL10n->get('SYS_LOGIN') . ' & ' . $gL10n->get('SYS_SECURITY'),
                'panels' => array(
                    array('id'=>'security',             'title'=>$gL10n->get('SYS_SECURITY'),               'icon'=>'bi-shield-fill',                   'subcards'=>false),
                    array('id'=>'registration',         'title'=>$gL10n->get('SYS_REGISTRATION'),           'icon'=>'bi-card-checklist',                'subcards'=>false),
                    array('id'=>'captcha',              'title'=>$gL10n->get('SYS_CAPTCHA'),                'icon'=>'bi-fonts',                         'subcards'=>false),
                    array('id'=>'sso',                  'title'=>$gL10n->get('SYS_SSO'),                    'icon'=>'bi-key',                           'subcards'=>false),
                ),
            ),

            // === 3) User Management ===
            array(
                'key'    => 'user_management',
                'label'  => $gL10n->get('SYS_USERS'),
                'panels' => array(
                    array('id'=>'contacts',             'title'=>$gL10n->get('SYS_CONTACTS'),               'icon'=>'bi-person-vcard-fill',             'subcards'=>false),
                    array('id'=>'profile',              'title'=>$gL10n->get('SYS_PROFILE'),                'icon'=>'bi-person-fill',                   'subcards'=>false),
                    array('id'=>'groups_roles',         'title'=>$gL10n->get('SYS_GROUPS_ROLES'),           'icon'=>'bi-people-fill',                   'subcards'=>false),
                    array('id'=>'category_report',      'title'=>$gL10n->get('SYS_CATEGORY_REPORT'),        'icon'=>'bi-list-stars',                    'subcards'=>false),
                ),
            ),

            // === 4) Communication ===
            array(
                'key'    => 'communication',
                'label'  => $gL10n->get('SYS_COMMUNICATION'),
                'panels' => array(
                    array('id'=>'system_notifications', 'title'=>$gL10n->get('SYS_SYSTEM_MAILS'),           'icon'=>'bi-broadcast-pin',                 'subcards'=>false),
                    array('id'=>'email_dispatch',       'title'=>$gL10n->get('SYS_MAIL_DISPATCH'),          'icon'=>'bi-envelope-open-fill',            'subcards'=>false),
                    array('id'=>'messages',             'title'=>$gL10n->get('SYS_MESSAGES'),               'icon'=>'bi-envelope-fill',                 'subcards'=>false),
                    array('id'=>'announcements',        'title'=>$gL10n->get('SYS_ANNOUNCEMENTS'),          'icon'=>'bi-newspaper',                     'subcards'=>false),
                    array('id'=>'forum',                'title'=>$gL10n->get('SYS_FORUM'),                  'icon'=>'bi-chat-dots-fill',                'subcards'=>false),
                ),
            ),

            // === 5) Contents ===
            array(
                'key'    => 'content_management',
                'label'  => $gL10n->get('SYS_CONTENTS'),
                'panels' => array(
                    array('id'=>'events',               'title'=>$gL10n->get('SYS_EVENTS'),                 'icon'=>'bi-calendar-week-fill',            'subcards'=>false),
                    array('id'=>'documents_files',      'title'=>$gL10n->get('SYS_DOCUMENTS_FILES'),        'icon'=>'bi-file-earmark-arrow-down-fill',  'subcards'=>false),
                    array('id'=>'inventory',            'title'=>$gL10n->get('SYS_INVENTORY'),              'icon'=>'bi-box-seam-fill',                 'subcards'=>false),
                    array('id'=>'photos',               'title'=>$gL10n->get('SYS_PHOTOS'),                 'icon'=>'bi-image-fill',                    'subcards'=>false),
                    array('id'=>'links',                'title'=>$gL10n->get('SYS_WEBLINKS'),               'icon'=>'bi-link-45deg',                    'subcards'=>false),
                ),
            ),
        );
    }

    /**
     * Generates the HTML of the form from the announcement preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the announcement preferences.
     * @throws Exception|\Smarty\Exception
     */
    public function createAnnouncementsForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formAnnouncements = new FormPresenter(
            'adm_preferences_form_announcements',
            'preferences/preferences.announcements.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'announcements')),
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
            $gL10n->get('SYS_NUMBER_OF_ENTRIES_PER_PAGE'),
            $formValues['announcements_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
        );
         $formAnnouncements->addInput(
            'announcements_clamp_text_lines',
            $gL10n->get('SYS_CLAMP_TEXT_LINES'),
            $formValues['announcements_clamp_text_lines'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => array('SYS_CLAMP_TEXT_LINES_DESC', array('SYS_ANNOUNCEMENT')))
        );
        $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'ANN')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
        $formAnnouncements->addCustomContent(
            'maintainCategories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            $html,
            array('helpTextId' => 'SYS_MAINTAIN_CATEGORIES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formAnnouncements->addSubmitButton(
            'adm_button_save_announcements',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formAnnouncements->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formAnnouncements);
        return $smarty->fetch('preferences/preferences.announcements.tpl');
    }

    /**
     * Generates the HTML of the form from the captcha preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the captcha preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createCaptchaForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formCaptcha = new FormPresenter(
            'adm_preferences_form_captcha',
            'preferences/preferences.captcha.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'captcha')),
            null,
            array('class' => 'form-preferences')
        );

        // search all available themes in the theme folder
        $themes = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_THEMES, false, false, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY)));
        if (count($themes) === 0) {
            throw new Exception('SYS_TEMPLATE_FOLDER_OPEN');
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

        $fonts = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_SYSTEM . '/fonts/', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));
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
        $html = '<img id="adm_captcha" src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
         <a id="adm_captcha_refresh" class="admidio-icon-link" href="javascript:void(0)">
            <i class="bi bi-arrow-repeat" style="font-size: 22pt;" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_RELOAD') . '"></i></a>';
        $formCaptcha->addCustomContent(
            'captchaPreview',
            $gL10n->get('ORG_CAPTCHA_PREVIEW'),
            $html,
            array('helpTextId' => 'ORG_CAPTCHA_PREVIEW_TEXT')
        );
        $formCaptcha->addSubmitButton(
            'adm_button_save_captcha',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formCaptcha->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formCaptcha);
        return $smarty->fetch('preferences/preferences.captcha.tpl');
    }

    /**
     * Generates the HTML of the form from the category report preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the category report preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createCategoryReportForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formCategoryReport = new FormPresenter(
            'adm_preferences_form_category_report',
            'preferences/preferences.category-report.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'category_report')),
            null,
            array('class' => 'form-preferences')
        );
        $formCategoryReport->addCheckbox(
            'category_report_module_enabled',
            $gL10n->get('SYS_ENABLE_CATEGORY_REPORT'),
            (bool) $formValues['category_report_module_enabled'],
            array('helpTextId' => array('SYS_ENABLE_CATEGORY_REPORT_DESC', array($gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'))))
        );
        // read all global lists
        $sqlData = array();
        $sqlData['query'] = 'SELECT crt_id, crt_name
                       FROM ' . TBL_CATEGORY_REPORT . '
                      WHERE crt_org_id = ? -- $gCurrentOrgId
                   ORDER BY crt_name';
        $sqlData['params'] = array($gCurrentOrgId);
        $formCategoryReport->addSelectBoxFromSql(
            'category_report_default_configuration',
            $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['category_report_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_CONFIGURATION_CAT_REP_DESC')
        );

        $formCategoryReport->addSubmitButton(
            'adm_button_save_category_report',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formCategoryReport->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formCategoryReport);
        return $smarty->fetch('preferences/preferences.category-report.tpl');
    }

    /**
     * Generates the HTML of the form from the changelog preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the changelog report preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createChangelogForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formChangelog = new FormPresenter(
            'adm_preferences_form_changelog',
            'preferences/preferences.changelog.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'changelog')),
            null,
            array('class' => 'form-preferences')
        );

        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_ADMINISTRATOR')
        );
        $formChangelog->addSelectBox(
            'changelog_module_enabled',
            $gL10n->get('SYS_ENABLE_CHANGELOG'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['changelog_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_ENABLE_CHANGELOG_DESC')
        );

        $tablesMap = array_map([$gL10n, 'translateIfTranslationStrId'], ChangelogService::getTableLabel());
        // $selectedTables = explode(',', $formValues['changelog_tables']??'');
        $formChangelog->addCustomContent(
            'changelog_tables',
            $gL10n->get('SYS_LOGGED_TABLES'),
            $gL10n->get('SYS_LOGGED_TABLES_DESC'),
            array(
                'tables' => array(
                    array(
                        'title' => $gL10n->get('SYS_HEADER_USER_ROLE_DATA'),
                        'id' => 'user_role_data',
                        'tables' => array('users', 'user_data', 'members', 'user_relations', 'roles', 'role_dependencies', 'category_report')
                    ),
                    array(
                        'title' => $gL10n->get('SYS_HEADER_USER_ROLE_SETTINGS'),
                        'id' => 'user_role_settings',
                        'tables' => array('user_fields', 'user_field_select_options', 'user_relation_types', 'roles_rights', 'roles_rights_data')
                    ),
                    array(
                        'title' => $gL10n->get('SYS_HEADER_CONTENT_MODULES'),
                        'id' => 'content_modules',
                        'tables' => array('files', 'folders', 'photos', 'announcements', 'events', 'rooms', 'forum_topics', 'forum_posts', 'inventory_fields', 'inventory_field_select_options', 'inventory_items', 'inventory_item_data', 'inventory_item_borrow_data', 'links', 'others')
                    ),
                    array(
                        'title' => $gL10n->get('SYS_HEADER_PREFERENCES'),
                        'id' => 'preferences',
                        'tables' => array('organizations', 'menu', 'preferences', 'texts', 'lists', 'list_columns', 'categories', 'saml_clients', 'oidc_clients', 'sso_keys')
                    )
                )
            )
        );

        foreach ($tablesMap as $tableName => $tableLabel) {
            $formChangelog->addCheckbox(
                'changelog_table_' . $tableName,
                "$tableLabel ($tableName)",
                $formValues['changelog_table_' . $tableName] ?? false
            );
        }

        // $formChangelog->addCheckbox(
        //     'changelog_allow_deletion',
        //     $gL10n->get('SYS_LOG_ALLOW_DELETION'),
        //     (bool)($formValues['changelog_allow_deletion']??false),
        //     array('helpTextId' => 'SYS_LOG_ALLOW_DELETION_DESC')
        // );

        $formChangelog->addSubmitButton(
            'adm_button_save_changelog',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formChangelog->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formChangelog);
        return $smarty->fetch('preferences/preferences.changelog.tpl');
    }

    /**
     * Generates the HTML of the form from the common preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the common preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createCommonForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formCommon = new FormPresenter(
            'adm_preferences_form_common',
            'preferences/preferences.common.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'common')),
            null,
            array('class' => 'form-preferences')
        );

        $formCommon->addInput(
            'homepage_logout',
            $gL10n->get('SYS_HOMEPAGE') . ' (' . $gL10n->get('SYS_VISITORS') . ')',
            $formValues['homepage_logout'],
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'ORG_HOMEPAGE_VISITORS')
        );
        $formCommon->addInput(
            'homepage_login',
            $gL10n->get('SYS_HOMEPAGE') . ' (' . $gL10n->get('ORG_REGISTERED_USERS') . ')',
            $formValues['homepage_login'],
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'ORG_HOMEPAGE_REGISTERED_USERS')
        );
        $formCommon->addCheckbox(
            'enable_rss',
            $gL10n->get('SYS_ENABLE_RSS_FEEDS'),
            (bool) $formValues['enable_rss'],
            array('helpTextId' => array('SYS_ENABLE_RSS_FEEDS_DESC', array('SYS_YES')))
        );
        $formCommon->addCheckbox(
            'system_cookie_note',
            $gL10n->get('SYS_COOKIE_NOTE'),
            (bool) $formValues['system_cookie_note'],
            array('helpTextId' => 'SYS_COOKIE_NOTE_DESC')
        );
        $formCommon->addCheckbox(
            'system_search_similar',
            $gL10n->get('ORG_SEARCH_SIMILAR_NAMES'),
            (bool) $formValues['system_search_similar'],
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
            array('type' => 'url', 'maxLength' => 250, 'helpTextId' => 'SYS_DATA_PROTECTION_DESC')
        );
        $formCommon->addInput(
            'system_url_imprint',
            $gL10n->get('SYS_IMPRINT'),
            $formValues['system_url_imprint'],
            array('type' => 'url', 'maxLength' => 250, 'helpTextId' => 'SYS_IMPRINT_DESC')
        );
        $formCommon->addCheckbox(
            'system_js_editor_enabled',
            $gL10n->get('ORG_JAVASCRIPT_EDITOR_ENABLE'),
            (bool) $formValues['system_js_editor_enabled'],
            array('helpTextId' => 'ORG_JAVASCRIPT_EDITOR_ENABLE_DESC')
        );
        $formCommon->addCheckbox(
            'system_browser_update_check',
            $gL10n->get('ORG_BROWSER_UPDATE_CHECK'),
            (bool) $formValues['system_browser_update_check'],
            array('helpTextId' => 'ORG_BROWSER_UPDATE_CHECK_DESC')
        );
        $formCommon->addSubmitButton(
            'adm_button_save_common',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formCommon->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formCommon);
        return $smarty->fetch('preferences/preferences.common.tpl');
    }

    /**
     * Generates the HTML of the form from the contact preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the contact preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createContactsForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formContacts = new FormPresenter(
            'adm_preferences_form_contacts',
            'preferences/preferences.contacts.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'contacts')),
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
        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100', '-1' => $gL10n->get('SYS_ALL'));
        $formContacts->addSelectBox(
            'contacts_per_page',
            $gL10n->get('SYS_CONTACTS_PER_PAGE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['contacts_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_SELECT_DESC', array(25)))
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
            (bool) $formValues['contacts_show_all'],
            array('helpTextId' => 'SYS_SHOW_ALL_CONTACTS_DESC')
        );
        $formContacts->addCheckbox(
            'contacts_user_relations_enabled',
            $gL10n->get('SYS_ENABLE_USER_RELATIONS'),
            (bool) $formValues['contacts_user_relations_enabled'],
            array('helpTextId' => 'SYS_ENABLE_USER_RELATIONS_DESC')
        );

        $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/userrelations/relationtypes.php">
            <i class="bi bi-person-heart"></i>' . $gL10n->get('SYS_SWITCH_TO_RELATIONSHIP_CONFIGURATION') . '</a>';
        $formContacts->addCustomContent(
            'userRelations',
            $gL10n->get('SYS_USER_RELATIONS'),
            $html,
            array('helpTextId' => 'SYS_MAINTAIN_USER_RELATION_TYPES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );

        $formContacts->addSubmitButton(
            'adm_button_save_contacts',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formContacts->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formContacts);
        return $smarty->fetch('preferences/preferences.contacts.tpl');
    }

    /**
     * Generates the HTML of the form from the design preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the design preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createDesignForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formDesign = new FormPresenter(
            'adm_preferences_form_design',
            'preferences/preferences.design.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'design')),
            null,
            array('class' => 'form-preferences')
        );

        // search all available themes in the theme folder
        $themes = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_THEMES, false, false, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY)));
        if (count($themes) === 0) {
            throw new Exception('SYS_TEMPLATE_FOLDER_OPEN');
        }
        $formDesign->addSelectBox(
            'theme',
            $gL10n->get('ORG_ADMIDIO_THEME'),
            $themes,
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $formValues['theme'], 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_ADMIDIO_THEME_DESC')
        );
        $formDesign->addSelectBox(
            'theme_fallback',
            $gL10n->get('ORG_ADMIDIO_THEME_FALLBACK'),
            $themes,
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $formValues['theme_fallback'], 'arrayKeyIsNotValue' => true, 'helpTextId' => 'ORG_ADMIDIO_THEME_FALLBACK_DESC')
        );
        $formDesign->addInput(
            'color_primary',
            $gL10n->get('SYS_COLOR_PRIMARY'),
            $formValues['color_primary']??'#349aaa',
            array('type' => 'color', 'helpTextId' => 'SYS_COLOR_PRIMARY_DESC')
        );
        $formDesign->addInput(
            'color_secondary',
            $gL10n->get('SYS_COLOR_SECONDARY'),
            $formValues['color_secondary']??'#263340',
            array('type' => 'color', 'helpTextId' => 'SYS_COLOR_SECONDARY_DESC')
        );

        $formDesign->addInput(
            'additional_styles_file',
            $gL10n->get('SYS_ADDITIONAL_CSS_FILE'),
            $formValues['additional_styles_file']??'',
            array('helpTextId' => 'SYS_ADDITIONAL_CSS_FILE_DESC')
        );
        $formDesign->addInput(
            'logo_file',
            $gL10n->get('SYS_LOGO_FILE'),
            $formValues['logo_file']??'',
            array('helpTextId' => 'SYS_LOGO_FILE_DESC')
        );
        $formDesign->addInput(
            'favicon_file',
            $gL10n->get('SYS_FAVICON_FILE'),
            $formValues['favicon_file']??'',
            array('helpTextId' => 'SYS_FAVICON_FILE_DESC')
        );
        $formDesign->addSubmitButton(
            'adm_button_save_design',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formDesign->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formDesign);
        return $smarty->fetch('preferences/preferences.design.tpl');
    }

    /**
     * Generates the HTML of the form from the documents & files preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the documents & files preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createDocumentsFilesForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formDocumentsFiles = new FormPresenter(
            'adm_preferences_form_documents_files',
            'preferences/preferences.documents-files.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'documents_files')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formDocumentsFiles->addSelectBox(
            'documents_files_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['documents_files_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $formDocumentsFiles->addInput(
            'documents_files_max_upload_size',
            $gL10n->get('SYS_MAXIMUM_FILE_SIZE') . ' (MB)',
            $formValues['documents_files_max_upload_size'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999999, 'step' => 1, 'helpTextId' => 'SYS_MAXIMUM_FILE_SIZE_DESC')
        );
        $formDocumentsFiles->addSubmitButton(
            'adm_button_save_documents_files',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formDocumentsFiles->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formDocumentsFiles);
        return $smarty->fetch('preferences/preferences.documents-files.tpl');
    }

    /**
     * Generates the html of the form from the inventory preferences and will return the complete html.
     * @return string Returns the complete html of the form from the inventory preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createInventoryForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId, $gCurrentSession, $gCurrentUser;
        $formValues = $gSettingsManager->getAll();
        //array with the internal field names of the borrowing fields
        $borrowingFieldNames = array('LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE');

        $formInventory = new FormPresenter(
            'adm_preferences_form_inventory',
            'preferences/preferences.inventory.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'Inventory')),
            null,
            array('class' => 'form-preferences')
        );

        // standard module settings
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER'),
            '3' => $gL10n->get('ORG_ONLY_FOR_MODULE_ADMINISTRATOR')
        );
        $formInventory->addSelectBox(
            'inventory_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['inventory_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_INVENTORY_ACCESS_TO_MODULE_DESC')
        );

        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100', '-1' => $gL10n->get('SYS_ALL'));
        $formInventory->addSelectBox(
            'inventory_items_per_page',
            $gL10n->get('SYS_INVENTORY_ITEMS_PER_PAGE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['inventory_items_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_SELECT_DESC', array(25)))
        );

        $formInventory->addInput(
            'inventory_field_history_days',
            $gL10n->get('SYS_DAYS_FIELD_HISTORY'),
            $formValues['inventory_field_history_days'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999999999, 'step' => 1, 'helpTextId' => 'SYS_DAYS_FIELD_HISTORY_DESC')
        );

        // general settings
        $formInventory->addSeperator(
            'inventory_seperator_general_settings',
            $gL10n->get('SYS_COMMON')
        );

        $formInventory->addCheckbox(
            'inventory_show_obsolete_select_field_options',
            $gL10n->get('SYS_SHOW_OBSOLETE_SELECT_FIELD_OPTIONS'),
            (bool) $formValues['inventory_show_obsolete_select_field_options'],
            array('helpTextId' => 'SYS_SHOW_OBSOLETE_SELECT_FIELD_OPTIONS_DESC')
        );

        $formInventory->addCheckbox(
            'inventory_items_disable_borrowing',
            $gL10n->get('SYS_INVENTORY_ITEMS_DISABLE_BORROWING'),
            (bool) $formValues['inventory_items_disable_borrowing'],
            array('helpTextId' => 'SYS_INVENTORY_ITEMS_DISABLE_BORROWING_DESC')
        );

        $formInventory->addCheckbox(
            'inventory_system_field_names_editable',
            $gL10n->get('SYS_INVENTORY_SYSTEM_FIELDNAME_EDIT'),
            $formValues['inventory_system_field_names_editable'],
            array('helpTextId' => 'SYS_INVENTORY_SYSTEM_FIELDNAME_EDIT_DESC')
        );

        if ($formValues['inventory_module_enabled'] !== 3  || ($formValues['inventory_module_enabled'] === 3 && $gCurrentUser->isAdministratorInventory())) {
            $formInventory->addCheckbox(
                'inventory_allow_keeper_edit',
                $gL10n->get('SYS_INVENTORY_ACCESS_EDIT'),
                $formValues['inventory_allow_keeper_edit'],
                array('helpTextId' => 'SYS_INVENTORY_ACCESS_EDIT_DESC')
            );

            // create array of possible fields for keeper edit
            $items = new ItemsData($gDb, $gCurrentOrgId);
            $selectBoxEntries = array();
            foreach ($items->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');
                if($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($infNameIntern, $borrowingFieldNames)) {
                    continue; // skip borrowing fields if borrowing is disabled
                }
                $selectBoxEntries[$infNameIntern] = $itemField->getValue('inf_name');
            }

            $formInventory->addSelectBox(
                'inventory_allowed_keeper_edit_fields',
                $gL10n->get('SYS_INVENTORY_ACCESS_EDIT_FIELDS'),
                $selectBoxEntries,
                array('defaultValue' => explode(',', $formValues['inventory_allowed_keeper_edit_fields']), 'helpTextId' => 'SYS_INVENTORY_ACCESS_EDIT_FIELDS_DESC', 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
            );
        }

        $formInventory->addCheckbox(
            'inventory_current_user_default_keeper',
            $gL10n->get('SYS_INVENTORY_USE_CURRENT_USER'),
            (bool)$formValues['inventory_current_user_default_keeper'],
            array('helpTextId' => 'SYS_INVENTORY_USE_CURRENT_USER_DESC')
        );

        $formInventory->addCheckbox(
            'inventory_allow_negative_numbers',
            $gL10n->get('SYS_INVENTORY_ALLOW_NEGATIVE_NUMBERS'),
            (bool)$formValues['inventory_allow_negative_numbers'],
            array('helpTextId' => 'SYS_INVENTORY_ALLOW_NEGATIVE_NUMBERS_DESC')
        );

        $formInventory->addInput(
            'inventory_decimal_places',
            $gL10n->get('SYS_INVENTORY_DECIMAL_PLACES'),
            $formValues['inventory_decimal_places'],
            array('type' => 'number','minNumber' => 0, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'SYS_INVENTORY_DECIMAL_PLACES_DESC')
        );

        $selectBoxEntries = array('date' => $gL10n->get('SYS_DATE'), 'datetime' => $gL10n->get('SYS_DATE') .' & ' .$gL10n->get('SYS_TIME'));
        $formInventory->addSelectBox(
            'inventory_field_date_time_format',
            $gL10n->get('SYS_INVENTORY_DATETIME_FORMAT'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['inventory_field_date_time_format'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_INVENTORY_DATETIME_FORMAT_DESC')
        );

        // profile view settings
        $formInventory->addSeperator(
            'inventory_seperator_profile_view_settings',
            $gL10n->get('SYS_INVENTORY_PROFILE_VIEW')
        );

        $formInventory->addCheckbox(
            'inventory_profile_view_enabled',
            $gL10n->get('SYS_INVENTORY_PROFILE_VIEW_ENABLED'),
            (bool)$formValues['inventory_profile_view_enabled'],
            array('helpTextId' => 'SYS_INVENTORY_PROFILE_VIEW_ENABLED_DESC')
        );
        // create array of possible fields for profile view
        $selectBoxEntries = array();
        foreach ($items->getItemFields() as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');
            if ($itemField->getValue('inf_name_intern') == 'ITEMNAME' || ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($infNameIntern, $borrowingFieldNames))) {
                continue;
            }
            $selectBoxEntries[$infNameIntern] = $itemField->getValue('inf_name');
        }

        $formInventory->addSelectBox(
            'inventory_profile_view',
            $gL10n->get('SYS_INVENTORY_PROFILE_VIEW_FIELDS'),
            $selectBoxEntries,
            array('defaultValue' => explode(',', $formValues['inventory_profile_view']), 'helpTextId' => 'SYS_INVENTORY_PROFILE_VIEW_DESC', 'multiselect' => true, 'maximumSelectionNumber' => count($selectBoxEntries))
        );

        // export settings
        $formInventory->addSeperator(
            'inventory_seperator_export_settings',
            $gL10n->get('SYS_INVENTORY_EXPORT')
        );

        $formInventory->addInput(
            'inventory_export_filename',
            $gL10n->get('SYS_INVENTORY_FILENAME'),
            $formValues['inventory_export_filename'],
            array('maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => 'SYS_INVENTORY_FILENAME_DESC')
        );

        $formInventory->addCheckbox(
            'inventory_add_date',
            $gL10n->get('SYS_INVENTORY_ADD_DATE'),
            (bool)$formValues['inventory_add_date'],
            array('helpTextId' => 'SYS_INVENTORY_ADD_DATE_DESC')
        );

        $formInventory->addSubmitButton(
            'adm_button_save_inventory',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formInventory->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formInventory);
        return $smarty->fetch('preferences/preferences.inventory.tpl');
    }

    /**
     * Generates the HTML of the form from the email dispatch preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the email dispatch preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createEmailDispatchForm(): string
    {
        global $gL10n, $gCurrentOrganization, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formEmailDispatch = new FormPresenter(
            'adm_preferences_form_email_dispatch',
            'preferences/preferences.email-dispatch.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'email_dispatch')),
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
            array('type' => 'email', 'maxLength' => 50, 'helpTextId' => array('SYS_SENDER_EMAIL_ADDRESS_DESC', array(DOMAIN)))
        );
        $formEmailDispatch->addInput(
            'mail_sendmail_name',
            $gL10n->get('SYS_SENDER_NAME'),
            $formValues['mail_sendmail_name'],
            array('maxLength' => 50, 'helpTextId' => 'SYS_SENDER_NAME_DESC')
        );

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
            (bool) $formValues['mail_into_to'],
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
            (bool) $formValues['mail_smtp_auth'],
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
            array('type' => 'password', 'maxLength' => 100, 'helpTextId' => 'SYS_SMTP_PASSWORD_DESC')
        );
        $html = '<a class="btn btn-secondary" id="send_test_mail" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'test_email')) . '">
            <i class="bi bi-envelope-fill"></i>' . $gL10n->get('SYS_SEND_TEST_MAIL') . '</a>';
        $formEmailDispatch->addCustomContent('send_test_email', $gL10n->get('SYS_TEST_MAIL'), $html, array('helpTextId' => $gL10n->get('SYS_TEST_MAIL_DESC', array($gL10n->get('SYS_EMAIL_FUNCTION_TEST', array($gCurrentOrganization->getValue('org_longname')))))));
        $formEmailDispatch->addSubmitButton(
            'adm_button_save_email_dispatch',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formEmailDispatch->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formEmailDispatch);
        return $smarty->fetch('preferences/preferences.email-dispatch.tpl');
    }

    /**
     * Generates the HTML of the form from the events preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the events preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createEventsForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formEvents = new FormPresenter(
            'adm_preferences_form_events',
            'preferences/preferences.events.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'events')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formEvents->addSelectBox(
            'events_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['events_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        if ($gSettingsManager->getBool('events_rooms_enabled')) {
            $selectBoxEntries = array(
                'detail' => $gL10n->get('SYS_DETAILED'),
                'compact' => $gL10n->get('SYS_COMPACT'),
                'room' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_ROOM'),
                'participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'),
                'description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION')
            );
        } else {
            $selectBoxEntries = array(
                'detail' => $gL10n->get('SYS_DETAILED'),
                'compact' => $gL10n->get('SYS_COMPACT'),
                'participants' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_PARTICIPANTS'),
                'description' => $gL10n->get('SYS_COMPACT') . ' - ' . $gL10n->get('SYS_DESCRIPTION')
            );
        }
        $formEvents->addSelectBox(
            'events_view',
            $gL10n->get('SYS_DEFAULT_VIEW'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['events_view'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_DEFAULT_VIEW_DESC', array('SYS_DETAILED', 'SYS_COMPACT')))
        );
        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100', '-1' => $gL10n->get('SYS_ALL'));
        $formEvents->addSelectBox(
            'events_per_page',
            $gL10n->get('SYS_NUMBER_OF_ENTRIES_PER_PAGE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['events_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_SELECT_DESC', array(10)))
        );
         $formEvents->addInput(
            'events_clamp_text_lines',
            $gL10n->get('SYS_CLAMP_TEXT_LINES', array($gL10n->get('SYS_DESCRIPTION'))),
            $formValues['events_clamp_text_lines'],
            array('type' => 'number', 'minNumber' => 0, 'step' => 1, 'helpTextId' => array('SYS_CLAMP_TEXT_LINES_DESC', array('SYS_DESCRIPTION')))
        );
        $formEvents->addCheckbox(
            'events_ical_export_enabled',
            $gL10n->get('SYS_ENABLE_ICAL_EXPORT'),
            (bool) $formValues['events_ical_export_enabled'],
            array('helpTextId' => 'SYS_ENABLE_ICAL_EXPORT_DESC')
        );
        $formEvents->addCheckbox(
            'events_show_map_link',
            $gL10n->get('SYS_SHOW_MAP_LINK'),
            (bool) $formValues['events_show_map_link'],
            array('helpTextId' => 'SYS_SHOW_MAP_LINK_DESC')
        );
        $sqlData = array();
        $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name, lst_timestamp DESC';
        $sqlData['params'] = array($gCurrentOrgId);
        $formEvents->addSelectBoxFromSql(
            'events_list_configuration',
            $gL10n->get('SYS_DEFAULT_LIST_CONFIGURATION_PARTICIPATION'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['events_list_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_LIST_CONFIGURATION_PARTICIPATION_DESC')
        );
        $formEvents->addCheckbox(
            'events_save_cancellations',
            $gL10n->get('SYS_SAVE_ALL_CANCELLATIONS'),
            (bool) $formValues['events_save_cancellations'],
            array('helpTextId' => 'SYS_SAVE_ALL_CANCELLATIONS_DESC')
        );
        $formEvents->addCheckbox(
            'events_may_take_part',
            $gL10n->get('SYS_MAYBE_PARTICIPATE'),
            (bool) $formValues['events_may_take_part'],
            array('helpTextId' => array('SYS_MAYBE_PARTICIPATE_DESC', array('SYS_PARTICIPATE', 'SYS_CANCEL', 'SYS_EVENT_PARTICIPATION_TENTATIVE')))
        );
        $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'EVT')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CALENDAR_MANAGEMENT') . '</a>';
        $formEvents->addCustomContent(
            'editCalendars',
            $gL10n->get('SYS_EDIT_CALENDARS'),
            $html,
            array('helpTextId' => 'SYS_EDIT_CALENDAR_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formEvents->addCheckbox(
            'events_rooms_enabled',
            $gL10n->get('SYS_ROOM_SELECTABLE'),
            (bool) $formValues['events_rooms_enabled'],
            array('helpTextId' => 'SYS_ROOM_SELECTABLE_DESC')
        );
        $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/rooms/rooms.php">
            <i class="bi bi-house-door-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_ROOM_MANAGEMENT') . '</a>';
        $formEvents->addCustomContent(
            'editRooms',
            $gL10n->get('SYS_EDIT_ROOMS'),
            $html,
            array('helpTextId' => 'SYS_EDIT_ROOMS_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formEvents->addSubmitButton(
            'adm_button_save_events',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formEvents->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formEvents);
        return $smarty->fetch('preferences/preferences.events.tpl');
    }

    /**
     * Generates the HTML of the form from the group and roles preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the group and roles preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createGroupsRolesForm(): string
    {
        global $gL10n, $gSettingsManager, $gDb, $gCurrentOrgId, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formGroupsRoles = new FormPresenter(
            'adm_preferences_form_groups_roles',
            'preferences/preferences.groups-roles.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'groups_roles')),
            null,
            array('class' => 'form-preferences')
        );
        $formGroupsRoles->addCheckbox(
            'groups_roles_module_enabled',
            $gL10n->get('SYS_ENABLE_GROUPS_ROLES'),
            (bool) $formValues['groups_roles_module_enabled'],
            array('helpTextId' => 'SYS_ENABLE_GROUPS_ROLES_DESC')
        );
        $selectBoxEntries = array('10' => '10', '25' => '25', '50' => '50', '100' => '100', '-1' => $gL10n->get('SYS_ALL'));
        $formGroupsRoles->addSelectBox(
            'groups_roles_members_per_page',
            $gL10n->get('SYS_MEMBERS_PER_PAGE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['groups_roles_members_per_page'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_MEMBERS_PER_PAGE_DESC')
        );
        // read all global lists
        $sqlData = array();
        $sqlData['query'] = 'SELECT lst_id, lst_name
                       FROM ' . TBL_LISTS . '
                      WHERE lst_org_id = ? -- $gCurrentOrgId
                        AND lst_global = true
                   ORDER BY lst_name, lst_timestamp DESC';
        $sqlData['params'] = array($gCurrentOrgId);
        $formGroupsRoles->addSelectBoxFromSql(
            'groups_roles_default_configuration',
            $gL10n->get('SYS_DEFAULT_CONFIGURATION'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['groups_roles_default_configuration'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DEFAULT_CONFIGURATION_LISTS_DESC')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_ADMINISTRATORS'),
            '1' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_ASSIGN_ROLES')))),
            '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))
        );
        $formGroupsRoles->addSelectBox(
            'groups_roles_show_former_members',
            $gL10n->get('SYS_SHOW_FORMER_MEMBERS'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['groups_roles_show_former_members'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_SHOW_FORMER_MEMBERS_DESC', array($gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))))
        );
        $selectBoxEntriesExport = array(
            '0' => $gL10n->get('SYS_NOBODY'),
            '1' => $gL10n->get('SYS_ALL'),
            '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER'))))
        );
        $formGroupsRoles->addSelectBox(
            'groups_roles_export',
            $gL10n->get('SYS_EXPORT_LISTS'),
            $selectBoxEntriesExport,
            array('defaultValue' => $formValues['groups_roles_export'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_EXPORT_LISTS_DESC')
        );
        $selectBoxEntriesEditLists = array(
            '1' => $gL10n->get('SYS_ALL'),
            '2' => preg_replace('/<\/?strong>/', '"', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_RIGHT', array($gL10n->get('SYS_RIGHT_EDIT_USER')))),
            '3' => $gL10n->get('SYS_ADMINISTRATORS')
        );
        $formGroupsRoles->addSelectBox(
            'groups_roles_edit_lists',
            $gL10n->get('SYS_CONFIGURE_LISTS'),
            $selectBoxEntriesEditLists,
            array('defaultValue' => $formValues['groups_roles_edit_lists'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_CONFIGURE_LISTS_DESC')
        );
        $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'ROL')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
        $formGroupsRoles->addCustomContent(
            'editCategories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            $html,
            array('helpTextId' => 'SYS_MAINTAIN_CATEGORIES_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formGroupsRoles->addSubmitButton(
            'adm_button_save_lists',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formGroupsRoles->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formGroupsRoles);
        return $smarty->fetch('preferences/preferences.groups-roles.tpl');
    }

    /**
     * Generates the HTML of the form from the forum preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the forum preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createForumForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formForum = new FormPresenter(
            'adm_preferences_form_forum',
            'preferences/preferences.forum.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'forum')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formForum->addSelectBox(
            'forum_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['forum_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $selectBoxEntries = array(
            'cards' => $gL10n->get('SYS_DETAILED'),
            'list' => $gL10n->get('SYS_LIST')
        );
        $formForum->addSelectBox(
            'forum_view',
            $gL10n->get('SYS_DEFAULT_VIEW'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['forum_view'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_DEFAULT_VIEW_FORUM_DESC', array('SYS_DETAILED', 'SYS_LIST')))
        );
        $formForum->addInput(
            'forum_topics_per_page',
            $gL10n->get('SYS_NUMBER_OF_TOPICS_PER_PAGE'),
            $formValues['forum_topics_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(10)))
        );
        $formForum->addInput(
            'forum_posts_per_page',
            $gL10n->get('SYS_NUMBER_OF_POSTS_PER_PAGE'),
            $formValues['forum_posts_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(15)))
        );
        $formForum->addSubmitButton(
            'adm_button_save_forum',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formForum->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formForum);
        return $smarty->fetch('preferences/preferences.forum.tpl');
    }

    /**
     * Generates the HTML of the form from the link preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the link preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createLinksForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formWeblinks = new FormPresenter(
            'adm_preferences_form_links',
            'preferences/preferences.links.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'links')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formWeblinks->addSelectBox(
            'weblinks_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['weblinks_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $formWeblinks->addInput(
            'weblinks_per_page',
            $gL10n->get('SYS_NUMBER_OF_ENTRIES_PER_PAGE'),
            $formValues['weblinks_per_page'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(0)))
        );
        $selectBoxEntries = array('_self' => $gL10n->get('SYS_SAME_WINDOW'), '_blank' => $gL10n->get('SYS_NEW_WINDOW'));
        $formWeblinks->addSelectBox(
            'weblinks_target',
            $gL10n->get('SYS_LINK_TARGET'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['weblinks_target'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_LINK_TARGET_DESC')
        );
        $formWeblinks->addInput(
            'weblinks_redirect_seconds',
            $gL10n->get('SYS_DISPLAY_REDIRECT'),
            $formValues['weblinks_redirect_seconds'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_DISPLAY_REDIRECT_DESC')
        );
        $html = '<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'LNK')) . '">
            <i class="bi bi-hdd-stack-fill"></i>' . $gL10n->get('SYS_SWITCH_TO_CATEGORIES_ADMINISTRATION') . '</a>';
        $formWeblinks->addCustomContent(
            'editCategories',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            $html,
            array('helpTextId' => $gL10n->get('SYS_MAINTAIN_CATEGORIES_DESC'), 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formWeblinks->addSubmitButton(
            'adm_button_save_links',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formWeblinks->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formWeblinks);
        return $smarty->fetch('preferences/preferences.links.tpl');
    }

    /**
     * Generates the HTML of the form from the message preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the message preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createMessagesForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formMessages = new FormPresenter(
            'adm_preferences_form_messages',
            'preferences/preferences.messages.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'messages')),
            null,
            array('class' => 'form-preferences')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formMessages->addSelectBox(
            'mail_module_enabled',
            $gL10n->get('ORG_ACCESS_TO_MODULE'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_module_enabled'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'ORG_ACCESS_TO_MODULE_DESC')
        );
        $formMessages->addCheckbox(
            'pm_module_enabled',
            $gL10n->get('SYS_ENABLE_PM_MODULE'),
            (bool) $formValues['pm_module_enabled'],
            array('helpTextId' => 'SYS_ENABLE_PM_MODULE_DESC')
        );
        $formMessages->addCheckbox(
            'mail_captcha_enabled',
            $gL10n->get('ORG_ENABLE_CAPTCHA'),
            (bool) $formValues['mail_captcha_enabled'],
            array('helpTextId' => 'SYS_SHOW_CAPTCHA_DESC')
        );

        $formMessages->addSelectBox(
            'mail_template',
            $gL10n->get('SYS_EMAIL_TEMPLATE'),
            PreferencesService::getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates'),
            array(
                'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.html', '', $formValues['mail_template']))),
                'showContextDependentFirstEntry' => true,
                'arrayKeyIsNotValue' => true,
                'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
                'helpTextId' => array('SYS_EMAIL_TEMPLATE_DESC', array('adm_my_files/mail_templates', '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:e-mail-templates">', '</a>'))
            )
        );
        $formMessages->addInput(
            'mail_max_receiver',
            $gL10n->get('SYS_MAX_RECEIVER'),
            $formValues['mail_max_receiver'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_MAX_RECEIVER_DESC')
        );
        $formMessages->addCheckbox(
            'mail_send_to_all_addresses',
            $gL10n->get('SYS_SEND_EMAIL_TO_ALL_ADDRESSES'),
            (bool) $formValues['mail_send_to_all_addresses'],
            array('helpTextId' => 'SYS_SEND_EMAIL_TO_ALL_ADDRESSES_DESC')
        );
        $formMessages->addCheckbox(
            'mail_show_former',
            $gL10n->get('SYS_SEND_EMAIL_FORMER'),
            (bool) $formValues['mail_show_former'],
            array('helpTextId' => 'SYS_SEND_EMAIL_FORMER_DESC')
        );
        $formMessages->addInput(
            'max_email_attachment_size',
            $gL10n->get('SYS_ATTACHMENT_SIZE') . ' (MB)',
            $formValues['max_email_attachment_size'],
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 999999, 'step' => 1, 'helpTextId' => 'SYS_ATTACHMENT_SIZE_DESC')
        );
        $formMessages->addCheckbox(
            'mail_save_attachments',
            $gL10n->get('SYS_SAVE_ATTACHMENTS'),
            (bool) $formValues['mail_save_attachments'],
            array('helpTextId' => 'SYS_SAVE_ATTACHMENTS_DESC')
        );
        $formMessages->addCheckbox(
            'mail_html_registered_users',
            $gL10n->get('SYS_HTML_MAILS_REGISTERED_USERS'),
            (bool) $formValues['mail_html_registered_users'],
            array('helpTextId' => 'SYS_HTML_MAILS_REGISTERED_USERS_DESC')
        );
        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $formMessages->addSelectBox(
            'mail_delivery_confirmation',
            $gL10n->get('SYS_DELIVERY_CONFIRMATION'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['mail_delivery_confirmation'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_DELIVERY_CONFIRMATION_DESC')
        );
        $formMessages->addSubmitButton(
            'adm_button_save_messages',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formMessages->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formMessages);
        return $smarty->fetch('preferences/preferences.messages.tpl');
    }

    /**
     * Generates the HTML of the form from the photo preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the photo preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createPhotosForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formPhotos = new FormPresenter(
            'adm_preferences_form_photos',
            'preferences/preferences.photos.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'photos')),
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
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => array('SYS_NUMBER_OF_ENTRIES_PER_PAGE_DESC', array(24)))
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
            (bool) $formValues['photo_download_enabled'],
            array('helpTextId' => array('SYS_ENABLE_DOWNLOAD_DESC', array('SYS_KEEP_ORIGINAL')))
        );
        $formPhotos->addCheckbox(
            'photo_keep_original',
            $gL10n->get('SYS_KEEP_ORIGINAL'),
            (bool) $formValues['photo_keep_original'],
            array('helpTextId' => array('SYS_KEEP_ORIGINAL_DESC', array('SYS_ENABLE_DOWNLOAD')))
        );
        $formPhotos->addCheckbox(
            'photo_ecard_enabled',
            $gL10n->get('SYS_ENABLE_GREETING_CARDS'),
            (bool) $formValues['photo_ecard_enabled'],
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
            PreferencesService::getArrayFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates'),
            array(
                'defaultValue' => ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $formValues['photo_ecard_template']))),
                'showContextDependentFirstEntry' => false,
                'arrayKeyIsNotValue' => true,
                'firstEntry' => $gL10n->get('SYS_NO_TEMPLATE'),
                'helpTextId' => 'SYS_TEMPLATE_DESC'
            )
        );
        $formPhotos->addSubmitButton(
            'adm_button_save_photos',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formPhotos->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formPhotos);
        return $smarty->fetch('preferences/preferences.photos.tpl');
    }

    /**
     * Generates the HTML of the form from the profile preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the profile preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createProfileForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentOrganization, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formProfile = new FormPresenter(
            'adm_preferences_form_profile',
            'preferences/preferences.profile.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'profile')),
            null,
            array('class' => 'form-preferences')
        );
        $html = '<a class="btn btn-secondary" href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php">
            <i class="bi bi-ui-radios"></i>' . $gL10n->get('SYS_SWITCH_TO_PROFILE_FIELDS_CONFIGURATION') . '</a>';
        $formProfile->addCustomContent(
            'editProfileFields',
            $gL10n->get('SYS_EDIT_PROFILE_FIELDS'),
            $html,
            array('helpTextId' => 'SYS_MANAGE_PROFILE_FIELDS_DESC', 'alertWarning' => $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST'))
        );
        $formProfile->addCheckbox(
            'profile_show_obsolete_select_field_options',
            $gL10n->get('SYS_SHOW_OBSOLETE_SELECT_FIELD_OPTIONS'),
            (bool) $formValues['profile_show_obsolete_select_field_options'],
            array('helpTextId' => 'SYS_SHOW_OBSOLETE_SELECT_FIELD_OPTIONS_DESC')
        );
        $formProfile->addCheckbox(
            'profile_show_map_link',
            $gL10n->get('SYS_SHOW_MAP_LINK'),
            (bool) $formValues['profile_show_map_link'],
            array('helpTextId' => 'SYS_SHOW_MAP_LINK_PROFILE_DESC')
        );
        $formProfile->addCheckbox(
            'profile_show_empty_fields',
            $gL10n->get('SYS_SHOW_EMPTY_PROFILE_FIELDS'),
            (bool) $formValues['profile_show_empty_fields'],
            array('helpTextId' => 'SYS_SHOW_EMPTY_PROFILE_FIELDS_DESC')
        );
        $formProfile->addCheckbox(
            'profile_show_roles',
            $gL10n->get('SYS_SHOW_ROLE_MEMBERSHIP'),
            (bool) $formValues['profile_show_roles'],
            array('helpTextId' => 'SYS_SHOW_ROLE_MEMBERSHIP_DESC')
        );
        $formProfile->addCheckbox(
            'profile_show_former_roles',
            $gL10n->get('SYS_SHOW_FORMER_ROLE_MEMBERSHIP'),
            (bool) $formValues['profile_show_former_roles'],
            array('helpTextId' => 'SYS_SHOW_FORMER_ROLE_MEMBERSHIP_DESC')
        );

        if ($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization()) {
            $formProfile->addCheckbox(
                'profile_show_extern_roles',
                $gL10n->get('SYS_SHOW_ROLES_OTHER_ORGANIZATIONS'),
                (bool) $formValues['profile_show_extern_roles'],
                array('helpTextId' => 'SYS_SHOW_ROLES_OTHER_ORGANIZATIONS_DESC')
            );
        }

        $selectBoxEntries = array('0' => $gL10n->get('SYS_DATABASE'), '1' => $gL10n->get('SYS_FOLDER'));
        $formProfile->addSelectBox(
            'profile_photo_storage',
            $gL10n->get('SYS_LOCATION_PROFILE_PICTURES'),
            $selectBoxEntries,
            array('defaultValue' => $formValues['profile_photo_storage'], 'showContextDependentFirstEntry' => false, 'helpTextId' => 'SYS_LOCATION_PROFILE_PICTURES_DESC')
        );
        $formProfile->addSubmitButton(
            'adm_button_save_profile',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formProfile->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formProfile);
        return $smarty->fetch('preferences/preferences.profile.tpl');
    }

    /**
     * Generates the HTML of the form from the regional settings preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the regional settings preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createRegionalSettingsForm(): string
    {
        global $gL10n, $gSettingsManager, $gTimezone, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formRegionalSettings = new FormPresenter(
            'adm_preferences_form_regional_settings',
            'preferences/preferences.regional-settings.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'regional_settings')),
            null,
            array('class' => 'form-preferences')
        );
        $formRegionalSettings->addInput(
            'system_timezone',
            $gL10n->get('ORG_TIMEZONE'),
            $gTimezone,
            array('property' => FormPresenter::FIELD_DISABLED, 'class' => 'form-control-small', 'helpTextId' => 'ORG_TIMEZONE_DESC')
        );
        $formRegionalSettings->addSelectBox(
            'system_language',
            $gL10n->get('SYS_LANGUAGE'),
            $gL10n->getAvailableLanguages(),
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $formValues['system_language'], 'helpTextId' => array('SYS_LANGUAGE_HELP_TRANSLATION', array('<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:uebersetzen">', '</a>')))
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
            array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 20, 'helpTextId' => array('ORG_DATE_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
        );
        $formRegionalSettings->addInput(
            'system_time',
            $gL10n->get('ORG_TIME_FORMAT'),
            $formValues['system_time'],
            array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 20, 'helpTextId' => array('ORG_TIME_FORMAT_DESC', array('<a href="https://www.php.net/manual/en/function.date.php">date()</a>')), 'class' => 'form-control-small')
        );
        $formRegionalSettings->addInput(
            'system_currency',
            $gL10n->get('ORG_CURRENCY'),
            $formValues['system_currency'],
            array('maxLength' => 20, 'helpTextId' => 'ORG_CURRENCY_DESC', 'class' => 'form-control-small')
        );
        $formRegionalSettings->addSubmitButton(
            'adm_button_save_regional_settings',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formRegionalSettings->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formRegionalSettings);
        return $smarty->fetch('preferences/preferences.regional-settings.tpl');
    }

    /**
     * Generates the HTML of the form from the registration preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the registration preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createRegistrationForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formRegistration = new FormPresenter(
            'adm_preferences_form_registration',
            'preferences/preferences.registration.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'registration')),
            null,
            array('class' => 'form-preferences')
        );
        $formRegistration->addCheckbox(
            'registration_module_enabled',
            $gL10n->get('ORG_ENABLE_REGISTRATION_MODULE'),
            (bool) $formValues['registration_module_enabled'],
            array('helpTextId' => 'ORG_ENABLE_REGISTRATION_MODULE_DESC')
        );
        $formRegistration->addCheckbox(
            'registration_manual_approval',
            $gL10n->get('SYS_MANUAL_APPROVAL'),
            (bool) $formValues['registration_manual_approval'],
            array('helpTextId' => array('SYS_MANUAL_APPROVAL_DESC', array('SYS_RIGHT_APPROVE_USERS')))
        );
        $formRegistration->addCheckbox(
            'registration_enable_captcha',
            $gL10n->get('ORG_ENABLE_CAPTCHA'),
            (bool) $formValues['registration_enable_captcha'],
            array('helpTextId' => 'ORG_CAPTCHA_REGISTRATION')
        );
        $formRegistration->addCheckbox(
            'registration_adopt_all_data',
            $gL10n->get('SYS_REGISTRATION_ADOPT_ALL_DATA'),
            (bool) $formValues['registration_adopt_all_data'],
            array('helpTextId' => 'SYS_REGISTRATION_ADOPT_ALL_DATA_DESC')
        );
        $formRegistration->addCheckbox(
            'registration_send_notification_email',
            $gL10n->get('ORG_EMAIL_ALERTS'),
            (bool) $formValues['registration_send_notification_email'],
            array('helpTextId' => array('ORG_EMAIL_ALERTS_DESC', array('SYS_RIGHT_APPROVE_USERS')))
        );
        $formRegistration->addSubmitButton(
            'adm_button_save_registration',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formRegistration->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formRegistration);
        return $smarty->fetch('preferences/preferences.registration.tpl');
    }

    /**
     * Generates the HTML of the form from the security preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the security preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createSecurityForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formSecurity = new FormPresenter(
            'adm_preferences_form_security',
            'preferences/preferences.security.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'security')),
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
            (bool) $formValues['enable_auto_login'],
            array('helpTextId' => 'ORG_LOGIN_AUTOMATICALLY_DESC')
        );
        $formSecurity->addCheckbox(
            'enable_password_recovery',
            $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
            (bool) $formValues['enable_password_recovery'],
            array('helpTextId' => array('SYS_PASSWORD_FORGOTTEN_PREF_DESC', array('SYS_ENABLE_NOTIFICATIONS')))
        );
        $formSecurity->addCheckbox(
            'security_login_email_address_enabled',
            $gL10n->get('SYS_LOGIN_WITH_EMAIL'),
            (bool) $formValues['security_login_email_address_enabled'],
            array('helpTextId' => 'SYS_LOGIN_WITH_EMAIL_DESC')
        );
        $formSecurity->addCheckbox(
            'two_factor_authentication_enabled',
            $gL10n->get('SYS_TFA_ENABLE'),
            (bool) $formValues['two_factor_authentication_enabled'],
            array('helpTextId' => 'SYS_TFA_ENABLE_DESC')
        );
        $formSecurity->addSubmitButton(
            'adm_button_save_security',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formSecurity->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formSecurity);
        return $smarty->fetch('preferences/preferences.security.tpl');
    }

    /**
     * Generates the HTML of the form from the sso preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the sso preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createSsoForm(): string
    {
        global $gL10n, $gSettingsManager, $gCurrentSession, $gDb, $gCurrentUser;

        $formValues = $gSettingsManager->getAll();

        $formSSO = new FormPresenter(
            'adm_preferences_form_sso',
            'preferences/preferences.sso.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'sso')),
            null,
            array('class' => 'form-preferences')
        );


        // Link to Key administration
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php');
        $html = '<a class="btn btn-secondary admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
            data-message="' . $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST') . '</br>' .
            $gL10n->get('ORG_NOT_SAVED_SETTINGS_CONTINUE') . '"
            data-href="window.location.href=\'' . $url . '\'">
            <i class="bi bi-key"></i>' . $gL10n->get('SYS_SSO_KEY_ADMIN') . '</a>';
        $formSSO->addCustomContent(
            'sso_keys',
            $gL10n->get('SYS_SSO_KEYS'),
            $html,
            array()
        );

        $samlService = new \Admidio\SSO\Service\SAMLService($gDb, $gCurrentUser);

        $formSSO->addCustomContent(
            'sso_saml_settings',
            '',
            '<h5>' . $gL10n->get('SYS_SSO_SAML') . '</h5>',
            array()
        );
        $formSSO->addCheckbox(
            'sso_saml_enabled',
            $gL10n->get('SYS_SSO_SAML_ENABLED'),
            (bool)$formValues['sso_saml_enabled'],
            array('helpTextId' => 'SYS_SSO_SAML_ENABLED_DESC')
        );
        $formSSO->addInput(
            'sso_saml_entity_id',
            $gL10n->get('SYS_SSO_SAML_ENTITY_ID'),
            $formValues['sso_saml_entity_id'],
            array('class' => 'copy-container if-saml-enabled', 'helpTextId' => 'SYS_SSO_SAML_ENTITY_ID_DESC')
        );

        $keyService = new KeyService($gDb);
        $keyArray = $keyService->getKeysData(true);
        // $keys = array('0' => $gL10n->get('SYS_NONE'));
        $keys = array();
        $valueAttributes = array();
        foreach ($keyArray as $key) {
            $keys[$key['key_id']] = $key['key_name'] . ' (' . $key['key_algorithm'] . ', ' . $key['key_expires_at'] . ')';
            // We can add the certificates as additional value attributes to the select entries
            $valueAttributes[$key['key_id']] = ['data-global' => $key['key_certificate']];
        }

        $formSSO->addSelectBox(
            'sso_saml_signing_key',
            $gL10n->get('SYS_SSO_SIGNING_KEY'),
            $keys,
            array('defaultValue' => $formValues['sso_saml_signing_key'], 'firstEntry' => $gL10n->get('SYS_NONE'),
                'valueAttributes' => $valueAttributes, 'class' => 'if-saml-enabled')
        );
        $formSSO->addSelectBox(
            'sso_saml_encryption_key',
            $gL10n->get('SYS_SSO_ENCRYPTION_KEY'),
            $keys,
            array('defaultValue' => $formValues['sso_saml_encryption_key'], 'firstEntry' => $gL10n->get('SYS_NONE'),
                'valueAttributes' => $valueAttributes, 'class' => 'if-saml-enabled')
        );

        $formSSO->addCheckbox(
            'sso_saml_want_requests_signed',
            $gL10n->get('SYS_SSO_SAML_WANT_REQUESTS_SIGNED'),
            (bool)$formValues['sso_saml_want_requests_signed'],
            array('class' => 'if-saml-enabled')
        );

        $formSSO->addCustomContent(
            'sso_saml_sso_staticsettings',
            $gL10n->get('SYS_SSO_STATIC_SETTINGS'),
            '',
            array('data' => $samlService->getStaticSettings())
        );

        // Link to SAML Client administration
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array());
        $html = '<a class="btn btn-secondary admidio-messagebox if-saml-enabled" href="javascript:void(0);" data-buttons="yes-no"
            data-message="' . $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST') . '</br>' .
            $gL10n->get('ORG_NOT_SAVED_SETTINGS_CONTINUE') . '"
            data-href="window.location.href=\'' . $url . '\'">
            <i class="bi bi-key"></i>' . $gL10n->get('SYS_SSO_CLIENT_ADMIN') . '</a>';
        $formSSO->addCustomContent(
            'sso_saml_clients',
            $gL10n->get('SYS_SSO_CLIENTS_SAML'),
            $html,
            array()
        );




        /* *******************************************************************************
         * OIDC Settings
         */
        $oidcService = new \Admidio\SSO\Service\OIDCService($gDb, $gCurrentUser);

        $formSSO->addCustomContent(
            'sso_oidc_settings',
            '',
            '<h5>' . $gL10n->get('SYS_SSO_OIDC') . '</h5>',
            array()
        );
        $formSSO->addCheckbox(
            'sso_oidc_enabled',
            $gL10n->get('SYS_SSO_OIDC_ENABLED'),
            (bool)$formValues['sso_oidc_enabled'],
            array('helpTextId' => 'SYS_SSO_OIDC_ENABLED_DESC')
        );

        if (empty($formValues['sso_oidc_issuer_url'])) {
            $formValues['sso_oidc_issuer_url'] = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/oidc';
        }
        if (str_ends_with($formValues['sso_oidc_issuer_url'], '/')) {
            $formValues['sso_oidc_issuer_url'] = substr($formValues['sso_oidc_issuer_url'], 0, -1);
        }
        $formSSO->addInput(
            'sso_oidc_issuer_url',
            $gL10n->get('SYS_SSO_OIDC_ISSUER_URL'),
            (string)$formValues['sso_oidc_issuer_url'],
            array('class' => 'copy-container if-oidc-enabled', 'helpTextId' => 'SYS_SSO_OIDC_ISSUER_URL_DESC')
        );

        $keyService = new KeyService($gDb);
        $keyArray = $keyService->getKeysData(true);
        // $keys = array('0' => $gL10n->get('SYS_NONE'));
        $keys = array();
        $valueAttributes = array();
        foreach ($keyArray as $key) {
            // OIDC supports only RSA keys!
            if (str_starts_with($key['key_algorithm'], 'RSA')) {
                $keys[$key['key_id']] = $key['key_name'] . ' (' . $key['key_algorithm'] . ', ' . $key['key_expires_at'] . ')';
                // We can add the certificates as additional value attributes to the select entries
                $valueAttributes[$key['key_id']] = ['data-global' => $key['key_certificate']];
            }
        }

        $formSSO->addSelectBox(
            'sso_oidc_signing_key',
            $gL10n->get('SYS_SSO_SIGNING_KEY'),
            $keys,
            array('defaultValue' => $formValues['sso_oidc_signing_key'], 'firstEntry' => $gL10n->get('SYS_NONE'),
                'valueAttributes' => $valueAttributes, 'class' => 'if-oidc-enabled')
        );

        $formSSO->addCustomContent(
            'sso_oidc_sso_staticsettings',
            $gL10n->get('SYS_SSO_STATIC_SETTINGS'),
            '',
            array('data' => $oidcService->getStaticSettings())
        );

        // Link to OIDC Client administration
        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array());
        $html = '<a class="btn btn-secondary admidio-messagebox if-oidc-enabled" href="javascript:void(0);" data-buttons="yes-no"
            data-message="' . $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST') . '</br>' .
            $gL10n->get('ORG_NOT_SAVED_SETTINGS_CONTINUE') . '"
            data-href="window.location.href=\'' . $url . '\'">
            <i class="bi bi-key"></i>' . $gL10n->get('SYS_SSO_CLIENT_ADMIN') . '</a>';
        $formSSO->addCustomContent(
            'sso_oidc_clients',
            $gL10n->get('SYS_SSO_CLIENTS_OIDC'),
            $html,
            array()
        );




        $formSSO->addSubmitButton(
            'adm_button_save_sso',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formSSO->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formSSO);
        return $smarty->fetch('preferences/preferences.sso.tpl');
    }

    /**
     * Generates the HTML of the form from the Admidio update preferences, system information preferences and PHP preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the  Admidio update preferences, system information preferences and PHP preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createSystemInformationForm(): string
    {
        global $gL10n, $gDb, $gLogger, $gDebug, $gImportDemoData, $gSystemComponent;

        // Admidio Version and Update
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
        $this->assignSmartyVariable('backupUrl', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'backup')));
        $this->assignSmartyVariable('admidioHomepage', ADMIDIO_HOMEPAGE);

        // Admidio System Information
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

        // Admidio PHP Information
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
        } catch (Exception $e) {
            $prnGeneratorColorClass = 'text-danger';
            $prnGeneratorText = $gL10n->get('SYS_PRNG_INSECURE');
            $prnGeneratorInfo = '<br />' . $e->getMessage();
        }
        $this->assignSmartyVariable('prnGeneratorColorClass', $prnGeneratorColorClass);
        $this->assignSmartyVariable('prnGeneratorText', $prnGeneratorText);
        $this->assignSmartyVariable('prnGeneratorInfo', $prnGeneratorInfo);
        $this->assignSmartyVariable('admidioUrl', ADMIDIO_URL);

        //assign card titles and corresponding template files
        $cards = array(
            array('title'=>$gL10n->get('SYS_ADMIDIO_VERSION_BACKUP'), 'icon'=>'bi-cloud-arrow-down-fill', 'templateFile'=>'preferences/preferences.admidio-update.tpl'),
            array('title'=>$gL10n->get('SYS_SYSTEM_INFORMATION'),        'icon'=>'bi-info-circle-fill', 'templateFile'=>'preferences/preferences.system-information.tpl'),
            array('title'=>$gL10n->get('SYS_PHP'),                 'icon'=>'bi-filetype-php', 'templateFile'=>'preferences/preferences.php.tpl'),
        );

        $this->assignSmartyVariable('cards', $cards);
        $smarty = $this->getSmartyTemplate();
        return $smarty->fetch('preferences/preferences.system-informations.tpl');
    }

    /**
     * Generates the HTML of the form from the system notifications preferences and will return the complete HTML.
     * @return string Returns the complete HTML of the form from the system notifications preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createSystemNotificationsForm(): string
    {
        global $gL10n, $gDb, $gSettingsManager, $gCurrentOrgId, $gCurrentSession;

        $formValues = $gSettingsManager->getAll();

        $formSystemNotifications = new FormPresenter(
            'adm_preferences_form_system_notifications',
            'preferences/preferences.system-notifications.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'system_notifications')),
            null,
            array('class' => 'form-preferences')
        );
        $formSystemNotifications->addCheckbox(
            'system_notifications_enabled',
            $gL10n->get('SYS_ENABLE_NOTIFICATIONS'),
            (bool) $formValues['system_notifications_enabled'],
            array('helpTextId' => 'SYS_ENABLE_NOTIFICATIONS_DESC')
        );
        $formSystemNotifications->addCheckbox(
            'system_notifications_new_entries',
            $gL10n->get('SYS_NOTIFICATION_NEW_ENTRIES'),
            (bool) $formValues['system_notifications_new_entries'],
            array('helpTextId' => 'SYS_NOTIFICATION_NEW_ENTRIES_DESC')
        );
        $formSystemNotifications->addCheckbox(
            'system_notifications_profile_changes',
            $gL10n->get('SYS_NOTIFICATION_PROFILE_CHANGES'),
            (bool) $formValues['system_notifications_profile_changes'],
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
        $formSystemNotifications->addSelectBoxFromSql(
            'system_notifications_role',
            $gL10n->get('SYS_NOTIFICATION_ROLE'),
            $gDb,
            $sqlData,
            array('defaultValue' => $formValues['system_notifications_role'], 'showContextDependentFirstEntry' => false, 'helpTextId' => array('SYS_NOTIFICATION_ROLE_DESC', array('SYS_RIGHT_ALL_LISTS_VIEW')))
        );

        $text = new Text($gDb);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_CONFIRMATION', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotifications->addMultilineTextInput('SYSMAIL_REGISTRATION_CONFIRMATION', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_CONFIRMATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_NEW', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotifications->addMultilineTextInput('SYSMAIL_REGISTRATION_NEW', $gL10n->get('SYS_NOTIFICATION_NEW_REGISTRATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_APPROVED', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotifications->addMultilineTextInput('SYSMAIL_REGISTRATION_APPROVED', $gL10n->get('SYS_NOTIFICATION_REGISTRATION_APPROVAL'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_REGISTRATION_REFUSED', 'txt_org_id' => $gCurrentOrgId));
        $formSystemNotifications->addMultilineTextInput('SYSMAIL_REGISTRATION_REFUSED', $gL10n->get('ORG_REFUSE_REGISTRATION'), $text->getValue('txt_text'), 7);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_NEW_PASSWORD', 'txt_org_id' => $gCurrentOrgId));
        $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_NEW_PASSWORD');
        $formSystemNotifications->addMultilineTextInput(
            'SYSMAIL_NEW_PASSWORD',
            $gL10n->get('ORG_SEND_NEW_PASSWORD'),
            $text->getValue('txt_text'),
            7,
            array('helpTextId' => $htmlDesc)
        );
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_PASSWORD_RESET', 'txt_org_id' => $gCurrentOrgId));
        $htmlDesc = $gL10n->get('ORG_ADDITIONAL_VARIABLES') . ':<br /><strong>#variable1#</strong> - ' . $gL10n->get('ORG_VARIABLE_ACTIVATION_LINK');
        $formSystemNotifications->addMultilineTextInput(
            'SYSMAIL_PASSWORD_RESET',
            $gL10n->get('SYS_PASSWORD_FORGOTTEN'),
            $text->getValue('txt_text'),
            7,
            array('helpTextId' => $htmlDesc)
        );
        $formSystemNotifications->addSubmitButton(
            'adm_button_save_system_notification',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $smarty = $this->getSmartyTemplate();
        $formSystemNotifications->addToSmarty($smarty);
        $gCurrentSession->addFormObject($formSystemNotifications);
        return $smarty->fetch('preferences/preferences.system-notifications.tpl');
    }

    /**
     * Set a panel name that should be opened at a page load.
     * @param string $panelName Name of the panel that should be opened at a page load.
     * @return void
     */
    public function setPanelToShow(string $panelName): void
    {
        $this->preferencesPanelToShow = $panelName;
    }

    /**
     * Read all available registrations from the database and create the HTML content of this
     * page with the Smarty template engine and write the HTML output to the internal
     * parameter **$pageContent**. If no registration is found, then show a message to the user.
     */
    public function show(): void
    {
        global $gL10n;

        if ($this->preferencesPanelToShow !== '') {
            // open the selected panel
            if ($this->preferencesPanelToShow !== '') {
                $this->addJavascript('
                    // --- Reset Tab active states for large screens
                    $("#adm_preferences_tabs .nav-link").removeClass("active");
                    $("#adm_preferences_tab_content .tab-pane").removeClass("active show");

                    // --- Reset Accordion active states for small screens
                    $("#adm_preferences_accordion [aria-expanded=\'true\']").attr("aria-expanded", "false");
                    $("#adm_preferences_accordion .accordion-button").addClass("collapsed");
                    $("#adm_preferences_accordion .accordion-item").removeClass("show");
                    $("#adm_preferences_accordion .accordion-collapse").removeClass("show");

                    // --- Activate the selected Tab and its content
                    $("#adm_tab_' . $this->preferencesPanelToShow . '").addClass("active");
                    $("#adm_tab_' . $this->preferencesPanelToShow . '_content").addClass("active show");

                    // --- For Mobile Accordion: open the desired accordion panel
                    $("#collapse_' . $this->preferencesPanelToShow . '").addClass("show");

                    // --- Desktop vs. Mobile via jQuery visibility
                    if ($(".d-none.d-md-block").is(":visible")) {
                        // Desktop mode
                        $("#adm_preferences_tabs .nav-link[data-bs-target=\'#adm_tab_' . $this->preferencesPanelToShow . '_content\']").addClass("active");
                        $("#adm_preferences_tab_content .tab-pane#adm_tab_' . $this->preferencesPanelToShow . '_content").addClass("active show");
                    } else {
                        // Mobile mode
                        $("#collapse_' . $this->preferencesPanelToShow . '").addClass("show").attr("aria-expanded", "true");
                        $("#heading_' . $this->preferencesPanelToShow . ' .accordion-button").removeClass("collapsed").attr("aria-expanded", "true");
                        // --- Hash setzen, damit Bookmark/Scroll stimmt und zum Element scrollen
                        location.hash = "#heading_' . $this->preferencesPanelToShow . '";
                    }
                ', true);
            }
        }

        $this->addJavascript('
            // === 1) Panel laden und Events binden ===
            function loadPreferencesPanel(panelId) {
                var panelContainer = $("[data-preferences-panel=\"" + panelId + "\"]");
                if (!panelContainer.length) return;

                // Schritt 1: Spinner einfügen
                panelContainer.html("<div class=\"d-flex justify-content-center align-items-center\" style=\"height: 200px;\"><div class=\"spinner-border text-primary\" role=\"status\"><span class=\"visually-hidden\">Lade...</span></div></div>");

                $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences.php", {
                    mode: "html_form",
                    panel: panelId
                }, function(htmlContent) {
                    panelContainer.html(htmlContent);
                    initializePanelInteractions(panelId);
                }).fail(function() {
                    panelContainer.html("<div class=\"text-danger\">Fehler beim Laden</div>");
                });
            }

            // === 2) Innerhalb eines Panels die Klick-Handler anmelden ===
            function initializePanelInteractions(panelId) {
                var panelContainer = $("[data-preferences-panel=\"" + panelId + "\"]");

                // Captcha-Refresh
                panelContainer.off("click", "#adm_captcha_refresh").on("click", "#adm_captcha_refresh", function(event) {
                    event.preventDefault();
                    var captchaImg = panelContainer.find("#adm_captcha");
                    if (captchaImg.length) {
                        captchaImg.attr("src", "' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php" + "?" + Math.random());
                    }
                });

                // Update-Check
                panelContainer.off("click", "#adm_link_check_update").on("click", "#adm_link_check_update", function(event) {
                    event.preventDefault();
                    var versionInfoContainer = panelContainer.find("#adm_version_content");
                    versionInfoContainer.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                    $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences.php", { mode: "update_check" }, function(htmlVersion) {
                        versionInfoContainer.html(htmlVersion);
                    });
                });

                // Verzeichnis-Schutz prüfen
                panelContainer.off("click", "#link_directory_protection").on("click", "#link_directory_protection", function(event) {
                    event.preventDefault();
                    var statusContainer = panelContainer.find("#directory_protection_status");
                    statusContainer.html("<i class=\"spinner-border spinner-border-sm\"></i>").show();
                    $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences.php", { mode: "htaccess" }, function(statusText) {
                        var directoryProtection = panelContainer.find("#directoryProtection");
                        directoryProtection.html("<span class=\"text-success\"><strong>" + statusText + "</strong></span>");
                    });
                });

                // Module Settings visibility
                // Universal handling for module enabled toggle within the current panel container

                // define additional ids that should also be considered for visibility toggling
                var additionalIds = [\'#system_notifications_enabled\'];
                // Look for any input whose id ends with "_module_enabled"
                var selectors = ["[id$=\'_module_enabled\']"].concat(additionalIds);

                var moduleEnabledField = panelContainer.find(selectors.join(", ")).filter(":visible");
                if (moduleEnabledField.length > 0) {
                    // Get all row elements inside the form, excluding the row containing the module enabled field
                    var formElementGroups = panelContainer.find("form div.row")
                        .not(moduleEnabledField.closest("div.row"));

                    // Function to update visibility based on the fields type and state
                    var updateVisibility = function(initialCall) {
                        var isEnabled;
                        if (moduleEnabledField.attr("type") === "checkbox") {
                            isEnabled = moduleEnabledField.is(":checked");
                        } else {
                            isEnabled = moduleEnabledField.val() != 0;
                        }

                        if (initialCall === true) {
                            if (isEnabled) {
                                formElementGroups.show();
                            } else {
                                formElementGroups.hide();
                            }
                        } else {
                            if (isEnabled) {
                                formElementGroups.slideDown("slow");
                            } else {
                                formElementGroups.slideUp("slow");
                            }
                        }
                    };

                    // Set initial state without animation
                    updateVisibility(true);

                    // Update visibility on change
                    moduleEnabledField.on("change", updateVisibility);
                }
            }

            // === 3) Hooks für Desktop-Tabs ===
            $(document).on("shown.bs.tab", "ul#adm_preferences_tabs button.nav-link", function(e) {
                var target = e.target.getAttribute("data-bs-target");
                var match = target && target.match(/^#adm_tab_(.+)_content$/);
                if (match) {
                    loadPreferencesPanel(match[1]);
                }
                // scroll to the top of the page
                $("html, body").animate({
                    scrollTop: 0
                }, 500);
            });
            // initial: load the active tab panel
            $("ul#adm_preferences_tabs button.nav-link.active").each(function() {
                var target = this.getAttribute("data-bs-target");
                var match = target && target.match(/^#adm_tab_(.+)_content$/);
                if (match) {
                    loadPreferencesPanel(match[1]);
                }
            });

            // === 4) Hooks für Mobile-Accordion ===
            $(document).on("shown.bs.collapse", "#adm_preferences_accordion .accordion-collapse", function() {
                var panelId = this.id.replace(/^collapse_/, "");
                loadPreferencesPanel(panelId);
            });
            // initial: geöffnetes Accordion-Panel laden
            $("#adm_preferences_accordion .accordion-collapse.show").each(function() {
                var panelId = this.id.replace(/^collapse_/, "");
                loadPreferencesPanel(panelId);
            });

            // === 5) Formular-Submit per AJAX ===
            $(document).on("submit", "form[id^=\"adm_preferences_form_\"]", formSubmit);
      ', true);


        ChangelogService::displayHistoryButton($this, 'preferences', 'preferences,texts');

        // Load the select2 in case any of the form uses a select box. Unfortunately, each section
        // is loaded on-demand, when there is no HTML page anymore to insert the css/JS file loading,
        // so we need to do it here, even when no selectbox will be used...
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/bootstrap-tabs-x/css/bootstrap-tabs-x-admidio.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/bootstrap-tabs-x/js/bootstrap-tabs-x-admidio.js');

        $this->assignSmartyVariable('preferenceTabs', $this->preferenceTabs);
        $this->addTemplateFile('preferences/preferences.tpl');

        parent::show();
    }
}
