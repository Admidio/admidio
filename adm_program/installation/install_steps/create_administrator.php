<?php
/**
 ***********************************************************************************************
 * Installation step: create_administrator
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'create_administrator.php')
{
    exit('This page may not be called directly!');
}

if (isset($_POST['orga_shortname']))
{
    // Zugangsdaten der DB in Sessionvariablen gefiltert speichern
    $_SESSION['orga_shortname'] = strStripTags($_POST['orga_shortname']);
    $_SESSION['orga_longname']  = strStripTags($_POST['orga_longname']);
    $_SESSION['orga_email']     = strStripTags($_POST['orga_email']);
    $_SESSION['orga_timezone']  = $_POST['orga_timezone'];

    if ($_SESSION['orga_shortname'] === ''
    ||  $_SESSION['orga_longname']  === ''
    ||  $_SESSION['orga_email']     === ''
    ||  !in_array($_SESSION['orga_timezone'], \DateTimeZone::listIdentifiers(), true))
    {
        showNotice(
            $gL10n->get('INS_ORGANIZATION_NAME_NOT_COMPLETELY'),
            safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'create_organization')),
            $gL10n->get('SYS_BACK'),
            'layout/back.png'
        );
        // => EXIT
    }
}

// initialize form data
if (isset($_SESSION['user_last_name']))
{
    $userLastName  = $_SESSION['user_last_name'];
    $userFirstName = $_SESSION['user_first_name'];
    $userEmail     = $_SESSION['user_email'];
    $userLogin     = $_SESSION['user_login'];
}
else
{
    $userLastName  = '';
    $userFirstName = '';
    $userEmail     = '';
    $userLogin     = '';
}

$userData = array($userLastName, $userFirstName, $userEmail, $userLogin);

// create a page to enter all necessary data to create a administrator user
$form = new HtmlFormInstallation('installation-form', safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'create_config')));
$form->addHeader('<script type="text/javascript" src="'.ADMIDIO_URL.FOLDER_LIBS_CLIENT.'/zxcvbn/dist/zxcvbn.js"></script>');
$form->addHeader('
    <script type="text/javascript">
        $(function() {
            $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4)");

            $("#user_password").keyup(function(e) {
                var result = zxcvbn(e.target.value, ' . json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
                var cssClasses = ["progress-bar-danger", "progress-bar-danger", "progress-bar-warning", "progress-bar-info", "progress-bar-success"];

                var progressBar = $("#admidio-password-strength .progress-bar");
                progressBar.attr("aria-valuenow", result.score * 25);
                progressBar.css("width", result.score * 25 + "%");
                progressBar.removeClass(cssClasses.join(" "));
                progressBar.addClass(cssClasses[result.score]);
            });
        });
    </script>
');
$form->setFormDescription($gL10n->get('INS_DATA_OF_ADMINISTRATOR_DESC'), $gL10n->get('INS_CREATE_ADMINISTRATOR'));
$form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_DATA_OF_ADMINISTRATOR'));
$form->addInput(
    'user_last_name', $gL10n->get('SYS_LASTNAME'), $userLastName,
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_first_name', $gL10n->get('SYS_FIRSTNAME'), $userFirstName,
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_email', $gL10n->get('SYS_EMAIL'), $userEmail,
    array('type' => 'email', 'maxLength' => 50, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_login', $gL10n->get('SYS_USERNAME'), $userLogin,
    array('maxLength' => 35, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'user_password', $gL10n->get('SYS_PASSWORD'), '',
    array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH, 'passwordStrength' => true, 'passwordUserData' => $userData, 'helpTextIdLabel' => 'PRO_PASSWORD_DESCRIPTION')
);
$form->addInput(
    'user_password_confirm', $gL10n->get('SYS_CONFIRM_PASSWORD'), '',
    array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
);
$form->closeGroupBox();
$form->addButton(
    'previous_page', $gL10n->get('SYS_BACK'),
    array('icon' => 'layout/back.png', 'link' => safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'create_organization')))
);
$form->addSubmitButton('next_page', $gL10n->get('INS_CONTINUE_INSTALLATION'), array('icon' => 'layout/forward.png'));
echo $form->show();
