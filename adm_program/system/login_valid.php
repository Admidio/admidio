<?php
/******************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if (basename($_SERVER['SCRIPT_FILENAME']) === 'login_valid.php')
{
    exit('This page may not be called directly !');
}

if(!$gValidLogin)
{
    if(!isset($_SESSION['login_forward_url']))
    {
        // aufgerufene URL merken, damit diese nach dem Einloggen sofort aufgerufen werden kann
        $_SESSION['login_forward_url'] = CURRENT_URL;
    }

    // User nicht eingeloggt -> Loginseite aufrufen
    header('Location: '.$g_root_path.'/adm_program/system/login.php');
    exit();
}
