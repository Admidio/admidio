
<!-- Hier koennen Sie Ihren HTML-Code einbauen, der am Ende des <body> Bereichs
     einer Admidio-Modul-Seite erscheinen soll.
-->

		&nbsp;</div>
		<div><img class="img_border" src="<?php echo THEME_PATH; ?>/images/border_bottom_big.png" alt="border" /></div>
	</div>
	<div id="right_block">
		<div><img class="img_border" src="<?php echo THEME_PATH; ?>/images/border_top_small.png" alt="border" /></div>
		<div id="sidebar" class="content">
			<?php
			if($g_valid_login)
			{
				echo "<h3>Angemeldet als</h3>";
			}
			else
			{
				echo "<h3>Anmelden</h3>";
			}
			include(SERVER_PATH. "/adm_plugins/login_form/login_form.php"); ?>
			
			<br />
			<h3>Module</h3>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/announcements/announcements.php">Ankündigungen</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/download/download.php">Downloads</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/mail/mail.php">E-Mail</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/photos/photos.php">Fotos</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/guestbook/guestbook.php">Gästebuch</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/lists/lists.php">Listen</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/lists/mylist.php">Eigene Liste</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/profile/profile.php">Profil</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/dates/dates.php">Termine</a></span>
            <span class="menu"><a href="<?php echo $g_root_path; ?>/adm_program/modules/links/links.php">Weblinks</a></span>
            
			<?php            
            if($g_current_user->isWebmaster() || $g_current_user->assignRoles() || $g_current_user->approveUsers() || $g_current_user->editUser())
            {
            	echo '<h3>Administration</h3>';
            	if($g_current_user->editUser())
					echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/new_user/new_user.php">Neue Anmeldungen</a></span>';
				if($g_current_user->approveUsers())
					echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/members/members.php">Benutzerverwaltung</a></span>';
				if($g_current_user->assignRoles())
					echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/roles/roles.php">Rollenverwaltung</a></span>';
				if($g_current_user->isWebmaster())
					echo '<span class="menu"><a href="'. $g_root_path. '/adm_program/administration/organization/organization.php">Organisationseinstellungen</a></span>';
            }
            ?>
		</div>
		<div><img class="img_border" src="<?php echo THEME_PATH; ?>/images/border_bottom_small.png" alt="border" /></div>
	</div>
</div>

<div style="clear: left;">&nbsp;</div>

<p>
	<a href="http://www.admidio.org"><img 
	src="<?php echo THEME_PATH ?>/images/admidio_logo_20.png" style="border: 0px; vertical-align: bottom;" src="images/admidio_small.png"
	 alt="Das Online-Verwaltungssystem für Vereine, Gruppen und Organisationen"
	 title="Das Online-Verwaltungssystem für Vereine, Gruppen und Organisationen" /></a>
	<span style="font-size: 9pt;">&nbsp;&nbsp;&copy; 2004 - <? echo date('Y', time()); ?>&nbsp;&nbsp;Admidio Team</span>
</p>