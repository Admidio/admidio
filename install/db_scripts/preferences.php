<?php
/**
 ***********************************************************************************************
 * System preferences for an organization
 *
 * IMPORTANT: If preferences should get other values with an update,
 *            then you must set these values for every organization
 *            in the update scripts
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$defaultOrgPreferences = array(
    // System
    'enable_rss'                     => '1',
    'enable_auto_login'              => '1',
    'security_login_email_address_enabled' => '0',
    'default_country'                => 'DEU',
    'logout_minutes'                 => '20',
    'homepage_logout'                => 'adm_program/overview.php',
    'homepage_login'                 => 'adm_program/overview.php',
    'theme'                          => 'simple',
    'enable_password_recovery'       => '1',
    'enable_two_factor_authentication' => '0',
    'system_browser_update_check'    => '0',
    'system_cookie_note'             => '1',
    'system_currency'                => '€',
    'system_date'                    => 'd.m.Y',
    'system_hashing_cost'            => '10',
    'system_js_editor_enabled'       => '1',
    'system_language'                => 'de',
    'system_search_similar'          => '1',
    'system_show_create_edit'        => '1',
    'system_time'                    => 'H:i',
    'system_url_imprint'             => '',
    'system_url_data_protection'     => '',
    'password_min_strength'          => '1',

    // Registration
    'registration_adopt_all_data'          => '1',
    'registration_enable_captcha'          => '1',
    'registration_enable_module'           => '1',
    'registration_manual_approval'         => '1',
    'registration_send_notification_email' => '1',

    // Changelog settings
    'changelog_module_enabled'             => '1',
    'changelog_table_user_data'            => '1',
    'changelog_table_users'                => '1',
    'changelog_table_members'              => '1',
    'changelog_table_user_fields'          => '0',
    'changelog_table_announcements'        => '0',
    'changelog_table_events'               => '0',
    'changelog_table_rooms'                => '0',
    'changelog_table_roles'                => '0',
    'changelog_table_role_dependencies'    => '0',
    'changelog_table_roles_rights'         => '0',
    'changelog_table_roles_rights_data'    => '0',
    'changelog_table_categories'           => '0',
    'changelog_table_category_report'      => '0',
    'changelog_table_links'                => '0',
    'changelog_table_folders'              => '0',
    'changelog_table_files'                => '0',
    'changelog_table_organizations'        => '0',
    'changelog_table_menu'                 => '0',
    'changelog_table_user_relation_types'  => '0',
    'changelog_table_user_relations'       => '1',
    'changelog_table_photos'               => '0',
    'changelog_table_lists'                => '0',
    'changelog_table_list_columns'         => '0',
    'changelog_table_preferences'          => '0',
    'changelog_table_texts'                => '0',
    'changelog_table_forum_topics'         => '0',
    'changelog_table_forum_posts'          => '0',
    'changelog_table_others'               => '0',

    // E-mail dispatch
    'mail_send_method'               => 'phpmail',
    'mail_sending_mode'              => '0',
    'mail_recipients_with_roles'     => '1',
    'mail_number_recipients'         => '50',
    'mail_into_to'                   => '0',
    'mail_character_encoding'        => 'utf-8',
    'mail_smtp_host'                 => '',
    'mail_smtp_auth'                 => '1',
    'mail_smtp_port'                 => '587',
    'mail_smtp_secure'               => 'tls',
    'mail_smtp_authentication_type'  => '',
    'mail_smtp_user'                 => '',
    'mail_smtp_password'             => '',

    // System notifications
    'system_notifications_enabled'         => '1',
    'system_notifications_role'            => '',
    'system_notifications_new_entries'     => '0',
    'system_notifications_profile_changes' => '0',

    // Captcha
    'captcha_type'                => 'pic',
    'captcha_fonts'               => 'AHGBold.ttf',
    'captcha_width'               => '215',
    'captcha_lines_numbers'       => '5',
    'captcha_perturbation'        => '0.75',
    'captcha_background_image'    => '',
    'captcha_background_color'    => '#B6D6DB',
    'captcha_text_color'          => '#707070',
    'captcha_line_color'          => '#707070',
    'captcha_charset'             => '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxy',
    'captcha_signature'           => 'Powered by Admidio.org',

    // Announcements
    'announcements_module_enabled' => '1',
    'announcements_per_page'       => '10',

    // Category-Report
    'category_report_enable_module'         => '1',
    'category_report_default_configuration' => '',

    // Contacts
    'contacts_field_history_days'     => '365',
    'contacts_list_configuration'     => '',
    'contacts_per_page'               => '25',
    'contacts_show_all'               => '1',
    'contacts_user_relations_enabled' => '1',

    // Documents and files
    'documents_files_module_enabled'  => '1',
    'documents_files_max_upload_size' => '3',

    // Events
    'events_list_configuration'   => '',
    'events_ical_export_enabled'  => '1',
    'events_may_take_part'        => '0',
    'events_module_enabled'       => '1',
    'events_per_page'             => '10',
    'events_rooms_enabled'        => '0',
    'events_save_cancellations'   => '1',
    'events_show_map_link'        => '1',
    'events_view'                 => 'detail',

    // Forum
    'forum_module_enabled'        => '1',
    'forum_posts_per_page'        => '15',
    'forum_topics_per_page'       => '10',
    'forum_view'                  => 'cards',

    // Groups and roles
    'groups_roles_default_configuration' => '',
    'groups_roles_enable_module'         => '1',
    'groups_roles_export'                => '1',
    'groups_roles_edit_lists'            => '1',
    'groups_roles_members_per_page'      => '25',
    'groups_roles_show_former_members'   => '2',

    // Messages
    'enable_mail_module'          => '1',
    'enable_pm_module'            => '1',
    'enable_mail_captcha'         => '1',
    'mail_delivery_confirmation'  => '0',
    'mail_html_registered_users'  => '1',
    'mail_max_receiver'           => '10',
    'mail_save_attachments'       => '1',
    'mail_send_to_all_addresses'  => '1',
    'mail_sendmail_address'       => '',
    'mail_sendmail_name'          => '',
    'mail_show_former'            => '1',
    'mail_template'               => 'default.html',
    'max_email_attachment_size'   => '1',

    // Photos
    'photo_albums_per_page'  => '24',
    'photo_download_enabled' => '0',
    'photo_ecard_enabled'    => '1',
    'photo_ecard_scale'      => '500',
    'photo_ecard_template'   => 'postcard.tpl',
    'photo_image_text'       => '© '.DOMAIN,
    'photo_image_text_size'  => '40',
    'photo_keep_original'    => '0',
    'photo_module_enabled'   => '1',
    'photo_show_width'       => '1200',
    'photo_show_height'      => '1200',
    'photo_show_mode'        => '1',
    'photo_thumbs_page'      => '24',
    'photo_thumbs_scale'     => '500',

    // Profile
    'profile_show_map_link'     => '0',
    'profile_show_roles'        => '1',
    'profile_show_former_roles' => '1',
    'profile_show_extern_roles' => '1',
    'profile_photo_storage'     => '0',

    // Weblinks
    'enable_weblinks_module'    => '1',
    'weblinks_per_page'         => '0',
    'weblinks_redirect_seconds' => '10',
    'weblinks_target'           => '_blank'
);
