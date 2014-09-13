
<!-- Here you can add your html code. This code will be applied at the end of the <body> area
     and after the Admidio module code.
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
			
			$moduleMenu->addItem('overview', '/adm_program/index.php',
								$gL10n->get('SYS_OVERVIEW'), '/icons/home.png');
			
            if( $gPreferences['enable_announcements_module'] == 1
            || ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
            {
				$moduleMenu->addItem('announcements', '/adm_program/modules/announcements/announcements.php',
									$gL10n->get('ANN_ANNOUNCEMENTS'), '/icons/announcements.png');
			}
			if($gPreferences['enable_download_module'] == 1)
			{
				$moduleMenu->addItem('download', '/adm_program/modules/downloads/downloads.php',
									$gL10n->get('DOW_DOWNLOADS'), '/icons/download.png');
			}
			if($gPreferences['enable_mail_module'] == 1)
			{
				$moduleMenu->addItem('email', '/adm_program/modules/messages/messages_write.php',
									$gL10n->get('SYS_EMAIL'), '/icons/email.png');
			}
            if($gPreferences['enable_photo_module'] == 1 
            || ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
            {
				$moduleMenu->addItem('photo', '/adm_program/modules/photos/photos.php',
									$gL10n->get('PHO_PHOTOS'), '/icons/photo.png');
			}
            if( $gPreferences['enable_guestbook_module'] == 1
            || ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))            
            {
				$moduleMenu->addItem('guestbk', '/adm_program/modules/guestbook/guestbook.php',
									$gL10n->get('GBO_GUESTBOOK'), '/icons/guestbook.png');
            }
			
			$moduleMenu->addItem('lists', '/adm_program/modules/lists/lists.php',
								$gL10n->get('LST_LISTS'), '/icons/lists.png');
			$moduleMenu->addItem('mylist', '/adm_program/modules/lists/mylist.php',
								$gL10n->get('LST_MY_LIST'), '/icons/mylist.png');
			$moduleMenu->addItem('profile', '/adm_program/modules/profile/profile.php',
								$gL10n->get('PRO_MY_PROFILE'), '/icons/profile.png');
			
            if( $gPreferences['enable_dates_module'] == 1
            || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))                    
            {
				$moduleMenu->addItem('dates', '/adm_program/modules/dates/dates.php',
									$gL10n->get('DAT_DATES'), '/icons/dates.png');
            }

            if( $gPreferences['enable_weblinks_module'] == 1
            || ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))            
            {
				$moduleMenu->addItem('links', '/adm_program/modules/links/links.php',
									$gL10n->get('LNK_WEBLINKS'), '/icons/weblinks.png');
            }

            if($gCurrentUser ->isWebmaster() || $gCurrentUser ->manageRoles() || $gCurrentUser ->approveUsers() || $gCurrentUser ->editUsers())
            {
				
				if($gCurrentUser ->approveUsers() && $gPreferences['registration_mode'] > 0)
                {
					$adminMenu->addItem('newreg', '/adm_program/modules/registration/new_user.php',
										$gL10n->get('NWU_NEW_REGISTRATIONS'), '/icons/new_registrations.png');
                }
                if($gCurrentUser ->editUsers())
                {
					$adminMenu->addItem('usrmgt', '/adm_program/modules/members/members.php',
										$gL10n->get('MEM_USER_MANAGEMENT'), '/icons/user_administration.png');
                }
                if($gCurrentUser ->manageRoles())
                {
					$adminMenu->addItem('roladm', '/adm_program/modules/roles/roles.php',
										$gL10n->get('ROL_ROLE_ADMINISTRATION'), '/icons/roles.png');
                }
                if($gCurrentUser ->isWebmaster())
                {
					$adminMenu->addItem('dbback', '/adm_program/modules/backup/backup.php',
										$gL10n->get('BAC_DATABASE_BACKUP'), '/icons/backup.png');
					$adminMenu->addItem('orgprop', '/adm_program/modules/preferences/organization.php',
										$gL10n->get('ORG_ORGANIZATION_PROPERTIES'), '/icons/options.png');
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
    <span style="font-size: 9pt;">&nbsp;&nbsp;&copy; 2004 - 2013&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
