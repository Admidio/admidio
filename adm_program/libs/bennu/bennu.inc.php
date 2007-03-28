<?php // $Id: bennu.inc.php,v 1.3 2005/07/21 22:31:44 defacer Exp $

if(!defined('_BENNU_VERSION')) {
    define('_BENNU_VERSION', '0.1');
    include(SERVER_PATH. '/adm_program/libs/bennu/icalendar_rfc2445.php');
    include(SERVER_PATH. '/adm_program/libs/bennu/icalendar_components.php');
    include(SERVER_PATH. '/adm_program/libs/bennu/icalendar_properties.php');
    include(SERVER_PATH. '/adm_program/libs/bennu/icalendar_parameters.php');
}

?>
