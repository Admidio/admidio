<?php
/**
 ***********************************************************************************************
 * Common functions for update and installation
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * A method to create a simple html page that shows a custom text and a navigation button.
 * This should be used to show notices or errors during installation or update.
 * @param string $message    A (html) message that should be displayed.
 * @param string $url        The url to which the user should be navigated if he clicks the button.
 * @param string $buttonText The text of the button.
 * @param string $buttonIcon The icon of the button.
 * @param bool   $update
 */
function showNotice($message, $url, $buttonText, $buttonIcon, $update = false)
{
    global $gL10n;

    $onClickText = '';

    // show dialog with success notification
    $form = new HtmlFormInstallation('installation-form', $url);

    if($update)
    {
        $form->setUpdateModus();
    }

    if($buttonText === $gL10n->get('INS_UPDATE_DATABASE'))
    {
        $onClickText = $gL10n->get('INS_DATABASE_IS_UPDATED');
    }

    $form->setFormDescription($message);
    $form->addSubmitButton('next_page', $buttonText, array('icon' => $buttonIcon, 'onClickText' => $onClickText));
    echo $form->show();
    exit();
}

/**
 * prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
 * @param \Database $db
 * @return string
 */
function checkDatabaseVersion(&$db)
{
    global $gL10n;

    $message = '';

    // check database version
    if(version_compare($db->getVersion(), $db->getMinimumRequiredVersion(), '<'))
    {
        $message = $gL10n->get('SYS_DATABASE_VERSION').': <strong>'.$db->getVersion().'</strong><br /><br />'.
                   $gL10n->get('INS_WRONG_MYSQL_VERSION', ADMIDIO_VERSION_TEXT, $db->getMinimumRequiredVersion(),
                               '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
    }

    return $message;
}

/**
 * prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
 * @return string
 */
function checkPhpVersion()
{
    global $gL10n;
    $message = '';

    // check PHP version
    if(version_compare(phpversion(), MIN_PHP_VERSION, '<'))
    {
        $message = $gL10n->get('SYS_PHP_VERSION').': <strong>'.phpversion().'</strong><br /><br />'.
                   $gL10n->get('INS_WRONG_PHP_VERSION', ADMIDIO_VERSION_TEXT, MIN_PHP_VERSION,
                               '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
    }

    return $message;
}
