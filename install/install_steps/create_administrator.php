<?php
/**
 ***********************************************************************************************
 * Installation step: create_administrator
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_administrator.php') {
    exit('This page may not be called directly!');
}

if ($mode === 'html') {
    // initialize form data
    if (isset($_SESSION['user_last_name'])) {
        $userLastName = $_SESSION['user_last_name'];
        $userFirstName = $_SESSION['user_first_name'];
        $userEmail = $_SESSION['user_email'];
        $userLogin = $_SESSION['user_login'];
    } else {
        $userLastName = '';
        $userFirstName = '';
        $userEmail = '';
        $userLogin = '';
    }

    $userData = array($userLastName, $userFirstName, $userEmail, $userLogin);

    // create a page to enter all necessary data to create a administrator user
    $page = new InstallationPresenter('adm_installation_create_administrator', $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT)));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_CREATE_ADMINISTRATOR'));
    $page->assignSmartyVariable('text', $gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC'));

    $form = new FormPresenter(
        'adm_installation_create_administrator_form',
        'installation.create-administrator.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION. '/installation.php', array('step' => 'create_administrator', 'mode' => 'check')),
        $page
    );
    $form->addInput(
        'adm_user_last_name',
        $gL10n->get('SYS_LASTNAME'),
        $userLastName,
        array('maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_user_first_name',
        $gL10n->get('SYS_FIRSTNAME'),
        $userFirstName,
        array('maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_user_email',
        $gL10n->get('SYS_EMAIL'),
        $userEmail,
        array('type' => 'email', 'maxLength' => 50, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_user_login',
        $gL10n->get('SYS_USERNAME'),
        $userLogin,
        array('maxLength' => 254, 'property' => FormPresenter::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_user_password',
        $gL10n->get('SYS_PASSWORD'),
        '',
        array('type' => 'password', 'property' => FormPresenter::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'passwordStrength' => true, 'passwordUserData' => $userData, 'helpTextId' => 'SYS_PASSWORD_DESCRIPTION')
    );
    $form->addInput(
        'adm_user_password_confirm',
        $gL10n->get('SYS_CONFIRM_PASSWORD'),
        '',
        array('type' => 'password', 'property' => FormPresenter::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
    );
    $form->addButton(
        'adm_previous_page',
        $gL10n->get('SYS_BACK'),
        array('icon' => 'bi-arrow-left-circle-fill', 'class' => 'admidio-margin-bottom',
            'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization')))
    );
    $form->addSubmitButton('adm_next_page', $gL10n->get('INS_CONTINUE_INSTALLATION'), array('icon' => 'bi-arrow-right-circle-fill', 'class' => 'float-end'));

    $form->addToHtmlPage();
    $_SESSION['installationCreateAdministratorForm'] = $form;
    $page->show();
} elseif ($mode === 'check') {
    // check form field input and sanitized it from malicious content
    if (isset($_SESSION['installationCreateAdministratorForm'])) {
        $formValues = $_SESSION['installationCreateAdministratorForm']->validate($_POST);
    } else {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // Save administrator data filtered in session variables
    $_SESSION['user_last_name']        = $formValues['adm_user_last_name'];
    $_SESSION['user_first_name']       = $formValues['adm_user_first_name'];
    $_SESSION['user_email']            = $formValues['adm_user_email'];
    $_SESSION['user_login']            = $formValues['adm_user_login'];
    $_SESSION['user_password']         = $formValues['adm_user_password'];
    $_SESSION['user_password_confirm'] = $formValues['adm_user_password_confirm'];

    // check the entered values with the same rules that a headless installation uses
    Installation::validateAdministratorInput(
        $_SESSION['user_login'],
        $_SESSION['user_first_name'],
        $_SESSION['user_last_name'],
        $_SESSION['user_email'],
        $_SESSION['user_password']
    );

    // password must be the same with password confirm
    if ($_SESSION['user_password'] !== $_SESSION['user_password_confirm']) {
        throw new Exception('SYS_PASSWORDS_NOT_EQUAL');
    }

    // if config file exists than don't create a new one
    if (is_file($configPath)) {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'start_installation')));
        // => EXIT
    } else {
        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_config'))));
        exit();
    }
}
