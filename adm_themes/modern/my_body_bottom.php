
<!-- Hier koennen Sie Ihren HTML-Code einbauen, der am Ende des <body> Bereichs
     einer Admidio-Modul-Seite erscheinen soll.
-->

        &nbsp;</div>
        <div id="bottom_border_img_big"></div>
    </div>
    <div id="right_block">
        <div id="top_border_img_small"></div>
        <div id="sidebar" class="content">
            <?php
            include(SERVER_PATH. '/adm_plugins/login_form/login_form.php');

            echo '<br/>
            <h3>Module</h3>
            <span class="menu" style="margin-bottom: 10px;"><a href="'. $g_root_path. '/adm_program/index.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/home.png" alt="'.$g_l10n->get('SYS_OVERVIEW').'" title="'.$g_l10n->get('SYS_OVERVIEW').'" /></a>
                <a href="'. $g_root_path. '/adm_program/index.php">'.$g_l10n->get('SYS_OVERVIEW').'</a></span>';
            if( $g_preferences['enable_announcements_module'] == 1
            || ($g_preferences['enable_announcements_module'] == 2 && $g_valid_login))
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/announcements.png" alt="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" title="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php">'.$g_l10n->get('ANN_ANNOUNCEMENTS').'</a></span>';
            }
            if($g_preferences['enable_download_module'] == 1)
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/downloads/downloads.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/download.png" alt="'.$g_l10n->get('DOW_DOWNLOADS').'" title="'.$g_l10n->get('DOW_DOWNLOADS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/downloads/downloads.php">'.$g_l10n->get('DOW_DOWNLOADS').'</a></span>';
            }
            if($g_preferences['enable_mail_module'] == 1)
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/mail/mail.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/email.png" alt="'.$g_l10n->get('SYS_EMAIL').'" title="'.$g_l10n->get('SYS_EMAIL').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/mail/mail.php">'.$g_l10n->get('SYS_EMAIL').'</a></span>';
            }
            if($g_preferences['enable_photo_module'] == 1 
            || ($g_preferences['enable_photo_module'] == 2 && $g_valid_login))
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/photos/photos.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/photo.png" alt="'.$g_l10n->get('PHO_PHOTOS').'" title="'.$g_l10n->get('PHO_PHOTOS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/photos/photos.php">'.$g_l10n->get('PHO_PHOTOS').'</a></span>';
            }
            if( $g_preferences['enable_guestbook_module'] == 1
            || ($g_preferences['enable_guestbook_module'] == 2 && $g_valid_login))            
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/guestbook/guestbook.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$g_l10n->get('GBO_GUESTBOOK').'" title="'.$g_l10n->get('GBO_GUESTBOOK').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/guestbook/guestbook.php">'.$g_l10n->get('GBO_GUESTBOOK').'</a></span>';
            }

            echo '
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/lists/lists.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/lists.png" alt="'.$g_l10n->get('LST_LISTS').'" title="'.$g_l10n->get('LST_LISTS').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/lists/lists.php">'.$g_l10n->get('LST_LISTS').'</a></span>
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/lists/mylist.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/mylist.png" alt="'.$g_l10n->get('LST_MY_LIST').'" title="'.$g_l10n->get('LST_MY_LIST').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/lists/mylist.php">'.$g_l10n->get('LST_MY_LIST').'</a></span>
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/profile/profile.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/profile.png" alt="'.$g_l10n->get('PRO_MY_PROFILE').'" title="'.$g_l10n->get('PRO_MY_PROFILE').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php">'.$g_l10n->get('PRO_MY_PROFILE').'</a></span>';

            if( $g_preferences['enable_dates_module'] == 1
            || ($g_preferences['enable_dates_module'] == 2 && $g_valid_login))                    
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/dates/dates.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/dates.png" alt="'.$g_l10n->get('DAT_DATES').'" title="'.$g_l10n->get('DAT_DATES').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/dates/dates.php">'.$g_l10n->get('DAT_DATES').'</a></span>';
            }
            if( $g_preferences['enable_weblinks_module'] == 1
            || ($g_preferences['enable_weblinks_module'] == 2 && $g_valid_login))            
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/links/links.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/weblinks.png" alt="'.$g_l10n->get('LNK_WEBLINKS').'" title="'.$g_l10n->get('LNK_WEBLINKS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/links/links.php">'.$g_l10n->get('LNK_WEBLINKS').'</a></span>';
            }
            
            if($g_preferences['enable_forum_interface'])
            {
                echo '<span class="menu"><a href="'. $g_forum->url. '"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/forum.png" alt="'.$g_l10n->get('SYS_FORUM').'" title="'.$g_l10n->get('SYS_FORUM').'" /></a>
                    <a href="'. $g_forum->url. '">'.$g_l10n->get('SYS_FORUM').'</a></span>';
            }

            if($g_current_user->isWebmaster() || $g_current_user->assignRoles() || $g_current_user->approveUsers() || $g_current_user->editUsers())
            {
                echo '<h3>Administration</h3>';
                if($g_current_user->approveUsers() && $g_preferences['registration_mode'] > 0)
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/new_user/new_user.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$g_l10n->get('NWU_NEW_REGISTRATIONS').'" title="'.$g_l10n->get('NWU_NEW_REGISTRATIONS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/new_user/new_user.php">'.$g_l10n->get('NWU_NEW_REGISTRATIONS').'</a></span>';
                }
                if($g_current_user->editUsers())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/members/members.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/user_administration.png" alt="'.$g_l10n->get('MEM_USER_MANAGEMENT').'" title="'.$g_l10n->get('MEM_USER_MANAGEMENT').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/members/members.php">'.$g_l10n->get('MEM_USER_MANAGEMENT').'</a></span>';
                }
                if($g_current_user->assignRoles())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/roles/roles.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/roles.png" alt="'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'" title="'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/roles/roles.php">'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'</a></span>';
                }
                if($g_current_user->isWebmaster())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/backup/backup.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/backup.png" alt="'.$g_l10n->get('BAC_DATABASE_BACKUP').'" title="'.$g_l10n->get('BAC_DATABASE_BACKUP').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/backup/backup.php">'.$g_l10n->get('BAC_DATABASE_BACKUP').'</a></span>';
                }
                if($g_current_user->isWebmaster())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/organization/organization.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/options.png" alt="'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'" title="'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/organization/organization.php">'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'</a></span>';
                }

            }
            
            ?>
        </div>
        <div id="bottom_border_img_small"></div>

        <div style="clear: both;"></div>
    </div>
</div>

<p>
    <a href="http://www.admidio.org"><img
    src="<?php echo THEME_PATH; ?>/images/admidio_logo_20.png" style="border: 0px; vertical-align: bottom;"
     alt="<?php echo $g_l10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" title="<?php echo $g_l10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" /></a>
    <span style="font-size: 9pt;">&nbsp;&nbsp;&copy; 2004 - 2011&nbsp;&nbsp;<?php echo $g_l10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
