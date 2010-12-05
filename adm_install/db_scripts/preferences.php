<?php
/******************************************************************************
 * Systemeinstellungen fuer eine Organisation
 *
 * WICHTIG: Sollen neue Einstellungen bei einem Update andere Werte erhalten,
 *          so muessen diese im Updatescript fuer jede Organisation
 *          eingetragen werden !!!
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$orga_preferences = array(

    // System
    'enable_rss'                    => '1',
    'enable_bbcode'                 => '1',
    'enable_auto_login'             => '1',
    'logout_minutes'                => '20',
    'homepage_logout'               => 'adm_program/index.php',
    'homepage_login'                => 'adm_program/index.php',
    'theme'                         => 'modern',
    'enable_password_recovery'      => '1',
    'system_language'               => 'de',
    'system_date'                   => 'd.m.Y',
    'system_time'                   => 'H:i',
    'system_currency'               => '€',
    'system_search_similar'         => '1',

    // Registrierung
    'registration_mode'              => '1',
    'enable_registration_captcha'    => '1',
    'enable_registration_admin_mail' => '1',

    // Ankuendigungen
    'enable_announcements_module' => '1',
    'announcements_per_page'      => '10',

    // Downloads
    'enable_download_module' => '1',
    'max_file_upload_size'   => '3072',

    // Fotomodul
    'enable_photo_module' => '1',
    'photo_save_scale'    => '640',
    'photo_thumbs_column' => '3',
    'photo_thumbs_row'    => '5',
    'photo_thumbs_scale'  => '160',
    'photo_show_width'    => '640',
    'photo_show_height'   => '400',
    'photo_image_text'    => '1',
    'photo_show_mode'     => '1',
    'photo_upload_mode'   => '1',
    'photo_image_text'    => '© '.$_SERVER['HTTP_HOST'],

    // Forum
    'enable_forum_interface'=> '0',
    'forum_version'         => 'phpbb2',
    'forum_export_user'     => '1',
    'forum_praefix'         => 'phpbb',
    'forum_sqldata_from_admidio'        => '0',
    'forum_db'              => '',
    'forum_srv'             => '',
    'forum_usr'             => '',
    'forum_pw'              => '',
    'forum_set_admin'       => '1',
    'forum_link_intern'     => '1',
    'forum_width'           => '570',

    // Gaestebuch
    'enable_guestbook_module'           => '1',
    'guestbook_entries_per_page'        => '10',
    'enable_guestbook_captcha'          => '1',
    'flooding_protection_time'          => '60',
    'enable_gbook_comments4all'         => '0',
    'enable_intial_comments_loading'    => '0',

    // Listen
    'lists_roles_per_page'   => '10',
    'lists_members_per_page' => '20',
    'lists_hide_overview_details' => '0',

    // Mailmodul
    'enable_mail_module'        => '1',
    'max_email_attachment_size' => '1024',
	'mail_bcc_count'			=> '50',
    'enable_mail_captcha'       => '1',
    'mail_sendmail_address'     => '',

    // Systemmail
    'enable_system_mails'       => '1',
    'email_administrator'           => 'webmaster@'. $_SERVER['HTTP_HOST'],
	'enable_email_notification'		=> '0',

    // Grußkartenmodul
    'enable_ecard_module'           => '1',
    'enable_ecard_text_length'      => '1',
    'enable_ecard_cc_recipients'    => '1',
    'ecard_view_width'              => '250',
    'ecard_view_height'             => '250',
    'ecard_card_picture_width'      => '400',
    'ecard_card_picture_height'     => '250',
    'ecard_cc_recipients'           => '5',
    'ecard_text_length'             => '500',
    'ecard_text_font'               => 'Comic Sans MS',
    'ecard_text_size'               => '20',
    'ecard_text_color'              => 'black',
    'ecard_template'                => 'brief_standard.tpl',

    // Profil
    'default_country'           => 'Deutschland',
    'profile_show_map_link'     => '1',
    'profile_show_roles'        => '1',
    'profile_show_former_roles' => '1',
    'profile_show_extern_roles' => '1',
    'profile_photo_storage'		=> '0',
    'profile_default_role'      => '0',

    // Termine
    'enable_dates_module'        => '1',
    'dates_per_page'             => '10',
    'dates_show_map_link'        => '1',
    'dates_show_calendar_select' => '1',
    'dates_show_rooms'           => '0',

    // Weblinks
    'enable_weblinks_module'    => '1',
    'weblinks_per_page' 		=> '0',
	'weblinks_redirect_seconds'	=> '10',
	'weblinks_target'			=> '_blank',
	
	// Captcha
    'captcha_background_color'	=> '#FFEFC4',
    'captcha_text_size' 		=> '20',
	'captcha_fonts'				=> 'Theme',
	'captcha_width'				=> '250',
    'captcha_height'			=> '60',
    'captcha_signs' 			=> '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
	'captcha_signature'			=> 'POWERED  BY   A D M I D I O . O R G',
	'captcha_signature_font_size' => '9',
	'captcha_type'				=> 'pic'	
 );
?>