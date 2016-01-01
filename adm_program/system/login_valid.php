<?php
/**
 ***********************************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
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
