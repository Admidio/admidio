<?php
/**
 ***********************************************************************************************
 * Installation step: create_organization
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_organization.php')
{
    exit('This page may not be called directly!');
}

if (isset($_POST['db_host']))
{
    // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
    $_SESSION['db_type']     = $_POST['db_type'];
    $_SESSION['db_host']     = $_POST['db_host'];
    $_SESSION['db_port']     = $_POST['db_port'];
    $_SESSION['db_database'] = $_POST['db_database'];
    $_SESSION['db_user']     = $_POST['db_user'];
    $_SESSION['db_password'] = $_POST['db_password'];
    $_SESSION['prefix']      = $_POST['db_prefix'];

    if ($_SESSION['db_type']     === ''
    ||  $_SESSION['db_host']     === ''
    ||  $_SESSION['db_database'] === ''
    ||  $_SESSION['db_user']     === ''
    ||  $_SESSION['prefix']      === '')
    {
        showNotice(
            $gL10n->get('INS_DATABASE_CONNECTION_NOT_COMPLETELY'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check DB-type
    if (!in_array($_SESSION['db_type'], array(Database::PDO_ENGINE_MYSQL, Database::PDO_ENGINE_PGSQL), true))
    {
        showNotice(
            $gL10n->get('INS_DATABASE_TYPE_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check host
    // TODO: unix_server is currently not supported
    if (preg_match($hostnameRegex, $_SESSION['db_host']) !== 1 && filter_var($_SESSION['db_host'], FILTER_VALIDATE_IP) === false)
    {
        showNotice(
            $gL10n->get('INS_HOST_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check port
    if ($_SESSION['db_port'] === '' || $_SESSION['db_port'] === null)
    {
        $_SESSION['db_port'] = null;
    }
    elseif (is_numeric($_SESSION['db_port']) && (int) $_SESSION['db_port'] > 0 && (int) $_SESSION['db_port'] <= 65535)
    {
        $_SESSION['db_port'] = (int) $_SESSION['db_port'];
    }
    else
    {
        showNotice(
            $gL10n->get('INS_DATABASE_PORT_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check database
    if (strlen($_SESSION['db_database']) > 64 || preg_match($sqlIdentifiersRegex, $_SESSION['db_database']) !== 1)
    {
        showNotice(
            $gL10n->get('INS_DATABASE_NAME_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check user
    if (strlen($_SESSION['db_user']) > 64 || preg_match($sqlIdentifiersRegex, $_SESSION['db_user']) !== 1)
    {
        showNotice(
            $gL10n->get('INS_DATABASE_USER_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // Check password
    $zxcvbnScore = PasswordHashing::passwordStrength($_SESSION['db_password']);
    if ($zxcvbnScore <= 2)
    {
        $gLogger->warning('Database password is weak! (zxcvbn lib)', array('score' => $zxcvbnScore));
    }

    // Check prefix
    if (strlen($_SESSION['prefix']) > 10 || preg_match($sqlIdentifiersRegex, $_SESSION['prefix']) !== 1)
    {
        showNotice(
            $gL10n->get('INS_TABLE_PREFIX_INVALID'),
            'installation.php?step=connect_database',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }

    // for security reasons only check database connection if no config file exists
    if (!is_file($pathConfigFile))
    {
        // check database connections
        try
        {
            $db = new Database($_SESSION['db_type'], $_SESSION['db_host'], $_SESSION['db_port'], $_SESSION['db_database'], $_SESSION['db_user'], $_SESSION['db_password']);
        }
        catch (AdmException $e)
        {
            showNotice(
                $gL10n->get('SYS_DATABASE_NO_LOGIN', $e->getText()),
                'installation.php?step=connect_database',
                $gL10n->get('SYS_BACK'),
                'layout/back.png'
            );
            // => EXIT
        }

        // check database version
        $message = checkDatabaseVersion($db);
        if ($message !== '')
        {
            showNotice($message, 'installation.php?step=connect_database', $gL10n->get('SYS_BACK'), 'layout/back.png');
            // => EXIT
        }

        // now check if a valid installation exists.
        $sql = 'SELECT org_id FROM ' . $_SESSION['prefix'] . '_organizations';
        $pdoStatement = $db->queryPrepared($sql, array(), false);

        if ($pdoStatement !== false && $pdoStatement->rowCount() > 0)
        {
            // valid installation exists -> exit installation
            showNotice(
                $gL10n->get('INS_INSTALLATION_EXISTS'),
                '../index.php',
                $gL10n->get('SYS_OVERVIEW'),
                'layout/application_view_list.png'
            );
            // => EXIT
        }
    }
}

// create a page to enter the organization names
$form = new HtmlFormInstallation('installation-form', 'installation.php?step=create_administrator');

// initialize form data
$shortnameProperty = FIELD_REQUIRED;

if (isset($_SESSION['orga_shortname']))
{
    $orgaShortName = $_SESSION['orga_shortname'];
    $orgaLongName  = $_SESSION['orga_longname'];
    $orgaEmail     = $_SESSION['orga_email'];
}
else
{
    $orgaShortName = $g_organization;
    $orgaLongName  = '';
    $orgaEmail     = '';

    if ($g_organization !== '')
    {
        $shortnameProperty = FIELD_READONLY;
    }
}

// create array with possible PHP timezones
$allTimezones = DateTimeZone::listIdentifiers();
$timezones = array();
foreach ($allTimezones as $timezone)
{
    $timezones[$timezone] = $timezone;
}

$form->setFormDescription($gL10n->get('ORG_NEW_ORGANIZATION_DESC'), $gL10n->get('INS_SET_ORGANIZATION'));
$form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ORGANIZATION'));
$form->addInput('orga_shortname',    $gL10n->get('SYS_NAME_ABBREVIATION'),   $orgaShortName, array('maxLength' => 10, 'property' => $shortnameProperty, 'class' => 'form-control-small'));
$form->addInput('orga_longname',     $gL10n->get('SYS_NAME'),                $orgaLongName,  array('maxLength' => 50, 'property' => FIELD_REQUIRED));
$form->addInput('orga_email',        $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $orgaEmail,     array('type' => 'email', 'maxLength' => 50, 'property' => FIELD_REQUIRED));
$form->addSelectBox('orga_timezone', $gL10n->get('ORG_TIMEZONE'),            $timezones,     array('property' => FIELD_REQUIRED, 'defaultValue' => date_default_timezone_get()));
$form->closeGroupBox();
$form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?step=connect_database'));
$form->addSubmitButton('next_page', $gL10n->get('INS_CREATE_ADMINISTRATOR'), array('icon' => 'layout/forward.png'));
echo $form->show();
