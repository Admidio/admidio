<?php
/******************************************************************************
 * System preferences for an organization
 *
 * IMPORTANT: If preferences should get other values with an update,
 *            then you must set these values for every organization
 *            in the update scripts
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$orga_preferences = array(

    // System
    'enable_rss'                    => '1',
    'enable_auto_login'             => '1',
    'default_country'               => 'DEU',
    'logout_minutes'                => '20',
    'homepage_logout'               => 'adm_program/index.php',
    'homepage_login'                => 'adm_program/index.php',
    'theme'                         => 'modern',
    'enable_password_recovery'      => '1',
    'system_show_create_edit'       => '1',
    'system_currency'               => '€',
    'system_date'                   => 'd.m.Y',
    'system_js_editor_enabled'      => '1',
    'system_js_editor_color'        => '#96c4cb',
    'system_language'               => 'de',
    'system_organization_select'    => '0',
    'system_search_similar'         => '1',
    'system_time'                   => 'H:i',
    'system_browser_update_check'   => '0',
    'system_hashing_cost'           => '10',

    // Registration
    'registration_mode'              => '1',
    'enable_registration_captcha'    => '1',
    'enable_registration_admin_mail' => '1',

    // E-mail dispatch
    'mail_send_method'              => 'phpmail',
    'mail_bcc_count'                => '50',
    'mail_sender_into_to'           => '0',
    'mail_character_encoding'       => 'utf-8',
    'mail_smtp_host'                => '',
    'mail_smtp_auth'                => '1',
    'mail_smtp_port'                => '25',
    'mail_smtp_secure'              => '',
    'mail_smtp_authentication_type' => 'LOGIN',
    'mail_smtp_user'                => '',
    'mail_smtp_password'            => '',

    // System notifications
    'enable_system_mails'       => '1',
    'email_administrator'       => 'webmaster@'. $_SERVER['HTTP_HOST'],
    'enable_email_notification' => '0',

    // Captcha
    'captcha_background_color'    => '#FFEFC4',
    'captcha_font_size'           => '20',
    'captcha_fonts'               => 'Theme',
    'captcha_width'               => '250',
    'captcha_height'              => '60',
    'captcha_signs'               => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
    'captcha_signature'           => 'POWERED  BY   A D M I D I O . O R G',
    'captcha_signature_font_size' => '13',
    'captcha_type'                => 'pic',

    // Announcements
    'enable_announcements_module' => '1',
    'announcements_per_page'      => '10',

    // User management
    'members_users_per_page'     => '25',
    'members_days_field_history' => '365',
    'members_show_all_users'     => '1',

    // Downloads
    'enable_download_module' => '1',
    'max_file_upload_size'   => '3',

    // Photos
    'enable_photo_module'    => '1',
    'photo_show_mode'        => '1',
    'photo_albums_per_page'  => '10',
    'photo_save_scale'       => '640',
    'photo_thumbs_page'      => '16',
    'photo_thumbs_scale'     => '160',
    'photo_show_width'       => '640',
    'photo_show_height'      => '400',
    'photo_image_text'       => '© '.$_SERVER['HTTP_HOST'],
    'photo_keep_original'    => '0',
    'photo_download_enabled' => '0',

    // Guestbook
    'enable_guestbook_module'           => '1',
    'guestbook_entries_per_page'        => '10',
    'enable_guestbook_captcha'          => '1',
    'flooding_protection_time'          => '60',
    'enable_gbook_comments4all'         => '0',
    'enable_intial_comments_loading'    => '0',
    'enable_guestbook_moderation'       => '0',

    // Lists
    'lists_roles_per_page'        => '10',
    'lists_members_per_page'      => '25',
    'lists_hide_overview_details' => '0',
    'lists_default_configuation'  => '',

    // Messages
    'enable_mail_module'          => '1',
    'enable_pm_module'            => '1',
    'enable_chat_module'          => '0',
    'enable_mail_captcha'         => '1',
    'mail_max_receiver'           => '10',
    'mail_show_former'            => '1',
    'mail_into_to'                => '0',
    'max_email_attachment_size'   => '1',
    'mail_sendmail_address'       => '',
    'mail_sendmail_name'          => '',
    'mail_html_registered_users'  => '1',
    'mail_delivery_confirmation'  => '0',

    // E-Cards
    'enable_ecard_module'         => '1',
    'ecard_thumbs_scale'          => '250',
    'ecard_card_picture_width'    => '400',
    'ecard_card_picture_height'   => '250',
    'ecard_template'              => 'postcard.tpl',

    // Profile
    'profile_log_edit_fields'   => '1',
    'profile_show_map_link'     => '1',
    'profile_show_roles'        => '1',
    'profile_show_former_roles' => '1',
    'profile_show_extern_roles' => '1',
    'profile_photo_storage'     => '0',

    // Events
    'enable_dates_module'        => '1',
    'dates_per_page'             => '10',
    'dates_viewmode'             => 'html',
    'dates_show_map_link'        => '1',
    'dates_show_rooms'           => '0',
    'enable_dates_ical'          => '1',
    'dates_ical_days_past'       => '30',
    'dates_ical_days_future'     => '365',

    // Weblinks
    'enable_weblinks_module'    => '1',
    'weblinks_per_page'         => '0',
    'weblinks_redirect_seconds' => '10',
    'weblinks_target'           => '_blank',

    // Inventory
    'enable_inventory_module'   => '0'
 );
