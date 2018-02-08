<?php
/**
 ***********************************************************************************************
 * Set the correct startpage for Admidio
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
// if config file doesn't exists, than show installation dialog
if (!is_file(__DIR__ . '/adm_my_files/config.php'))
{
    header('Location: adm_program/installation/index.php');
    exit();
}

// include only bootstrap because we could not include common.php at this point
// to be backward compatible with older versions
require_once(__DIR__ . '/adm_my_files/config.php');
require_once(__DIR__ . '/adm_program/system/bootstrap.php');

// connect to database
try
{
    $gDb = Database::createDatabaseInstance();
}
catch (AdmException $e)
{
    $e->showText();
    // => EXIT
}

// if database doesn't contain the components table then link to update wizard
// because database Admidio version is lower then 3.0
$sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
if ($gDb->queryPrepared($sql, array(), false) === false)
{
    admRedirect(ADMIDIO_URL . '/adm_program/installation/update.php');
    // => EXIT
}

require_once(__DIR__ . '/adm_program/system/common.php');

if (isset($gHomepage))
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
