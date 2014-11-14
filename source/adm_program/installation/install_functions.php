<?php
/******************************************************************************
 * Common functions for update and installation
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

 /** A method to create a simple html page that shows a custom text and a navigation button. 
  *  This should be used to show notices or errors during installation or update.
  *  @param $message    A (html) message that should be displayed.
  *  @param $url        The url to which the user should be navigated if he clicks the button.
  *  @param $buttonText The text of the button.
  *  @param $buttonIcon The icon of the button.
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
    
    if($buttonText == $gL10n->get('INS_UPDATE_DATABASE'))
    {
        $onClickText = $gL10n->get('INS_DATABASE_IS_UPDATED');
    }
    
    $form->setFormDescription($message);
    $form->addSubmitButton('next_page', $buttonText, $buttonIcon, null, $onClickText);
    $form->show();
    exit();
}

// prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
function checkDatabaseVersion(&$db)
{
    global $gL10n;
    $message = '';

    // check database version
    if(version_compare($db->getVersion(), $db->getMinVersion()) == -1)
    {
        $message = $gL10n->get('SYS_DATABASE_VERSION').': <strong>'.$db->getVersion().'</strong><br /><br />'.
                   $gL10n->get('INS_WRONG_MYSQL_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT, $db->getMinVersion(), '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
    }

    return $message;
}

// prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
function checkPhpVersion()
{
    global $gL10n;
    $message = '';

    // check PHP version
    if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
    {
        $message = $gL10n->get('SYS_PHP_VERSION').': <strong>'.phpversion().'</strong><br /><br />'.
                   $gL10n->get('INS_WRONG_PHP_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT, MIN_PHP_VERSION, '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
    }

    return $message;
}

?>