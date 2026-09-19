<?php
/**
 ***********************************************************************************************
 * Command-line configuration of Admidio
 *
 * This file controls who may use the Admidio command line and what defaults its options have.
 * Without this file, or with $gCliEnabled = false, the command line refuses every command
 * except help, list, version, completion and cli:defaultconfig.
 *
 * Note that this file expresses a policy, it is not a security boundary: anyone who can read
 * adm_my_files/config.php holds the database credentials and can reach the same data without
 * the command line. Restrict read access to adm_my_files if that is what you need.
 *
 * "admidio cli:defaultconfig" prints this file with all its comments, so a copy can be created
 * at any time with "admidio cli:defaultconfig --output=adm_my_files/cli-config.php".
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// ---------------------------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------------------------

// Master switch. Set to true to allow the Admidio command line to be used at all.
$gCliEnabled = false;

// System accounts that may run the command line. Empty = every local account may.
// Comma-separated, each entry one of:
//   reinhold              user name              (Windows: 'PC-01\reinhold' or the user SID)
//   1001                  numeric user id
//   %admidio-cli          group name             (Windows: group name or group SID)
//   %2000                 numeric group id
// Supplementary groups count, so an operator is added with
// "usermod -aG admidio-cli <user>" and this file stays unchanged.
// Examples:
//   $gCliAllowedUsers = 'root, www-data';
//   $gCliAllowedUsers = '%admidio-cli';
//   $gCliAllowedUsers = 'reinhold, cronjob, %admidio-cli, 1001';
$gCliAllowedUsers = '';

// Commands that may be run, as comma-separated shell patterns (the wildcards of fnmatch():
// "*" matches any sequence, "?" a single character). The deny list is checked first and wins.
// An empty allow list allows every command that the deny list does not forbid.
// Examples:
//   // a cron account that may only run the scheduled maintenance:
//   $gCliAllowedCommands = 'maintenance:run, autologin:cleanup, update:check';
//   // everything except the commands that export data or secrets:
//   $gCliDeniedCommands  = 'database:backup, sso:key-export, sso:certificate-export, system:phpinfo';
//   // only the export commands:
//   $gCliAllowedCommands = 'list:export, *:export-rss';
$gCliAllowedCommands = '';
$gCliDeniedCommands  = '';

// ---------------------------------------------------------------------------------------------
// Acting Admidio user
// ---------------------------------------------------------------------------------------------

// The account that commands act as is the value of --as, which is an ordinary command-line
// option and therefore configured in $gCliDefaults below, not with a setting of its own:
//     $gCliDefaults = array('as' => 'reinhold');
// See the last section of this file. The account still has to be activated and a member of the
// organization, exactly as when --as is given on the command line.

// Admidio accounts that --as may address at all. Empty = every valid account.
// Useful when the command line is opened to a cron account: it then cannot act as a full
// administrator even though --as is not an authentication. Login name, user id or UUID.
// Example:
//   $gCliAllowedActors = 'cronjob, backup-service';
$gCliAllowedActors = '';

// ---------------------------------------------------------------------------------------------
// Log
// ---------------------------------------------------------------------------------------------

// Log of every invocation: time, system account, command, arguments, acting user, exit code.
// An absolute path is used as it is; a relative path is resolved inside the data directory of
// this installation (adm_my_files). Empty = no log. The file is rotated daily like admidio.log.
// Examples:
//   $gCliLogFile = 'logs/admidio-cli.log';        // -> adm_my_files/logs/admidio-cli.log
//   $gCliLogFile = '/var/log/admidio/cli.log';    // absolute, must be writable by the caller
//   $gCliLogFile = '';                            // no log
$gCliLogFile = 'logs/admidio-cli.log';

// ---------------------------------------------------------------------------------------------
// Default values for command-line options
// ---------------------------------------------------------------------------------------------

// Used whenever an option was given neither on the command line nor in the environment.
// Resolution order for every option:
//     --option=...  ->  environment variable ADMIDIO_<OPTION>  ->  $gCliDefaults  ->  built-in
// The environment variable is the name of the option in capitals with "-" replaced by "_",
// e.g. --organization -> ADMIDIO_ORGANIZATION, --no-truncate -> ADMIDIO_NO_TRUNCATE.
//
// Two kinds of entry:
//   * a global option: the key is the option name and the value is that option's single value.
//     These are the options that every command understands - config, host, organization, as,
//     format, output, width, quiet, no-interaction, no-truncate, dry-run. A global default is
//     used only by commands to which the option applies, so 'as' does not disturb the commands
//     that run without an acting user.
//   * a command: the key contains a colon and is the name of the command, and the value is an
//     array of that command's own options. Those apply to that command only and take precedence
//     over a global entry of the same name.
// --yes and --overwrite cannot be defaulted: a confirmation of a destructive operation has to
// be given per invocation. A single call ignores every default below when it is started with
// the environment variable ADMIDIO_NO_DEFAULTS=1, which is the way to find out whether a
// surprising result comes from this file.
//
// Example - act as one account instead of writing --as=reinhold every time, a host-dependent
// config.php, always the same organization, JSON for everything, and three commands with their
// own defaults, one of them acting as a different account:
//   $gCliDefaults = array(
//       'as'           => 'reinhold',
//       'host'         => 'www.example.org',
//       'organization' => 'example',
//       'format'       => 'json',
//       'width'        => 200,
//       'list:export'  => array('role' => 'Members', 'format' => 'csv'),
//       'photo:upload' => array('as' => 'photoadmin'),
//       'database:backup' => array('output' => '/var/backups/admidio')
//   );
$gCliDefaults = array();
