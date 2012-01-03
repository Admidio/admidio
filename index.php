<?php
/******************************************************************************
 * Set the correct startpage for Admidio
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if(file_exists('config.php'))
{
	// if config file exists then show stored homepage
	require_once('adm_program/system/common.php');

	if(isset($gHomepage))
	{
		header('Location: '.$gHomepage);
	}
	else
	{
		// if parameter gHomepage doesn't exists then show default page
		header('Location: adm_program/index.php');
	}
}
else
{
	// config file doesn't exists then show installation wizard
	header('Location: adm_install/index.php');
}

?>