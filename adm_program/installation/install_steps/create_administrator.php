<?php
/**
 ***********************************************************************************************
 * Installation step: create_administrator
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_administrator.php') {
    exit('This page may not be called directly!');
}

if (isset($_POST['orga_shortname'])) {
    // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
    $_SESSION['orga_shortname'] = StringUtils::strStripTags($_POST['orga_shortname']);
    $_SESSION['orga_longname']  = StringUtils::strStripTags($_POST['orga_longname']);
    $_SESSION['orga_email']     = StringUtils::strStripTags($_POST['orga_email']);
    $_SESSION['orga_timezone']  = $_POST['orga_timezone'];

    if ($_SESSION['orga_shortname'] === ''
    ||  $_SESSION['orga_longname']  === ''
    ||  $_SESSION['orga_email']     === ''
    ||  !in_array($_SESSION['orga_timezone'], \DateTimeZone::listIdentifiers(), true)) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization'))
        );
        // => EXIT
    }

    // allow only letters, numbers and special characters like .-_+@
    if (!StringUtils::strValidCharacters($_SESSION['orga_shortname'], 'noSpecialChar')) {
        $page = new HtmlPageInstallation('admidio-installation-message');
        $page->showMessage(
            'error',
            $gL10n->get('SYS_NOTE'),
            $gL10n->get('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION')),
            $gL10n->get('SYS_BACK'),
            'fa-arrow-circle-left',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization'))
        );
        // => EXIT
    }
}

// initialize form data
if (isset($_SESSION['user_last_name'])) {
    $userLastName  = $_SESSION['user_last_name'];
    $userFirstName = $_SESSION['user_first_name'];
    $userEmail     = $_SESSION['user_email'];
    $userLogin     = $_SESSION['user_login'];
} else {
    $userLastName  = '';
    $userFirstName = '';
    $userEmail     = '';
    $userLogin     = '';
}

$userData = array($userLastName, $userFirstName, $userEmail, $userLogin);

// create a page to enter all necessary data to create a administrator user
$page = new HtmlPageInstallation('admidio-installation-create-administrator');
$page->addTemplateFile('installation.tpl');
$page->assign('subHeadline', $gL10n->get('INS_CREATE_ADMINISTRATOR'));
$page->assign('text', $gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC'));
$page->addJavascriptFile(FOLDER_LIBS_CLIENT . '/zxcvbn/dist/zxcvbn.js');
$page->addJavascript('
    $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4)");

    $("#user_password").keyup(function(e) {
        var result = zxcvbn(e.target.value, ' . json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
        var cssClasses = ["bg-danger", "bg-danger", "bg-warning", "bg-info", "bg-success"];

        var progressBar = $("#admidio-password-strength .progress-bar");
        progressBar.attr("aria-valuenow", result.score * 25);
        progressBar.css("width", result.score * 25 + "%");
        progressBar.removeClass(cssClasses.join(" "));
        progressBar.addClass(cssClasses[result.score]);
    });
', true);

$form = new HtmlForm('installation-form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_config')));
$form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ADMINISTRATOR'));
$form->addInput(
    'user_last_name',
    $gL10n->get('SYS_LASTNAME'),
    $userLastName,
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_first_name',
    $gL10n->get('SYS_FIRSTNAME'),
    $userFirstName,
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_email',
    $gL10n->get('SYS_EMAIL'),
    $userEmail,
    array('type' => 'email', 'maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_login',
    $gL10n->get('SYS_USERNAME'),
    $userLogin,
    array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_password',
    $gL10n->get('SYS_PASSWORD'),
    '',
    array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'passwordStrength' => true, 'passwordUserData' => $userData, 'helpTextIdLabel' => 'PRO_PASSWORD_DESCRIPTION')
);
$form->addInput(
    'user_password_confirm',
    $gL10n->get('SYS_CONFIRM_PASSWORD'),
    '',
    array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
);
$form->closeGroupBox();
$form->addButton(
    'previous_page',
    $gL10n->get('SYS_BACK'),
    array('icon' => 'fa-arrow-circle-left', 'class' => 'admidio-margin-bottom',
        'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization')))
);
$form->addSubmitButton('next_page', $gL10n->get('INS_CONTINUE_INSTALLATION'), array('icon' => 'fa-arrow-circle-right', 'class' => 'float-right'));

$page->addHtml($form->show());
$page->show();
