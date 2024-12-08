<?php
/**
 ***********************************************************************************************
 * Installation step: create_organization
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\UI\Component\Form;
use Admidio\UI\View\Installation;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_organization.php') {
    exit('This page may not be called directly!');
}

if ($mode === 'html') {
    // initialize form data
    $shortnameProperty = Form::FIELD_REQUIRED;

    if (isset($_SESSION['orga_shortname'])) {
        $orgaShortName = $_SESSION['orga_shortname'];
        $orgaLongName = $_SESSION['orga_longname'];
        $orgaEmail = $_SESSION['orga_email'];
    } else {
        $orgaShortName = '';
        $orgaLongName = '';
        $orgaEmail = '';
    }

    // create array with possible PHP timezones
    $allTimezones = \DateTimeZone::listIdentifiers();
    $timezones = array();
    foreach ($allTimezones as $timezone) {
        $timezones[$timezone] = $timezone;
    }

    // create a page to enter the organization names
    $page = new Installation('adm_installation_create_organization', $gL10n->get('INS_INSTALLATION'));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_SET_ORGANIZATION'));
    $page->assignSmartyVariable('text', $gL10n->get('SYS_NEW_ORGANIZATION_DESC'));

    $form = new Form(
        'adm_installation_create_organization_form',
        'installation.create-organization.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_organization', 'mode' => 'check')),
        $page
    );
    $form->addInput(
        'adm_organization_shortname',
        $gL10n->get('SYS_NAME_ABBREVIATION'),
        $orgaShortName,
        array('maxLength' => 10, 'property' => $shortnameProperty, 'class' => 'form-control-small')
    );
    $form->addInput(
        'adm_organization_longname',
        $gL10n->get('SYS_NAME'),
        $orgaLongName,
        array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addInput(
        'adm_organization_email',
        $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
        $orgaEmail,
        array('type' => 'email', 'maxLength' => 254, 'property' => Form::FIELD_REQUIRED)
    );
    $form->addSelectBox(
        'adm_organization_timezone',
        $gL10n->get('ORG_TIMEZONE'),
        $timezones,
        array('property' => Form::FIELD_REQUIRED, 'defaultValue' => date_default_timezone_get())
    );
    $form->addButton(
        'adm_previous_page',
        $gL10n->get('SYS_BACK'),
        array('icon' => 'bi-arrow-left-circle-fill', 'class' => 'admidio-margin-bottom',
            'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION. '/installation.php', array('step' => 'connect_database')))
    );
    $form->addSubmitButton('adm_next_page', $gL10n->get('INS_CREATE_ADMINISTRATOR'), array('icon' => 'bi-arrow-right-circle-fill', 'class' => 'float-end'));

    $form->addToHtmlPage();
    $_SESSION['installationCreateOrganizationForm'] = $form;
    $page->show();
} elseif ($mode === 'check') {
    // check form field input and sanitized it from malicious content
    if (isset($_SESSION['installationCreateOrganizationForm'])) {
        $formValues = $_SESSION['installationCreateOrganizationForm']->validate($_POST);
    } else {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // Save organization data filtered in session variables
    $_SESSION['orga_shortname'] = $formValues['adm_organization_shortname'];
    $_SESSION['orga_longname']  = $formValues['adm_organization_longname'];
    $_SESSION['orga_email']     = $formValues['adm_organization_email'];
    $_SESSION['orga_timezone']  = $formValues['adm_organization_timezone'];

    if (!in_array($_SESSION['orga_timezone'], \DateTimeZone::listIdentifiers(), true)) {
        throw new Exception('SYS_FIELD_INVALID_INPUT', array('ORG_TIMEZONE'));
    }

    // allow only letters, numbers and special characters like .-_+@
    if (!StringUtils::strValidCharacters($_SESSION['orga_shortname'], 'noSpecialChar')) {
        throw new Exception('SYS_FIELD_INVALID_CHAR', array('SYS_NAME_ABBREVIATION'));
    }

    echo json_encode(array(
        'status' => 'success',
        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'create_administrator'))));
    exit();
}
