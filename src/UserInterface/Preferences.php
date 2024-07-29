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
            'system_notification' => array(
                'id' => 'SystemNotification',
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
                'id' => 'Php',
                'title' => $gL10n->get('SYS_PHP'),
                'icon' => 'bi-filetype-php'
            ),
            'system_information' => array(
                'id' => 'SystemInformation',
                'title' => $gL10n->get('ORG_SYSTEM_INFORMATION'),
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
     * Generates the html of the form from the common preferences and will return the complete html.
     * @return string Returns the complete html of the form from the common preferences.
     * @throws AdmException
     * @throws \Exception
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
     * Generates the html of the form from the email dispatch preferences and will return the complete html.
     * @return string Returns the complete html of the form from the email dispatch preferences.
     * @throws AdmException
     * @throws \Exception
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
     * Generates the html of the form from the organization preferences and will return the complete html.
     * @return string Returns the complete html of the form from the organization preferences.
     * @throws AdmException
     * @throws \Exception
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
     * Generates the html of the form from the regional settings preferences and will return the complete html.
     * @return string Returns the complete html of the form from the regional settings preferences.
     * @throws AdmException
     * @throws \Exception
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
     * @throws AdmException
     * @throws \Exception
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
     * @throws AdmException
     * @throws \Exception
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
            var panels = ["Common", "Security", "Organization", "RegionalSettings", "Registration", "EmailDispatch"];

            for(var i = 0; i < panels.length; i++) {
                $("#admidioPanelPreferencesCommon" + panels[i] + " .accordion-header").click(function (e) {
                    var id = $(this).data("preferences-panel");
                    if ($("#admidioPanelPreferencesCommon" + id + " h2").attr("aria-expanded") == "true") {
                        $.get("' . ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences_function.php?mode=html_form&form=" + id, function (data) {
                            $("#admidioPanelPreferencesCommon" + id + " .accordion-body").html(data);
                        });
                    }
                });

                $(document).on("submit", "#preferencesForm" + panels[i], formSubmit);
            }

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
