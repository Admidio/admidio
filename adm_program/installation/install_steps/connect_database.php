<?php
/**
 ***********************************************************************************************
 * Installation step: connect_database
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'connect_database.php')
{
    exit('This page may not be called directly!');
}

if (!isset($_SESSION['language']))
{
    // check if a language string was committed
    if (!isset($_POST['system_language']) || trim($_POST['system_language']) === '')
    {
        showNotice(
            $gL10n->get('INS_LANGUAGE_NOT_CHOOSEN'),
            'installation.php?step=choose_language',
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }
    else
    {
        $_SESSION['language'] = $_POST['system_language'];
        $gL10n->setLanguage($_SESSION['language']);
    }
}

// initialize form data
if (isset($_SESSION['db_host']))
{
    $dbType   = $_SESSION['db_type'];
    $host     = $_SESSION['db_host'];
    $port     = $_SESSION['db_port'];
    $database = $_SESSION['db_database'];
    $user     = $_SESSION['db_user'];
    $prefix   = $_SESSION['prefix'];
}
else
{
    $dbType   = '';
    $host     = '';
    $port     = '';
    $database = '';
    $user     = '';
    $prefix   = 'adm';
}

$hostnameRegex = '(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])';
$ipv4Regex = '(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)';
$ipv6Regex = '(?:[a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}';
$hostRegex = '^(' . $hostnameRegex . '|' . $ipv4Regex . '|' . $ipv6Regex . ')$';
$sqlIdentifiersRegex = '^[a-zA-Z]([a-zA-Z0-9_]*[a-zA-Z0-9])?$';

// create a page to enter all necessary database connection information
$form = new HtmlFormInstallation('installation-form', 'installation.php?step=create_organization');
$form->setFormDescription($gL10n->get('INS_DATABASE_LOGIN_DESC'), $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
$form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATABASE_LOGIN'));
$form->addSelectBoxFromXml(
    'db_type', $gL10n->get('INS_DATABASE_SYSTEM'), ADMIDIO_PATH.'/adm_program/system/databases.xml',
    'identifier', 'name', array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $dbType)
);
$form->addInput('db_host',     $gL10n->get('SYS_HOST'),         $host,     array('pattern' => $hostRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'INS_DATABASE_HOST_INFO'));
$form->addInput('db_port',     $gL10n->get('SYS_PORT'),         $port,     array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 65535, 'step' => 1, 'helpTextIdLabel' => 'INS_DATABASE_PORT_INFO'));
$form->addInput('db_database', $gL10n->get('SYS_DATABASE'),     $database, array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED));
$form->addInput('db_user',     $gL10n->get('SYS_USERNAME'),     $user,     array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED));
$form->addInput('db_password', $gL10n->get('SYS_PASSWORD'),     null,      array('type' => 'password'));
$form->addInput('db_prefix',   $gL10n->get('INS_TABLE_PREFIX'), $prefix,   array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 10, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small'));
$form->closeGroupBox();
$form->addButton('previous_page', $gL10n->get('SYS_BACK'), array('icon' => 'layout/back.png', 'link' => 'installation.php?step=welcome'));
$form->addSubmitButton('next_page', $gL10n->get('INS_SET_ORGANIZATION'), array('icon' => 'layout/forward.png'));
echo $form->show();
