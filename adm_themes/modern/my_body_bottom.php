
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
            <h3>'.$gL10n->get('SYS_MODULES').'</h3>
            <span class="menu" style="margin-bottom: 10px;"><a href="'. $g_root_path. '/adm_program/index.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/home.png" alt="'.$gL10n->get('SYS_OVERVIEW').'" title="'.$gL10n->get('SYS_OVERVIEW').'" /></a>
                <a href="'. $g_root_path. '/adm_program/index.php">'.$gL10n->get('SYS_OVERVIEW').'</a></span>';
            if( $gPreferences['enable_announcements_module'] == 1
            || ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/announcements.png" alt="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" title="'.$gL10n->get('ANN_ANNOUNCEMENTS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php">'.$gL10n->get('ANN_ANNOUNCEMENTS').'</a></span>';
            }
            if($gPreferences['enable_download_module'] == 1)
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/downloads/downloads.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('DOW_DOWNLOADS').'" title="'.$gL10n->get('DOW_DOWNLOADS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/downloads/downloads.php">'.$gL10n->get('DOW_DOWNLOADS').'</a></span>';
            }
            if($gPreferences['enable_mail_module'] == 1)
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/mail/mail.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get('SYS_EMAIL').'" title="'.$gL10n->get('SYS_EMAIL').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/mail/mail.php">'.$gL10n->get('SYS_EMAIL').'</a></span>';
            }
            if($gPreferences['enable_photo_module'] == 1 
            || ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/photos/photos.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/photo.png" alt="'.$gL10n->get('PHO_PHOTOS').'" title="'.$gL10n->get('PHO_PHOTOS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/photos/photos.php">'.$gL10n->get('PHO_PHOTOS').'</a></span>';
            }
            if( $gPreferences['enable_guestbook_module'] == 1
            || ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))            
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/guestbook/guestbook.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/guestbook.png" alt="'.$gL10n->get('GBO_GUESTBOOK').'" title="'.$gL10n->get('GBO_GUESTBOOK').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/guestbook/guestbook.php">'.$gL10n->get('GBO_GUESTBOOK').'</a></span>';
            }

            echo '
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/lists/lists.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/lists.png" alt="'.$gL10n->get('LST_LISTS').'" title="'.$gL10n->get('LST_LISTS').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/lists/lists.php">'.$gL10n->get('LST_LISTS').'</a></span>
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/lists/mylist.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/mylist.png" alt="'.$gL10n->get('LST_MY_LIST').'" title="'.$gL10n->get('LST_MY_LIST').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/lists/mylist.php">'.$gL10n->get('LST_MY_LIST').'</a></span>
            <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/profile/profile.php"><img
                style="vertical-align: middle;" src="'. THEME_PATH. '/icons/profile.png" alt="'.$gL10n->get('PRO_MY_PROFILE').'" title="'.$gL10n->get('PRO_MY_PROFILE').'" /></a>
                <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php">'.$gL10n->get('PRO_MY_PROFILE').'</a></span>';

            if( $gPreferences['enable_dates_module'] == 1
            || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))                    
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/dates/dates.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/dates.png" alt="'.$gL10n->get('DAT_DATES').'" title="'.$gL10n->get('DAT_DATES').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/dates/dates.php">'.$gL10n->get('DAT_DATES').'</a></span>';
            }
            if( $gPreferences['enable_weblinks_module'] == 1
            || ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))            
            {
                echo '
                <span class="menu"><a href="'. $g_root_path. '/adm_program/modules/links/links.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/weblinks.png" alt="'.$gL10n->get('LNK_WEBLINKS').'" title="'.$gL10n->get('LNK_WEBLINKS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/modules/links/links.php">'.$gL10n->get('LNK_WEBLINKS').'</a></span>';
            }
            
            if($gPreferences['enable_forum_interface'])
            {
                echo '<span class="menu"><a href="'. $gForum->url. '"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/forum.png" alt="'.$gL10n->get('SYS_FORUM').'" title="'.$gL10n->get('SYS_FORUM').'" /></a>
                    <a href="'. $gForum->url. '">'.$gL10n->get('SYS_FORUM').'</a></span>';
            }

            if($gCurrentUser->isWebmaster() || $gCurrentUser->assignRoles() || $gCurrentUser->approveUsers() || $gCurrentUser->editUsers())
            {
                echo '<h3>'.$gL10n->get('SYS_ADMINISTRATION').'</h3>';
                if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/new_user/new_user.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_NEW_REGISTRATIONS').'" title="'.$gL10n->get('NWU_NEW_REGISTRATIONS').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/new_user/new_user.php">'.$gL10n->get('NWU_NEW_REGISTRATIONS').'</a></span>';
                }
                if($gCurrentUser->editUsers())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/members/members.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/user_administration.png" alt="'.$gL10n->get('MEM_USER_MANAGEMENT').'" title="'.$gL10n->get('MEM_USER_MANAGEMENT').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/members/members.php">'.$gL10n->get('MEM_USER_MANAGEMENT').'</a></span>';
                }
                if($gCurrentUser->assignRoles())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/roles/roles.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/roles.png" alt="'.$gL10n->get('ROL_ROLE_ADMINISTRATION').'" title="'.$gL10n->get('ROL_ROLE_ADMINISTRATION').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/roles/roles.php">'.$gL10n->get('ROL_ROLE_ADMINISTRATION').'</a></span>';
                }
                if($gCurrentUser->isWebmaster())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/backup/backup.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/backup.png" alt="'.$gL10n->get('BAC_DATABASE_BACKUP').'" title="'.$gL10n->get('BAC_DATABASE_BACKUP').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/backup/backup.php">'.$gL10n->get('BAC_DATABASE_BACKUP').'</a></span>';
                }
                if($gCurrentUser->isWebmaster())
                {
                    echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/organization/organization.php"><img
                    style="vertical-align: middle;" src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('ORG_ORGANIZATION_PROPERTIES').'" title="'.$gL10n->get('ORG_ORGANIZATION_PROPERTIES').'" /></a>
                    <a href="'. $g_root_path. '/adm_program/administration/organization/organization.php">'.$gL10n->get('ORG_ORGANIZATION_PROPERTIES').'</a></span>';
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
     alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>" /></a>
    <span style="font-size: 9pt;">&nbsp;&nbsp;&copy; 2004 - 2012&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
