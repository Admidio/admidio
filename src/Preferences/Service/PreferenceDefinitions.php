<?php
namespace Admidio\Preferences\Service;

use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\SSO\Service\OIDCService;
use InvalidArgumentException;

/**
 ***********************************************************************************************
 * Canonical definitions for Admidio's core organization preferences.
 *
 * This registry is the single source of truth for defaults and semantic constraints. The
 * installer, web preference workflow and headless interfaces all consume the same definitions.
 * Presentation concerns such as translated labels, help text and form layout remain in the UI.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class PreferenceDefinitions
{
    private const DEFAULT_DOMAIN_COPYRIGHT = 'domain_copyright';
    private const DEFAULT_ADMIDIO_URL = 'admidio_url';
    private const DEFAULT_OIDC_ISSUER_URL = 'oidc_issuer_url';

    private const VALIDATOR_MEMBER_SHARING = 'member_sharing';
    private const VALIDATOR_EVENTS_VIEW = 'events_view';
    private const VALIDATOR_EMAIL = 'email';
    private const VALIDATOR_URL_OPTIONAL = 'url_optional';
    private const VALIDATOR_COLOR = 'color';
    private const VALIDATOR_OIDC_ISSUER = 'oidc_issuer';
    private const VALIDATOR_THEME = 'theme';
    private const VALIDATOR_LANGUAGE = 'language';
    private const VALIDATOR_COUNTRY = 'country';
    private const VALIDATOR_MAIL_TEMPLATE = 'mail_template';
    private const VALIDATOR_ECARD_TEMPLATE = 'ecard_template';
    private const VALIDATOR_CAPTCHA_FONT = 'captcha_font';
    private const VALIDATOR_CAPTCHA_BACKGROUND = 'captcha_background';
    private const VALIDATOR_CATEGORY_REPORT = 'category_report';
    private const VALIDATOR_CONTACTS_LIST = 'contacts_list';
    private const VALIDATOR_LIST = 'list';
    private const VALIDATOR_NOTIFICATION_ROLE = 'notification_role';
    private const VALIDATOR_INVENTORY_ROLES = 'inventory_roles';
    private const VALIDATOR_INVENTORY_KEEPER_FIELDS = 'inventory_keeper_fields';
    private const VALIDATOR_INVENTORY_PROFILE_FIELDS = 'inventory_profile_fields';
    private const VALIDATOR_SSO_KEY = 'sso_key';
    private const VALIDATOR_OIDC_SIGNING_KEY = 'oidc_signing_key';

    /** @var array<int,string> */
    private const VALIDATORS = array(
        self::VALIDATOR_MEMBER_SHARING,
        self::VALIDATOR_EVENTS_VIEW,
        self::VALIDATOR_EMAIL,
        self::VALIDATOR_URL_OPTIONAL,
        self::VALIDATOR_COLOR,
        self::VALIDATOR_OIDC_ISSUER,
        self::VALIDATOR_THEME,
        self::VALIDATOR_LANGUAGE,
        self::VALIDATOR_COUNTRY,
        self::VALIDATOR_MAIL_TEMPLATE,
        self::VALIDATOR_ECARD_TEMPLATE,
        self::VALIDATOR_CAPTCHA_FONT,
        self::VALIDATOR_CAPTCHA_BACKGROUND,
        self::VALIDATOR_CATEGORY_REPORT,
        self::VALIDATOR_CONTACTS_LIST,
        self::VALIDATOR_LIST,
        self::VALIDATOR_NOTIFICATION_ROLE,
        self::VALIDATOR_INVENTORY_ROLES,
        self::VALIDATOR_INVENTORY_KEEPER_FIELDS,
        self::VALIDATOR_INVENTORY_PROFILE_FIELDS,
        self::VALIDATOR_SSO_KEY,
        self::VALIDATOR_OIDC_SIGNING_KEY,
    );

    /**
     * The keys a definition of table() may use. Everything else is a typo, see coverageProblems().
     *
     * @var array<int,string>
     */
    private const DEFINITION_KEYS = array(
        'default', 'defaultProvider', 'type', 'values', 'minimum', 'maximum', 'maxLength',
        'required', 'sensitive', 'internal', 'validator'
    );

    /** @var array<int,string> */
    private const DEFAULT_PROVIDERS = array(
        self::DEFAULT_DOMAIN_COPYRIGHT,
        self::DEFAULT_ADMIDIO_URL,
        self::DEFAULT_OIDC_ISSUER_URL
    );

    /**
     * Every core organization preference and everything that is known about it.
     *
     * A definition is an array with the keys listed below. As a shortcut it may also be a plain
     * string, which is read as the default value: 'weblinks_target' => '_blank' and
     * 'weblinks_target' => array('default' => '_blank') describe the same preference.
     *
     * - default          string  The value a new organization gets. Exactly one of default and
     *                            defaultProvider has to be present.
     * - defaultProvider  string  Name of a default that can only be built once the installation
     *                            constants exist, because it is derived from them. One of the
     *                            DEFAULT_* constants of this class.
     * - type             string  string (the default), bool, int, enum or reference. A bool is
     *                            normalized to '0'/'1' and accepts 0/1, true/false, yes/no and
     *                            on/off, an int to its decimal representation. A reference is a
     *                            string that names another record and is checked by its validator.
     * - values           array   The permitted values of an enum, as strings. Required for enum
     *                            and meaningless for every other type.
     * - minimum          int     Smallest permitted value of an int.
     * - maximum          int     Largest permitted value of an int.
     * - maxLength        int     Largest permitted length of a string, matching the length the
     *                            form field offers.
     * - required         bool    The value must not be empty. Only meaningful for string and
     *                            reference; bool, int and enum always carry a value.
     * - sensitive        bool    The value is a secret. It is masked in the changelog, redacted
     *                            by config:list and config:get, and left out of config:export
     *                            unless it is asked for explicitly.
     * - internal         bool    Admidio maintains the value itself, an administrator does not.
     *                            The installer still seeds it, but supportedNames(), definition()
     *                            and the whole config:* write path refuse it.
     * - validator        string  Name of the semantic rule that is applied after the scalar type
     *                            was normalized, one of the VALIDATOR_* constants of this class. A
     *                            validator sees the complete proposed target state, so it can
     *                            judge preferences that depend on each other.
     *
     * coverageProblems() rejects a key that is not in this list, so a typo cannot silently turn
     * into a constraint that is never applied.
     *
     * @return array<string,array<string,mixed>|string>
     */
    private static function table(): array
    {
        return array(
            'enable_rss' => array('default' => '1', 'type' => 'bool'),
            'enable_auto_login' => array('default' => '1', 'type' => 'bool'),
            'security_login_email_address_enabled' => array('default' => '0', 'type' => 'bool'),
            'default_country' => array('default' => 'DEU', 'type' => 'reference', 'validator' => self::VALIDATOR_COUNTRY),
            'logout_minutes' => array('default' => '20', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'homepage_logout' => array('default' => 'modules/overview.php', 'maxLength' => 250, 'required' => true),
            'homepage_login' => array('default' => 'modules/overview.php', 'maxLength' => 250, 'required' => true),
            'enable_password_recovery' => array('default' => '1', 'type' => 'bool'),
            'two_factor_authentication_enabled' => array('default' => '0', 'type' => 'bool'),
            'system_browser_update_check' => array('default' => '0', 'type' => 'bool'),
            'system_cookie_note' => array('default' => '1', 'type' => 'bool'),
            'system_currency' => array('default' => '€', 'maxLength' => 20),
            'system_date' => array('default' => 'd.m.Y', 'maxLength' => 20, 'required' => true),
            'system_hashing_cost' => array('default' => '10', 'type' => 'int', 'internal' => true),
            'system_js_editor_enabled' => array('default' => '1', 'type' => 'bool'),
            'system_language' => array('default' => 'de', 'type' => 'reference', 'required' => true, 'validator' => self::VALIDATOR_LANGUAGE),
            'system_search_similar' => array('default' => '1', 'type' => 'bool'),
            'system_show_create_edit' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'system_time' => array('default' => 'H:i', 'maxLength' => 20, 'required' => true),
            'system_url_imprint' => array('default' => '', 'maxLength' => 250, 'validator' => self::VALIDATOR_URL_OPTIONAL),
            'system_url_data_protection' => array('default' => '', 'maxLength' => 250, 'validator' => self::VALIDATOR_URL_OPTIONAL),
            'password_min_strength' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2', '3', '4')),
            'path_for_calculating_disk_usage' => array('default' => '', 'maxLength' => 250),
            'theme' => array('default' => 'simple', 'type' => 'reference', 'required' => true, 'validator' => self::VALIDATOR_THEME),
            'theme_fallback' => array('default' => 'simple', 'type' => 'reference', 'required' => true, 'validator' => self::VALIDATOR_THEME),
            'theme_color_primary' => array('default' => '#349aaa', 'validator' => self::VALIDATOR_COLOR),
            'theme_color_secondary' => array('default' => '#a7d9e0', 'validator' => self::VALIDATOR_COLOR),
            'theme_color_tertiary' => array('default' => '#e9ecef', 'validator' => self::VALIDATOR_COLOR),
            'theme_color_text' => array('default' => '#263340', 'validator' => self::VALIDATOR_COLOR),
            'theme_color_background' => array('default' => '#ffffff', 'validator' => self::VALIDATOR_COLOR),
            'theme_logo_file' => '',
            'theme_logo_file_max_height' => array('default' => '60', 'type' => 'int', 'minimum' => 40, 'maximum' => 200, 'required' => true),
            'theme_admidio_headline' => 'SYS_ONLINE_MEMBERSHIP_ADMINISTRATION',
            'theme_favicon_file' => '',
            'theme_additional_styles_file' => '',
            'registration_adopt_all_data' => array('default' => '1', 'type' => 'bool'),
            'registration_module_enabled' => array('default' => '1', 'type' => 'bool'),
            'registration_manual_approval' => array('default' => '1', 'type' => 'bool'),
            'registration_send_notification_email' => array('default' => '1', 'type' => 'bool'),
            'changelog_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'changelog_default_days' => array('default' => '365', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999999999),
            'changelog_retention_days' => array('default' => '0', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999999999),
            'changelog_last_purge' => array('default' => '0', 'type' => 'int', 'internal' => true),
            'changelog_table_user_data' => array('default' => '1', 'type' => 'bool'),
            'changelog_table_users' => array('default' => '1', 'type' => 'bool'),
            'changelog_table_members' => array('default' => '1', 'type' => 'bool'),
            'changelog_table_user_fields' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_user_field_select_options' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_announcements' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_events' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_rooms' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_roles' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_role_dependencies' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_roles_rights' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_roles_rights_data' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_categories' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_category_report' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_links' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_folders' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_files' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_organizations' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_menu' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_user_relation_types' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_user_relations' => array('default' => '1', 'type' => 'bool'),
            'changelog_table_photos' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_lists' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_list_columns' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_preferences' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_texts' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_forum_topics' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_forum_posts' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_inventory_fields' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_inventory_field_select_options' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_inventory_items' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_inventory_item_data' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_inventory_item_borrow_data' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_saml_clients' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_oidc_clients' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_sso_keys' => array('default' => '0', 'type' => 'bool'),
            'changelog_table_others' => array('default' => '0', 'type' => 'bool'),
            'mail_send_method' => array('default' => 'phpmail', 'type' => 'enum', 'values' => array('phpmail', 'SMTP')),
            'mail_sending_mode' => array('default' => '0', 'type' => 'enum', 'values' => array('0', '1')),
            'mail_recipients_with_roles' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'mail_number_recipients' => array('default' => '50', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'mail_into_to' => array('default' => '0', 'type' => 'bool'),
            'mail_sender_mode' => array('default' => '1', 'type' => 'enum', 'values' => array('1', '2', '3')),
            'mail_sender_email' => array('default' => '', 'maxLength' => 50, 'required' => true, 'validator' => self::VALIDATOR_EMAIL),
            'mail_sender_name' => array('default' => '', 'maxLength' => 50, 'required' => true),
            'mail_send_to_all_addresses' => array('default' => '0', 'type' => 'bool'),
            'mail_smtp_host' => array('default' => '', 'maxLength' => 50),
            'mail_smtp_auth' => array('default' => '1', 'type' => 'bool'),
            'mail_smtp_port' => array('default' => '587', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'mail_smtp_secure' => array('default' => 'tls', 'type' => 'enum', 'values' => array('', 'ssl', 'tls')),
            'mail_smtp_authentication_type' => array('default' => '', 'type' => 'enum', 'values' => array('', 'LOGIN', 'PLAIN', 'CRAM-MD5')),
            'mail_smtp_user' => array('default' => '', 'maxLength' => 100),
            'mail_smtp_password' => array('default' => '', 'sensitive' => true, 'maxLength' => 100),
            'system_notifications_enabled' => array('default' => '1', 'type' => 'bool'),
            'system_notifications_role' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_NOTIFICATION_ROLE),
            'system_notifications_new_entries' => array('default' => '0', 'type' => 'bool'),
            'system_notifications_profile_changes' => array('default' => '0', 'type' => 'bool'),
            'system_notifications_inventory_changes' => array('default' => '0', 'type' => 'bool'),
            'captcha_enabled' => array('default' => '1', 'type' => 'bool'),
            'captcha_type' => array('default' => 'pic', 'type' => 'enum', 'values' => array('pic', 'calc', 'word')),
            'captcha_fonts' => array('default' => 'AHGBold.ttf', 'type' => 'reference', 'validator' => self::VALIDATOR_CAPTCHA_FONT),
            'captcha_width' => array('default' => '215', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'captcha_lines_numbers' => array('default' => '5', 'type' => 'int', 'minimum' => 1, 'maximum' => 25),
            'captcha_perturbation' => '0.75',
            'captcha_background_image' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_CAPTCHA_BACKGROUND),
            'captcha_background_color' => array('default' => '#B6D6DB', 'maxLength' => 7, 'validator' => self::VALIDATOR_COLOR),
            'captcha_text_color' => array('default' => '#707070', 'maxLength' => 7, 'validator' => self::VALIDATOR_COLOR),
            'captcha_line_color' => array('default' => '#707070', 'maxLength' => 7, 'validator' => self::VALIDATOR_COLOR),
            'captcha_charset' => array('default' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxy', 'maxLength' => 80),
            'captcha_signature' => array('default' => 'Powered by Admidio.org', 'maxLength' => 60),
            'announcements_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'announcements_per_page' => array('default' => '10', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'announcements_clamp_text_lines' => array('default' => '0', 'type' => 'int', 'minimum' => 0, 'maximum' => null),
            'category_report_module_enabled' => array('default' => '1', 'type' => 'bool'),
            'category_report_default_configuration' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_CATEGORY_REPORT),
            'contacts_list_configuration' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_CONTACTS_LIST),
            'contacts_per_page' => array('default' => '25', 'type' => 'enum', 'values' => array('10', '25', '50', '100', '-1')),
            'contacts_show_all' => array('default' => '1', 'type' => 'bool'),
            'contacts_suborganization_use_same_members' => array('default' => '0', 'type' => 'bool', 'validator' => self::VALIDATOR_MEMBER_SHARING),
            'contacts_user_relations_enabled' => array('default' => '1', 'type' => 'bool'),
            'documents_files_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'documents_files_max_upload_size' => array('default' => '3', 'type' => 'int', 'minimum' => 0, 'maximum' => 999999999),
            'inventory_module_enabled' => array('default' => '2', 'type' => 'enum', 'values' => array('0', '1', '2', '3', '4', '5')),
            'inventory_visible_for' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_INVENTORY_ROLES),
            'inventory_items_per_page' => array('default' => '25', 'type' => 'enum', 'values' => array('10', '25', '50', '100', '-1')),
            'inventory_field_history_days' => array('default' => '365', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999999999),
            'inventory_item_picture_enabled' => array('default' => '1', 'type' => 'bool'),
            'inventory_item_picture_storage' => array('default' => '0', 'type' => 'enum', 'values' => array('0', '1')),
            'inventory_item_picture_width' => array('default' => '130', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'inventory_item_picture_height' => array('default' => '170', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'inventory_show_obsolete_select_field_options' => array('default' => '1', 'type' => 'bool'),
            'inventory_system_field_names_editable' => array('default' => '0', 'type' => 'bool'),
            'inventory_allow_keeper_edit' => array('default' => '0', 'type' => 'bool'),
            'inventory_allowed_keeper_edit_fields' => array('default' => 'LAST_RECEIVER,BORROW_DATE,RETURN_DATE', 'type' => 'reference', 'validator' => self::VALIDATOR_INVENTORY_KEEPER_FIELDS),
            'inventory_current_user_default_keeper' => array('default' => '0', 'type' => 'bool'),
            'inventory_allow_negative_numbers' => array('default' => '1', 'type' => 'bool'),
            'inventory_decimal_places' => array('default' => '1', 'type' => 'int', 'minimum' => 0, 'maximum' => null, 'required' => true),
            'inventory_field_date_time_format' => array('default' => 'date', 'type' => 'enum', 'values' => array('date', 'datetime')),
            'inventory_items_disable_borrowing' => array('default' => '0', 'type' => 'bool'),
            'inventory_profile_view_enabled' => array('default' => '1', 'type' => 'bool'),
            'inventory_profile_view' => array('default' => 'LAST_RECEIVER', 'type' => 'reference', 'validator' => self::VALIDATOR_INVENTORY_PROFILE_FIELDS),
            'inventory_export_filename' => array('default' => 'SYS_INVENTORY', 'maxLength' => 50, 'required' => true),
            'inventory_add_date' => array('default' => '0', 'type' => 'bool'),
            'events_list_configuration' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_LIST),
            'events_ical_export_enabled' => array('default' => '1', 'type' => 'bool'),
            'events_may_take_part' => array('default' => '0', 'type' => 'bool'),
            'events_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'events_per_page' => array('default' => '10', 'type' => 'enum', 'values' => array('10', '25', '50', '100', '-1')),
            'events_clamp_text_lines' => array('default' => '0', 'type' => 'int', 'minimum' => 0, 'maximum' => null),
            'events_rooms_enabled' => array('default' => '0', 'type' => 'bool'),
            'events_save_cancellations' => array('default' => '1', 'type' => 'bool'),
            'events_show_map_link' => array('default' => '1', 'type' => 'bool'),
            'events_view' => array('default' => 'detail', 'type' => 'enum', 'values' => array('detail', 'compact', 'room', 'participants', 'description'), 'validator' => self::VALIDATOR_EVENTS_VIEW),
            'forum_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'forum_posts_per_page' => array('default' => '15', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'forum_topics_per_page' => array('default' => '10', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'forum_view' => array('default' => 'cards', 'type' => 'enum', 'values' => array('cards', 'list')),
            'groups_roles_default_configuration' => array('default' => '', 'type' => 'reference', 'validator' => self::VALIDATOR_LIST),
            'groups_roles_module_enabled' => array('default' => '1', 'type' => 'bool'),
            'groups_roles_export' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'groups_roles_edit_lists' => array('default' => '1', 'type' => 'enum', 'values' => array('1', '2', '3')),
            'groups_roles_members_per_page' => array('default' => '25', 'type' => 'enum', 'values' => array('10', '25', '50', '100', '-1')),
            'groups_roles_show_former_members' => array('default' => '2', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'mail_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'pm_module_enabled' => array('default' => '1', 'type' => 'bool'),
            'mail_delivery_confirmation' => array('default' => '0', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'mail_html_registered_users' => array('default' => '1', 'type' => 'bool'),
            'mail_max_receiver' => array('default' => '10', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'mail_save_attachments' => array('default' => '1', 'type' => 'bool'),
            'mail_show_former' => array('default' => '1', 'type' => 'bool'),
            'mail_template' => array('default' => 'default.html', 'type' => 'reference', 'validator' => self::VALIDATOR_MAIL_TEMPLATE),
            'max_email_attachment_size' => array('default' => '1', 'type' => 'int', 'minimum' => 0, 'maximum' => 999999),
            'photo_albums_per_page' => array('default' => '24', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'photo_download_enabled' => array('default' => '0', 'type' => 'bool'),
            'photo_ecard_enabled' => array('default' => '1', 'type' => 'bool'),
            'photo_ecard_scale' => array('default' => '500', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'photo_ecard_template' => array('default' => 'postcard.tpl', 'type' => 'reference', 'validator' => self::VALIDATOR_ECARD_TEMPLATE),
            'photo_image_text' => array('defaultProvider' => self::DEFAULT_DOMAIN_COPYRIGHT, 'maxLength' => 60),
            'photo_image_text_size' => array('default' => '40', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'photo_keep_original' => array('default' => '0', 'type' => 'bool'),
            'photo_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'photo_show_width' => array('default' => '1200', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'photo_show_height' => array('default' => '1200', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'photo_show_mode' => array('default' => '1', 'type' => 'enum', 'values' => array('1', '2')),
            'photo_thumbs_page' => array('default' => '24', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'photo_thumbs_scale' => array('default' => '500', 'type' => 'int', 'minimum' => 1, 'maximum' => 9999),
            'profile_show_obsolete_select_field_options' => array('default' => '1', 'type' => 'bool'),
            'profile_show_map_link' => array('default' => '0', 'type' => 'bool'),
            'profile_show_empty_fields' => array('default' => '1', 'type' => 'bool'),
            'profile_show_roles' => array('default' => '1', 'type' => 'bool'),
            'profile_show_former_roles' => array('default' => '1', 'type' => 'bool'),
            'profile_show_extern_roles' => array('default' => '1', 'type' => 'bool'),
            'profile_membership_duration_exact' => array('default' => '1', 'type' => 'bool'),
            'profile_photo_storage' => array('default' => '0', 'type' => 'enum', 'values' => array('0', '1')),
            'sso_saml_enabled' => array('default' => '0', 'type' => 'bool'),
            'sso_saml_entity_id' => array('defaultProvider' => self::DEFAULT_ADMIDIO_URL),
            'sso_saml_want_requests_signed' => array('default' => '1', 'type' => 'bool'),
            'sso_saml_signing_key' => array('default' => '0', 'type' => 'reference', 'validator' => self::VALIDATOR_SSO_KEY),
            'sso_saml_encryption_key' => array('default' => '0', 'type' => 'reference', 'validator' => self::VALIDATOR_SSO_KEY),
            'sso_oidc_enabled' => array('default' => '0', 'type' => 'bool'),
            'sso_oidc_issuer_url' => array('defaultProvider' => self::DEFAULT_OIDC_ISSUER_URL, 'validator' => self::VALIDATOR_OIDC_ISSUER),
            'sso_oidc_signing_key' => array('default' => '0', 'type' => 'reference', 'validator' => self::VALIDATOR_OIDC_SIGNING_KEY),
            'sso_oidc_encryption_key' => array('default' => '', 'internal' => true, 'sensitive' => true),
            'weblinks_module_enabled' => array('default' => '1', 'type' => 'enum', 'values' => array('0', '1', '2')),
            'weblinks_per_page' => array('default' => '0', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'weblinks_redirect_seconds' => array('default' => '10', 'type' => 'int', 'minimum' => 0, 'maximum' => 9999),
            'weblinks_target' => array('default' => '_blank', 'type' => 'enum', 'values' => array('_self', '_blank')),
        );
    }

    /**
     * The definitions of table() with every shortcut expanded, so that every caller sees the same
     * shape. The table is a constant expression, therefore it is only built once.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        static $definitions = null;

        if ($definitions === null) {
            $definitions = array();
            foreach (self::table() as $name => $definition) {
                $definitions[$name] = is_array($definition) ? $definition : array('default' => $definition);
            }
        }

        return $definitions;
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        $defaults = array();
        foreach (self::all() as $name => $definition) {
            $defaults[$name] = array_key_exists('defaultProvider', $definition)
                ? self::resolveDefaultProvider((string)$definition['defaultProvider'])
                : (string)$definition['default'];
        }
        return $defaults;
    }

    /** @return array<int,string> */
    public static function supportedNames(): array
    {
        $names = array();
        foreach (self::all() as $name => $definition) {
            if (empty($definition['internal'])) {
                $names[] = $name;
            }
        }
        sort($names);
        return $names;
    }

    /** @return array<int,string> */
    public static function internalNames(): array
    {
        $names = array();
        foreach (self::all() as $name => $definition) {
            if (!empty($definition['internal'])) {
                $names[] = $name;
            }
        }
        sort($names);
        return $names;
    }

    public static function exists(string $name): bool
    {
        return array_key_exists($name, self::all());
    }

    public static function isSupported(string $name): bool
    {
        $definitions = self::all();
        return isset($definitions[$name]) && empty($definitions[$name]['internal']);
    }

    public static function isSensitive(string $name): bool
    {
        $definition = self::rawDefinition($name);
        return !empty($definition['sensitive']);
    }

    /**
     * Semantic validation rules that form adapters can translate into presentation attributes.
     *
     * @return array{type:string,minimum:?int,maximum:?int,maxLength:?int,required:bool}
     */
    public static function validationRules(string $name): array
    {
        self::assertSupported($name);
        $definition = self::rawDefinition($name);

        return array(
            'type' => (string)($definition['type'] ?? 'string'),
            'minimum' => array_key_exists('minimum', $definition) ? $definition['minimum'] : null,
            'maximum' => array_key_exists('maximum', $definition) ? $definition['maximum'] : null,
            'maxLength' => array_key_exists('maxLength', $definition) ? $definition['maxLength'] : null,
            'required' => !empty($definition['required'])
        );
    }

    /**
     * Return public schema metadata for an administrator-editable preference.
     *
     * @return array<string,mixed>
     */
    public static function definition(string $name): array
    {
        self::assertSupported($name);
        $definition = self::rawDefinition($name);
        if (array_key_exists('defaultProvider', $definition)) {
            $definition['default'] = self::resolveDefaultProvider((string)$definition['defaultProvider']);
        }
        unset($definition['internal'], $definition['validator'], $definition['defaultProvider']);
        $definition['type'] ??= 'string';
        $definition['sensitive'] = !empty($definition['sensitive']);
        return $definition;
    }

    /**
     * Validate and normalize one preference using the same batch path as configuration imports.
     */
    public static function normalize(string $name, mixed $value): string
    {
        return self::normalizeValues(array($name => $value))[$name];
    }

    /**
     * Normalize a set of preferences and validate semantic dependencies against the complete
     * proposed target state. This is important for imports and web panels that change related
     * settings together, for example enabling rooms while selecting the room event view.
     *
     * @param array<string,mixed> $values
     * @return array<string,string>
     */
    public static function normalizeValues(array $values): array
    {
        $normalized = array();
        $definitions = array();

        // First normalize scalar types without consulting other preferences. The second pass can
        // then evaluate references and cross-preference rules against the complete target values.
        foreach ($values as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Preference names must be strings.');
            }
            self::assertSupported($name);
            $definition = self::rawDefinition($name);
            $definitions[$name] = $definition;
            $normalized[$name] = self::normalizeScalar($name, $value, $definition);
        }

        foreach ($normalized as $name => $value) {
            $normalized[$name] = self::runValidator(
                $name,
                $value,
                $definitions[$name]['validator'] ?? '',
                $normalized
            );
        }

        return $normalized;
    }

    /** @param array<string,mixed> $definition */
    private static function normalizeScalar(string $name, mixed $value, array $definition): string
    {
        if (is_array($value)) {
            $value = implode(',', array_map(static fn (mixed $entry): string => (string)$entry, $value));
        }
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Preference "' . $name . '" requires a scalar value.');
        }
        $value = (string)($value ?? '');
        $type = $definition['type'] ?? 'string';

        if ($type === 'bool') {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized === null) {
                throw new InvalidArgumentException(
                    'Preference "' . $name . '" expects a boolean value (0/1, true/false, yes/no or on/off).'
                );
            }
            return $normalized ? '1' : '0';
        }

        if ($type === 'int') {
            if (!preg_match('/^-?\d+$/', $value)) {
                throw new InvalidArgumentException('Preference "' . $name . '" expects an integer value.');
            }
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer === false) {
                throw new InvalidArgumentException('Preference "' . $name . '" expects an integer value.');
            }
            $minimum = $definition['minimum'] ?? null;
            $maximum = $definition['maximum'] ?? null;
            if (($minimum !== null && $integer < $minimum) || ($maximum !== null && $integer > $maximum)) {
                $range = $maximum === null ? $minimum . ' or greater' : $minimum . '..' . $maximum;
                throw new InvalidArgumentException('Preference "' . $name . '" expects an integer in range ' . $range . '.');
            }
            return (string)$integer;
        }

        if ($type === 'enum') {
            if (!in_array($value, $definition['values'] ?? array(), true)) {
                throw new InvalidArgumentException(
                    'Preference "' . $name . '" expects one of: ' . implode(', ', $definition['values'] ?? array()) . '.'
                );
            }
            return $value;
        }

        if (!empty($definition['required']) && trim($value) === '') {
            throw new InvalidArgumentException('Preference "' . $name . '" must not be empty.');
        }
        if (isset($definition['maxLength']) && strlen($value) > $definition['maxLength']) {
            throw new InvalidArgumentException(
                'Preference "' . $name . '" must not exceed ' . $definition['maxLength'] . ' characters.'
            );
        }

        return $value;
    }

    /**
     * Check the canonical registry itself. Since the installer consumes defaults() directly,
     * there is no second seeded preference list that can drift out of sync.
     *
     * @return array<int,string>
     */
    public static function coverageProblems(): array
    {
        $problems = array();
        foreach (self::all() as $name => $definition) {
            $hasDefault = array_key_exists('default', $definition);
            $hasProvider = array_key_exists('defaultProvider', $definition);
            if ($hasDefault === $hasProvider) {
                $problems[] = 'Preference "' . $name
                    . '" must define exactly one of default or defaultProvider.';
            } elseif ($hasProvider
                && !in_array((string)$definition['defaultProvider'], self::DEFAULT_PROVIDERS, true)) {
                $problems[] = 'Preference "' . $name . '" uses the unknown default provider "'
                    . $definition['defaultProvider'] . '".';
            }
            $type = $definition['type'] ?? 'string';
            if (!in_array($type, array('bool', 'int', 'enum', 'reference', 'string'), true)) {
                $problems[] = 'Preference "' . $name . '" has unsupported type "' . $type . '".';
            }
            if ($type === 'enum' && empty($definition['values'])) {
                $problems[] = 'Preference "' . $name . '" is an enum without values.';
            }
            $validator = (string)($definition['validator'] ?? '');
            if ($validator !== '' && !in_array($validator, self::VALIDATORS, true)) {
                $problems[] = 'Preference "' . $name . '" uses the unknown validator "'
                    . $validator . '".';
            }
            foreach (array_diff(array_keys($definition), self::DEFINITION_KEYS) as $key) {
                $problems[] = 'Preference "' . $name . '" uses the unknown key "' . $key . '".';
            }
        }
        return $problems;
    }

    private static function resolveDefaultProvider(string $provider): string
    {
        return match ($provider) {
            self::DEFAULT_DOMAIN_COPYRIGHT => '© ' . self::runtimeConstant('DOMAIN'),
            self::DEFAULT_ADMIDIO_URL => self::runtimeConstant('ADMIDIO_URL'),
            self::DEFAULT_OIDC_ISSUER_URL => self::runtimeConstant('ADMIDIO_URL')
                . self::runtimeConstant('FOLDER_MODULES') . '/sso/index.php',
            default => throw new InvalidArgumentException('Unknown preference default provider "' . $provider . '".')
        };
    }

    private static function runtimeConstant(string $name): string
    {
        if (!defined($name)) {
            throw new InvalidArgumentException(
                'Preference defaults requiring runtime constant "' . $name . '" cannot be resolved before bootstrap.'
            );
        }
        return (string)constant($name);
    }

    /** @return array<string,mixed> */
    private static function rawDefinition(string $name): array
    {
        $definitions = self::all();
        if (!isset($definitions[$name])) {
            throw new InvalidArgumentException('Unknown core preference "' . $name . '".');
        }
        return $definitions[$name];
    }

    private static function assertSupported(string $name): void
    {
        $definition = self::rawDefinition($name);
        if (!empty($definition['internal'])) {
            throw new InvalidArgumentException(
                'Preference "' . $name . '" is maintained internally and is not administrator-editable.'
            );
        }
    }

    /**
     * @param array<string,string> $proposedValues Values normalized in the same batch.
     */
    private static function runValidator(
        string $name,
        string $value,
        string $validator,
        array $proposedValues
    ): string {
        global $gDb, $gCurrentOrgId, $gCurrentOrganization, $gL10n, $gSettingsManager;

        switch ($validator) {
            case '':
                return $value;
            case self::VALIDATOR_MEMBER_SHARING:
                if (!isset($gCurrentOrganization)
                    || $gCurrentOrganization->isChildOrganization()
                    || !$gCurrentOrganization->isParentOrganization()) {
                    throw new InvalidArgumentException(
                        'Preference "contacts_suborganization_use_same_members" can only be edited for a parent organization.'
                    );
                }
                return $value;
            case self::VALIDATOR_EVENTS_VIEW:
                $roomsEnabled = array_key_exists('events_rooms_enabled', $proposedValues)
                    ? $proposedValues['events_rooms_enabled'] === '1'
                    : $gSettingsManager->getBool('events_rooms_enabled');
                if ($value === 'room' && !$roomsEnabled) {
                    throw new InvalidArgumentException(
                        'Preference "events_view" may use "room" only while events_rooms_enabled is enabled.'
                    );
                }
                return $value;
            case self::VALIDATOR_EMAIL:
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    throw new InvalidArgumentException('Preference "' . $name . '" requires a valid email address.');
                }
                return $value;
            case self::VALIDATOR_URL_OPTIONAL:
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                    throw new InvalidArgumentException('Preference "' . $name . '" requires a valid URL or an empty value.');
                }
                return $value;
            case self::VALIDATOR_COLOR:
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    throw new InvalidArgumentException('Preference "' . $name . '" requires a color in #RRGGBB form.');
                }
                return $value;
            case self::VALIDATOR_OIDC_ISSUER:
                $value = trim($value);
                if ($value === OIDCService::getDefaultIssuerURL()) {
                    return '';
                }
                if ($value !== '') {
                    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                        throw new InvalidArgumentException('Preference "sso_oidc_issuer_url" requires a valid URL or an empty value.');
                    }
                    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
                    if (!in_array($scheme, array('http', 'https'), true)) {
                        throw new InvalidArgumentException('Preference "sso_oidc_issuer_url" requires an HTTP(S) URL.');
                    }
                    $value = rtrim($value, '/');
                }
                return $value;
            case self::VALIDATOR_THEME:
                if (!StringUtils::strIsValidFolderName($value)
                    || !is_file(ADMIDIO_PATH . FOLDER_THEMES . '/' . $value . '/index.html')) {
                    throw new InvalidArgumentException('Preference "' . $name . '" references an unavailable theme.');
                }
                return $value;
            case self::VALIDATOR_LANGUAGE:
                if (!StringUtils::strIsValidFolderName($value)
                    || !is_file(ADMIDIO_PATH . FOLDER_LANGUAGES . '/' . $value . '.xml')) {
                    throw new InvalidArgumentException('Preference "system_language" references an unavailable language.');
                }
                return $value;
            case self::VALIDATOR_COUNTRY:
                if ($value !== '' && !array_key_exists($value, $gL10n->getCountries())) {
                    throw new InvalidArgumentException('Preference "default_country" references an unknown country code.');
                }
                return $value;
            case self::VALIDATOR_MAIL_TEMPLATE:
                return self::validateFileChoice($value, ADMIDIO_PATH . FOLDER_DATA . '/mail_templates', 'mail template');
            case self::VALIDATOR_ECARD_TEMPLATE:
                return self::validateFileChoice($value, ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', 'e-card template');
            case self::VALIDATOR_CAPTCHA_FONT:
                return self::validateFileChoice($value, ADMIDIO_PATH . FOLDER_SYSTEM . '/fonts', 'CAPTCHA font', false);
            case self::VALIDATOR_CAPTCHA_BACKGROUND:
                return self::validateFileChoice($value, ADMIDIO_PATH . FOLDER_LIBS . '/securimage/backgrounds', 'CAPTCHA background', true);
            case self::VALIDATOR_CATEGORY_REPORT:
                return self::validateNumericReference(
                    $value,
                    'SELECT COUNT(*) FROM ' . TBL_CATEGORY_REPORT . ' WHERE crt_id = ? AND crt_org_id = ?',
                    array($gCurrentOrgId),
                    'category-report configuration'
                );
            case self::VALIDATOR_CONTACTS_LIST:
                if ($value === '' || $value === '0') {
                    return $value;
                }
                self::assertNumeric($value, $name);
                $count = (int)$gDb->queryPrepared(
                    'SELECT COUNT(*) FROM ' . TBL_LISTS . '
                      WHERE lst_id = ? AND lst_org_id = ? AND lst_global = true
                        AND NOT EXISTS (
                            SELECT 1 FROM ' . TBL_LIST_COLUMNS . '
                             WHERE lsc_lst_id = lst_id AND lsc_special_field LIKE \'mem%\'
                        )',
                    array((int)$value, $gCurrentOrgId)
                )->fetchColumn();
                if ($count !== 1) {
                    throw new InvalidArgumentException('Preference "contacts_list_configuration" references an unavailable list.');
                }
                return (string)(int)$value;
            case self::VALIDATOR_LIST:
                return self::validateNumericReference(
                    $value,
                    'SELECT COUNT(*) FROM ' . TBL_LISTS . ' WHERE lst_id = ? AND lst_org_id = ? AND lst_global = true',
                    array($gCurrentOrgId),
                    'list configuration'
                );
            case self::VALIDATOR_NOTIFICATION_ROLE:
                if ($value === '') {
                    return '';
                }
                $count = (int)$gDb->queryPrepared(
                    'SELECT COUNT(*) FROM ' . TBL_ROLES . '
                 INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                      WHERE rol_uuid = ? AND rol_valid = true AND rol_system = false
                        AND rol_all_lists_view = true AND cat_org_id = ? AND cat_name_intern <> \'EVENTS\'',
                    array($value, $gCurrentOrgId)
                )->fetchColumn();
                if ($count !== 1) {
                    throw new InvalidArgumentException('Preference "system_notifications_role" references an unavailable role.');
                }
                return $value;
            case self::VALIDATOR_INVENTORY_ROLES:
                $values = self::commaValues($value);
                foreach ($values as $roleId) {
                    self::assertNumeric($roleId, $name);
                    $count = (int)$gDb->queryPrepared(
                        'SELECT COUNT(*) FROM ' . TBL_ROLES . '
                     INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                          WHERE rol_id = ? AND rol_valid = true AND rol_system = false AND cat_name_intern <> \'EVENTS\'',
                        array((int)$roleId)
                    )->fetchColumn();
                    if ($count !== 1) {
                        throw new InvalidArgumentException('Preference "inventory_visible_for" references unavailable role ' . $roleId . '.');
                    }
                }
                return implode(',', $values);
            case self::VALIDATOR_INVENTORY_KEEPER_FIELDS:
            case self::VALIDATOR_INVENTORY_PROFILE_FIELDS:
                $items = new ItemsData($gDb, $gCurrentOrgId);
                $allowed = array();
                $itemPictureEnabled = array_key_exists('inventory_item_picture_enabled', $proposedValues)
                    ? $proposedValues['inventory_item_picture_enabled'] === '1'
                    : $gSettingsManager->getBool('inventory_item_picture_enabled');
                $borrowingDisabled = array_key_exists('inventory_items_disable_borrowing', $proposedValues)
                    ? $proposedValues['inventory_items_disable_borrowing'] === '1'
                    : $gSettingsManager->getBool('inventory_items_disable_borrowing');
                if ($validator === self::VALIDATOR_INVENTORY_KEEPER_FIELDS && $itemPictureEnabled) {
                    $allowed['ITEM_PICTURE'] = true;
                }
                foreach ($items->getItemFields() as $itemField) {
                    $field = (string)$itemField->getValue('inf_name_intern');
                    if ($validator === self::VALIDATOR_INVENTORY_PROFILE_FIELDS && $field === 'ITEMNAME') {
                        continue;
                    }
                    if ($borrowingDisabled && in_array($field, $items->borrowFieldNames, true)) {
                        continue;
                    }
                    $allowed[$field] = true;
                }
                $values = self::commaValues($value);
                foreach ($values as $field) {
                    if (!isset($allowed[$field])) {
                        throw new InvalidArgumentException('Preference "' . $name . '" references unavailable field ' . $field . '.');
                    }
                }
                return implode(',', $values);
            case self::VALIDATOR_SSO_KEY:
            case self::VALIDATOR_OIDC_SIGNING_KEY:
                if ($value === '' || $value === '0') {
                    return $value;
                }
                self::assertNumeric($value, $name);
                $sql = 'SELECT COUNT(*) FROM ' . TBL_SSO_KEYS . ' WHERE key_id = ?';
                $params = array((int)$value);
                if ($validator === self::VALIDATOR_OIDC_SIGNING_KEY) {
                    $sql .= ' AND key_algorithm LIKE ?';
                    $params[] = 'RSA%';
                }
                if ((int)$gDb->queryPrepared($sql, $params)->fetchColumn() !== 1) {
                    throw new InvalidArgumentException('Preference "' . $name . '" references an unavailable SSO key.');
                }
                return (string)(int)$value;
        }

        throw new InvalidArgumentException('No validator exists for preference "' . $name . '".');
    }

    private static function validateFileChoice(string $value, string $directory, string $label, bool $allowEmpty = true): string
    {
        if ($value === '' && $allowEmpty) {
            return '';
        }
        if ($value === '' || basename($value) !== $value || !is_file(rtrim($directory, '/') . '/' . $value)) {
            throw new InvalidArgumentException('The selected ' . $label . ' is not available.');
        }
        return $value;
    }

    /** @param array<int,mixed> $extraParams */
    private static function validateNumericReference(string $value, string $sql, array $extraParams, string $label): string
    {
        global $gDb;
        if ($value === '' || $value === '0') {
            return $value;
        }
        self::assertNumeric($value, $label);
        $params = array_merge(array((int)$value), $extraParams);
        if ((int)$gDb->queryPrepared($sql, $params)->fetchColumn() !== 1) {
            throw new InvalidArgumentException('The selected ' . $label . ' is not available.');
        }
        return (string)(int)$value;
    }

    private static function assertNumeric(string $value, string $label): void
    {
        if (!ctype_digit($value)) {
            throw new InvalidArgumentException('Preference/reference "' . $label . '" expects a numeric id.');
        }
    }

    /** @return array<int,string> */
    private static function commaValues(string $value): array
    {
        if (trim($value) === '') {
            return array();
        }
        $values = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $entry): bool => $entry !== ''));
        return array_values(array_unique($values));
    }
}
