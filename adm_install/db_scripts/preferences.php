<?php
/******************************************************************************
 * Systemeinstellungen fuer eine Organisation
 *
 * WICHTIG: Neue Einstellungen muessen auch im jeweiligen Updatescript 
 *          eingetragen werden !!!
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

$orga_preferences = array(

    // System
    'enable_rss'     => '1',
    'enable_bbcode'  => '1',
    'logout_minutes' => '20',
    'enable_system_mails' => '1',
    'email_administrator' => 'webmaster@'. $_SERVER['HTTP_HOST'],
    
    // Registrierung
    'registration_mode' => '1',
    'enable_registration_captcha'    => '1',
    'enable_registration_admin_mail' => '1',
    
    // Mailmodul
    'enable_mail_module'  => '1',
    'max_email_attachment_size' => '1024',
    'enable_mail_captcha' => '1',
    
    // Fotomodul
    'enable_photo_module' => '1',
    'photo_save_scale'    => '640',
    'photo_thumbs_column' => '5',
    'photo_thumbs_row'    => '5',
    'photo_thumbs_scale'  => '100',
    'photo_show_width'    => '500',
    'photo_show_height'   => '380',
    'photo_image_text'    => '1',
    'photo_preview_scale' => '100',
    
    // Gaestebuch
    'enable_guestbook_module'   => '1',
    'enable_guestbook_captcha'  => '1',
    'flooding_protection_time'  => '60',
    'enable_gbook_comments4all' => '0',
    
    // Weblinks
    'enable_weblinks_module' => '1',
    
    // Downloads
    'enable_download_module' => '1',
    'max_file_upload_size'   => '3072',
    
    // Ankuendigungen
    'enable_announcements_module' => '1',
    
    // Termine
    'enable_dates_module' => '1'
 )
?>
