<?php
/**
 ***********************************************************************************************
 * Admidio Shutdown
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'shutdown.php')
{
    exit('This page may not be called directly!');
}

function admShutdown()
{
    global $gLogger;

    $gLogger->info('MEMORY USAGE: ' . round(memory_get_peak_usage() / 1024, 1) . ' KiB');
}

register_shutdown_function('admShutdown');
