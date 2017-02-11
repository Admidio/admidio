<?php
/**
 ***********************************************************************************************
 * Dieses Script muss mit include() eingefuegt werden, wenn der User zum Aufruf
 * einer Seite eingeloggt sein MUSS
 *
 * Ist der User nicht eingeloggt, wird er automatisch auf die Loginseite weitergeleitet
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'login_valid.php')
{
    exit('This page may not be called directly!');
}

if(!$gValidLogin)
{
    if(!isset($_SESSION['login_forward_url']))
    {
        // remember requested URL, so we could redirect to this URL again after login
        $_SESSION['login_forward_url'] = CURRENT_URL;
    }

    // User not logged in -> Request login site
    admRedirect(ADMIDIO_URL . '/adm_program/system/login.php');
    // => EXIT
}
