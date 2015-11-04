<?php
/******************************************************************************
 * Set the correct startpage for Admidio
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if(file_exists('adm_my_files/config.php'))
{
    require_once('adm_my_files/config.php');

    // default prefix is set to 'adm' because of compatibility to old versions
    if(!isset($g_tbl_praefix))
    {
        $g_tbl_praefix = 'adm';
    }

    // create database object and establish connection to database
    if(!isset($gDbType))
    {
        $gDbType = 'mysql';
    }

    require_once('adm_program/system/constants.php');
    require_once('adm_program/system/function.php');

    // connect to database
    try
    {
        $gDb = new Database($gDbType, $g_adm_srv, null, $g_adm_db, $g_adm_usr, $g_adm_pw);
    }
    catch(AdmException $e)
    {
        $e->showText();
    }

    // if database doesn't contain the components table then link to update wizard
    // because database Admidio version is lower then 3.0
    if($gDb->query('SELECT 1 FROM '.TBL_COMPONENTS, false) == false)
    {
        header('Location: adm_program/installation/update.php');
    }

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
    header('Location: adm_program/installation/index.php');
}
