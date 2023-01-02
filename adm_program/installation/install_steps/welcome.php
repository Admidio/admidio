<?php
/**
 ***********************************************************************************************
 * Installation step: welcome
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'welcome.php') {
    exit('This page may not be called directly!');
}

// create a page with the notice that the installation must be configured on the next pages
// create form with selectbox where user can select a language
$page = new HtmlPageInstallation('admidio-installation-welcome');
$page->addTemplateFile('installation.tpl');
$page->assign('subHeadline', $gL10n->get('INS_WELCOME_TO_INSTALLATION'));
$page->assign('text', $gL10n->get(
    'INS_WELCOME_TEXT',
    array(
            '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:installation" target="_blank">', '</a>',
            '<a href="https://www.admidio.org/forum" target="_blank">', '</a>'
        )
));


$form = new HtmlForm('installation-form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'connect_database')));

// the possible languages will be read from a xml file
$form->addSelectBox(
    'system_language',
    $gL10n->get('INS_PLEASE_CHOOSE_LANGUAGE'),
    $gL10n->getAvailableLanguages(),
    array('defaultValue' => $gL10n->getLanguage(), 'showContextDependentFirstEntry' => false)
);

// if this is a beta version then show a notice to the user
if (ADMIDIO_VERSION_BETA > 0) {
    $gLogger->notice('INSTALLATION: This is a BETA release!');

    $form->addDescription(
        '<div class="alert alert-warning alert-small" role="alert">
            <i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('INS_WARNING_BETA_VERSION').'
        </div>'
    );
}

$form->addSubmitButton('next_page', $gL10n->get('INS_DATABASE_LOGIN'), array('icon' => 'fa-arrow-circle-right', 'class' => 'float-right'));

$page->addHtml($form->show());
$page->addHtml('<br /><br />');
$page->show();
