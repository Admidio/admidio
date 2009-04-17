<?php
    // wenn noch nicht installiert, dann Install-Dialog anzeigen
    if(file_exists('config.php'))
    {
        require_once('adm_program/system/common.php');
        header('Location: '.$g_homepage);
    }
    else
    {
        header('Location: adm_install/index.php');
    }
?>