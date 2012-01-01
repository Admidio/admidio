<?php
/******************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('login_valid.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('This page may not be called directly !');
}

if($gValidLogin == false)
{
    if(isset($_SESSION['login_forward_url']) == false)
    {
        // aufgerufene URL merken, damit diese nach dem Einloggen sofort aufgerufen werden kann
        $_SESSION['login_forward_url'] = CURRENT_URL;
    }
    
    // User nicht eingeloggt -> Loginseite aufrufen
    $location = 'Location: '.$g_root_path.'/adm_program/system/login.php';
    header($location);
    exit();
}

?>