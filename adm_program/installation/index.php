<?php
/******************************************************************************
 * Redirect the user to installation or update page
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// check if installation is necessary
if(file_exists('../../adm_my_files/config.php'))
{
    $page = 'update.php';
}
else
{
    $page = 'installation.php';
}

// redirect to installation or update page
header('Location: '.$page);
exit();
