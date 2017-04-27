<?php
/**
 ***********************************************************************************************
 * Set the correct startpage for Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if(is_file('adm_my_files/config.php'))
{
    // include all files separately because we could not include common.php at this point
    // to be backward compatible with older versions
    require_once('adm_my_files/config.php');
    require_once('adm_program/system/init_globals.php');
    require_once('adm_program/system/constants.php');
    require_once('adm_program/system/function.php');
    require_once('adm_program/system/logging.php');

    // connect to database
    try
    {
        $gDb = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
    }
    catch(AdmException $e)
    {
        $e->showText();
        // => EXIT
    }

    // if database doesn't contain the components table then link to update wizard
    // because database Admidio version is lower then 3.0
    if($gDb->query('SELECT 1 FROM '.TBL_COMPONENTS, false) === false)
    {
        admRedirect(ADMIDIO_URL . '/adm_program/installation/update.php');
        // => EXIT
    }

    require_once('adm_program/system/common.php');

    if(isset($gHomepage))
    {
        admRedirect($gHomepage);
        // => EXIT
    }
    else
    {
        // if parameter gHomepage doesn't exists then show default page
        admRedirect(ADMIDIO_URL . '/adm_program/index.php');
        // => EXIT
    }
}
else
{
    // config file doesn't exists then show installation wizard
    header('Location: adm_program/installation/index.php');
    exit();
}
