<?php
/**
 ***********************************************************************************************
 * Installation step: welcome
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\View\Installation;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'welcome.php') {
    exit('This page may not be called directly!');
}

if ($mode === 'html') {
    // create a page with the notice that the installation must be configured on the next pages
    // create form with select box where user can select a language
    $page = new Installation('adm_installation_welcome', $gL10n->get('INS_INSTALLATION'));
    $page->addTemplateFile('installation.tpl');
    $page->assignSmartyVariable('subHeadline', $gL10n->get('INS_WELCOME_TO_INSTALLATION'));
    $page->assignSmartyVariable('text', $gL10n->get(
        'INS_WELCOME_TEXT',
        array(
            '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:installation" target="_blank">', '</a>',
            '<a href="https://www.admidio.org/forum" target="_blank">', '</a>'
        )
    ));

    $form = new FormPresenter(
        'adm_installation_welcome_form',
        'installation.welcome.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'welcome', 'mode' => 'check')),
        $page
    );

    // the possible languages will be read from a xml file
    $form->addSelectBox(
        'adm_system_language',
        $gL10n->get('INS_PLEASE_CHOOSE_LANGUAGE'),
        $gL10n->getAvailableLanguages(),
        array('defaultValue' => $gL10n->getLanguage(), 'showContextDependentFirstEntry' => false)
    );
    $form->addSubmitButton(
        'adm_next_page',
        $gL10n->get('INS_DATABASE_LOGIN'),
        array('icon' => 'bi-arrow-right-circle-fill', 'class' => 'float-end')
    );

    $page->assignSmartyVariable('admidioBetaVersion', ADMIDIO_VERSION_BETA);
    $form->addToHtmlPage();
    $_SESSION['installationWelcomeForm'] = $form;

    $page->show();
} elseif ($mode === 'check') {
    // check form field input and sanitized it from malicious content
    if (isset($_SESSION['installationWelcomeForm'])) {
        $_SESSION['installationWelcomeForm']->validate($_POST);
    } else {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if (isset($_POST['adm_system_language']) && trim($_POST['adm_system_language']) !== '') {
        $_SESSION['language'] = $_POST['adm_system_language'];
        $gL10n->setLanguage($_SESSION['language']);
    } elseif (!isset($_SESSION['language'])) {
        throw new Exception('INS_LANGUAGE_NOT_CHOSEN');
    }

    echo json_encode(array(
        'status' => 'success',
        'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'connect_database'))));
    exit();
}
