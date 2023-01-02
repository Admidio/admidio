<?php
/**
 ***********************************************************************************************
 * Redirect to chosen weblink
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid   - UUID of the weblink that should be redirected
 *
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'string');

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0) {
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
if ((int) $gSettingsManager->get('enable_weblinks_module') === 2) {
    // available only with valid login
    require(__DIR__ . '/../../system/login_valid.php');
}

// read link from id
$weblink = new TableWeblink($gDb);
$weblink->readDataByUuid($getLinkUuid);
$lnkUrl = $weblink->getValue('lnk_url');

// if no link is set or the weblink is not visible to the user show error
if (strlen($lnkUrl) === 0 || !$weblink->isVisible()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

try {
    // If link is valid, increase counter by one position
    $weblink->setValue('lnk_counter', (int) $weblink->getValue('lnk_counter') + 1);
    $weblink->saveChangesWithoutRights();
    $weblink->save();
} catch (AdmException $e) {
    $e->showHtml();
}

// direct forwarding or show page with notice of redirection
if ($gSettingsManager->getInt('weblinks_redirect_seconds') > 0) {
    // create html page object
    $page = new HtmlPage('admidio-weblinks-redirect', $gL10n->get('SYS_REDIRECT'));

    // add special header for automatic redirection after x seconds
    $page->addHeader('<meta http-equiv="refresh" content="'. $gSettingsManager->getInt('weblinks_redirect_seconds').'; url='.$lnkUrl.'">');

    // Counter counts down the seconds until forwarding
    $page->addJavascript('
        /**
         * @param {bool} init
         */
        function countDown(init) {
            if (init || --document.getElementById("counter").firstChild.nodeValue > 0 ) {
                window.setTimeout( "countDown()" , 1000 );
            }
        };
        countDown(true);
    ');

    $page->addHtml('
    <p class="lead">'.$gL10n->get('SYS_REDIRECT_DESC', array($gCurrentOrganization->getValue('org_longname'),
        '<span id="counter">'.$gSettingsManager->getInt('weblinks_redirect_seconds').'</span>',
        '<strong>'.$weblink->getValue('lnk_name').'</strong> ('.$lnkUrl.')',
        '<a href="'.$lnkUrl.'" target="_self">', '</a>')).'
    </p>');

    $page->show();
} else {
    admRedirect($lnkUrl);
    // => EXIT
}
