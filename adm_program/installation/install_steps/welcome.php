<?php
/**
 ***********************************************************************************************
 * Installation step: welcome
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'install_step_.php')
{
    exit('This page may not be called directly!');
}

// create the text that should be shown in the form
$message = $gL10n->get('INS_WELCOME_TEXT', '<a href="https://www.admidio.org/forum">', '</a>');
$messageWarning = '';

// if this is a beta version then show a notice to the user
if (ADMIDIO_VERSION_BETA > 0)
{
    $gLogger->notice('INSTALLATION: This is a BETA release!');

    $messageWarning .= '
        <div class="alert alert-warning alert-small" role="alert">
            <span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_BETA_VERSION').'
        </div>';
}

// if safe mode is used then show a notice to the user
// deprecated: Remove if PHP 5.3 dropped
if (ini_get('safe_mode') === '1')
{
    $gLogger->warning('DEPRECATED: Safe-Mode is enabled!');
    $messageWarning .= '
        <div class="alert alert-warning alert-small" role="alert">
            <span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('INS_WARNING_SAFE_MODE').'
        </div>';
}

// create a page with the notice that the installation must be configured on the next pages
// create form with selectbox where user can select a language
$form = new HtmlFormInstallation('installation-form', 'installation.php?step=connect_database');
$form->setFormDescription($message, $gL10n->get('INS_WELCOME_TO_INSTALLATION'));

// the possible languages will be read from a xml file
$form->addSelectBoxFromXml(
    'system_language', $gL10n->get('INS_PLEASE_CHOOSE_LANGUAGE'), ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml',
    'isocode', 'name', array('defaultValue' => $gL10n->getLanguage())
);
$form->addDescription($messageWarning);

$form->addSubmitButton('next_page', $gL10n->get('INS_DATABASE_LOGIN'), array('icon' => 'layout/forward.png'));
echo $form->show();
