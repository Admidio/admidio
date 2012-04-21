
<!-- Here you can add your html code. this code will be applied at the end of the <body> area
     and after the Admidio module code
-->

        &nbsp;</div>
        <div id="bottom_border_img_big"></div>
    </div>
    <div id="right_block">
        <div id="top_border_img_small"></div>
        <div id="sidebar" class="content">
            <?php
  
            include(SERVER_PATH. '/adm_plugins/login_form/login_form.php');
			            
			// prepare the menus here so that the plugins have a chance to modify them
			$moduleMenu = new Menu('modules', $gL10n->get('SYS_MODULES'));
			$adminMenu = new Menu('administration', $gL10n->get('SYS_ADMINISTRATION'));
			
			$moduleMenu->addItem('overview', $g_root_path. '/adm_program/index.php',
								$gL10n->get('SYS_OVERVIEW'), THEME_PATH. '/icons/home.png'); // ging verloren: style="margin-bottom: 10px;
			
            if( $gPreferences['enable_announcements_module'] == 1
            || ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
            {
				$moduleMenu->addItem('announcements', $g_root_path. '/adm_program/modules/announcements/announcements.php',
									$gL10n->get('ANN_ANNOUNCEMENTS'), THEME_PATH. '/icons/announcements.png');
			}
			if($gPreferences['enable_download_module'] == 1)
			{
				$moduleMenu->addItem('download', $g_root_path. '/adm_program/modules/downloads/downloads.php',
									$gL10n->get('DOW_DOWNLOADS'), THEME_PATH. '/icons/download.png');
			}
			if($gPreferences['enable_mail_module'] == 1)
			{
				$moduleMenu->addItem('email', $g_root_path. '/adm_program/modules/mail/mail.php',
									$gL10n->get('SYS_EMAIL'), THEME_PATH. '/icons/email.png');
			}
            if($gPreferences['enable_photo_module'] == 1 
            || ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
            {
				$moduleMenu->addItem('photo', $g_root_path. '/adm_program/modules/photos/photos.php',
									$gL10n->get('PHO_PHOTOS'), THEME_PATH. '/icons/photo.png');
			}
            if( $gPreferences['enable_guestbook_module'] == 1
            || ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))            
            {
				$moduleMenu->addItem('guestbk', $g_root_path. '/adm_program/modules/guestbook/guestbook.php',
									$gL10n->get('GBO_GUESTBOOK'), THEME_PATH. '/icons/guestbook.png');
            }
			
			$moduleMenu->addItem('lists', $g_root_path. '/adm_program/modules/lists/lists.php',
								$gL10n->get('LST_LISTS'), THEME_PATH. '/icons/lists.png');
			$moduleMenu->addItem('mylist', $g_root_path. '/adm_program/modules/lists/mylist.php',
								$gL10n->get('LST_MY_LIST'), THEME_PATH. '/icons/mylist.png');
			$moduleMenu->addItem('profile', $g_root_path. '/adm_program/modules/profile/profile.php',
								$gL10n->get('PRO_MY_PROFILE'), THEME_PATH. '/icons/profile.png');
			
            if( $gPreferences['enable_dates_module'] == 1
            || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))                    
            {
				$moduleMenu->addItem('dates', $g_root_path. '/adm_program/modules/dates/dates.php',
									$gL10n->get('DAT_DATES'), THEME_PATH. '/icons/dates.png');
            }

            if( $gPreferences['enable_weblinks_module'] == 1
            || ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))            
            {
				$moduleMenu->addItem('links', $g_root_path. '/adm_program/modules/links/links.php',
									$gL10n->get('LNK_WEBLINKS'), THEME_PATH. '/icons/weblinks.png');
            }

            if($gPreferences['enable_forum_interface'])
            {
				$moduleMenu->addItem('forum', $g_forum->url,
									$gL10n->get('SYS_FORUM'), THEME_PATH. '/icons/forum.png');
            }

            if($gCurrentUser ->isWebmaster() || $gCurrentUser ->assignRoles() || $gCurrentUser ->approveUsers() || $gCurrentUser ->editUsers())
            {
				
				if($gCurrentUser ->approveUsers() && $gPreferences['registration_mode'] > 0)
                {
					$adminMenu->addItem('newreg', $g_root_path. '/adm_program/administration/new_user/new_user.php',
										$gL10n->get('NWU_NEW_REGISTRATIONS'), THEME_PATH. '/icons/new_registrations.png');
                }
                if($gCurrentUser ->editUsers())
                {
					$adminMenu->addItem('usrmgt', $g_root_path. '/adm_program/administration/members/members.php',
										$gL10n->get('MEM_USER_MANAGEMENT'), THEME_PATH. '/icons/user_administration.png');
                }
                if($gCurrentUser ->assignRoles())
                {
					$adminMenu->addItem('roladm', $g_root_path. '/adm_program/administration/roles/roles.php',
										$gL10n->get('ROL_ROLE_ADMINISTRATION'), THEME_PATH. '/icons/roles.png');
                }
                if($gCurrentUser ->isWebmaster())
                {
					$adminMenu->addItem('dbback', $g_root_path. '/adm_program/administration/backup/backup.php',
										$gL10n->get('BAC_DATABASE_BACKUP'), THEME_PATH. '/icons/backup.png');
					$adminMenu->addItem('orgprop', $g_root_path. '/adm_program/administration/organization/organization.php',
										$gL10n->get('ORG_ORGANIZATION_PROPERTIES'), THEME_PATH. '/icons/options.png');
                }
            }
			
			$moduleMenu->show();
            $adminMenu->show();
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
