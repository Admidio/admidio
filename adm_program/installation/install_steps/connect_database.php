<?php
/**
 ***********************************************************************************************
 * Installation step: connect_database
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'connect_database.php') {
    exit('This page may not be called directly!');
}

if (isset($_POST['system_language']) && trim($_POST['system_language']) !== '') {
    $_SESSION['language'] = $_POST['system_language'];
    $gL10n->setLanguage($_SESSION['language']);
} elseif (!isset($_SESSION['language'])) {
    $page = new HtmlPageInstallation('admidio-installation-message');
    $page->showMessage(
        'error',
        $gL10n->get('SYS_NOTE'),
        $gL10n->get('INS_LANGUAGE_NOT_CHOSEN'),
        $gL10n->get('SYS_BACK'),
        'fa-arrow-circle-left',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'welcome'))
    );
    // => EXIT
}

// HTML-Form Regex-Patterns
$hostnameRegex = '(?:[a-z0-9-]{1,63}\.)*(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z]{2,63})?';
$ipv4Regex = '(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)';
$ipv6Regex = '(?:[a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}';
$hostRegex = '^(' . $hostnameRegex . '|' . $ipv4Regex . '|' . $ipv6Regex . ')$';
$sqlIdentifiersRegex = '^[a-zA-Z0-9_$@-]+$';

// initialize form data
if (isset($_SESSION['db_host'])) {
    $dbEngine    = $_SESSION['db_engine'];
    $dbHost      = $_SESSION['db_host'];
    $dbPort      = $_SESSION['db_port'];
    $dbName      = $_SESSION['db_name'];
    $dbUsername  = $_SESSION['db_username'];
    $tablePrefix = $_SESSION['table_prefix'];
} else {
    $dbEngine    = '';
    $dbHost      = '';
    $dbPort      = '';
    $dbName      = '';
    $dbUsername  = '';
    $tablePrefix = 'adm';
}

// create a page to enter all necessary database connection information
$page = new HtmlPageInstallation('admidio-installation-connect-database');
$page->addTemplateFile('installation.tpl');
$page->assign('subHeadline', $gL10n->get('INS_ENTER_LOGIN_TO_DATABASE'));
$page->assign('text', $gL10n->get('INS_DATABASE_LOGIN_DESC'));

$form = new HtmlForm('installation-form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization')));
$form->openGroupBox('gbConnectDatabase', $gL10n->get('INS_DATABASE_LOGIN'));
$form->addSelectBoxFromXml(
    'db_engine',
    $gL10n->get('INS_DATABASE_SYSTEM'),
    ADMIDIO_PATH.'/adm_program/system/databases.xml',
    'identifier',
    'name',
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $dbEngine)
);
$form->addInput(
    'db_host',
    $gL10n->get('SYS_HOST'),
    $dbHost,
    array('pattern' => $hostRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'INS_DATABASE_HOST_INFO')
);
$form->addInput(
    'db_port',
    $gL10n->get('SYS_PORT'),
    $dbPort,
    array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 65535, 'step' => 1, 'helpTextIdLabel' => 'INS_DATABASE_PORT_INFO')
);
$form->addInput(
    'db_name',
    $gL10n->get('SYS_DATABASE'),
    $dbName,
    array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'db_username',
    $gL10n->get('SYS_USERNAME'),
    $dbUsername,
    array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 64, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'db_password',
    $gL10n->get('SYS_PASSWORD'),
    '',
    array('type' => 'password')
);
$form->addInput(
    'table_prefix',
    $gL10n->get('INS_TABLE_PREFIX'),
    $tablePrefix,
    array('pattern' => $sqlIdentifiersRegex, 'maxLength' => 10, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
);
$form->closeGroupBox();
$form->addButton(
    'previous_page',
    $gL10n->get('SYS_BACK'),
    array('icon' => 'fa-arrow-circle-left', 'class' => 'admidio-margin-bottom',
        'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'welcome')))
);
$form->addSubmitButton('next_page', $gL10n->get('INS_SET_ORGANIZATION'), array('icon' => 'fa-arrow-circle-right', 'class' => 'float-right'));

$page->addHtml($form->show());
$page->show();
