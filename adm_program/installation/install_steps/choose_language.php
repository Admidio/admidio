<?php
/**
 ***********************************************************************************************
 * Installation step: choose_language
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'choose_language.php')
{
    exit('This page may not be called directly!');
}

session_destroy();

// create form with selectbox where user can select a language
// the possible languages will be read from a xml file
$form = new HtmlFormInstallation('installation-form', 'installation.php?step=welcome');
$form->openGroupBox('gbChooseLanguage', $gL10n->get('INS_CHOOSE_LANGUAGE'));
$form->addSelectBoxFromXml(
    'system_language', $gL10n->get('SYS_LANGUAGE'), ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml',
    'isocode', 'name', array('property' => FIELD_REQUIRED, 'defaultValue' => $gL10n->getLanguage())
);
$form->closeGroupBox();
$form->addSubmitButton('next_page', $gL10n->get('SYS_NEXT'), array('icon' => 'layout/forward.png'));
echo $form->show();
