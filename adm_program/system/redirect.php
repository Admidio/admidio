<?php
/**
 ***********************************************************************************************
 * Redirect to chosen url
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * url - url that should be redirected
 *
 *****************************************************************************/

require_once(__DIR__ . '/common.php');

// Initialize and check the parameters
$getUrl = admFuncVariableIsValid($_GET, 'url', 'url', array('requireValue' => true));

if (filter_var($getUrl, FILTER_VALIDATE_URL) === false) {
    $gMessage->show($gL10n->get('SYS_REDIRECT_URL_INVALID'));
    // => EXIT
}

// create html page object
$page = new HtmlPage('admidio-redirect', $gL10n->get('SYS_REDIRECT'));

// add special header for automatic redirection after x seconds
$page->addHeader('<meta http-equiv="refresh" content="' . $gSettingsManager->getInt('weblinks_redirect_seconds') . '; url=' . $getUrl . '">');

// Counter zählt die sekunden bis zur Weiterleitung runter
$page->addJavascript(
    '
    /**
     * @param {bool} init
     */
    function countDown(init) {
        if (init || --document.getElementById("counter").firstChild.nodeValue > 0) {
            window.setTimeout("countDown()", 1000);
        }
    };
    countDown(true);'
);

// Html des Modules ausgeben
$page->addHtml(
    '<p class="lead">' .
        $gL10n->get(
            'SYS_REDIRECT_DESC',
            array($gCurrentOrganization->getValue('org_longname'),
            '<span id="counter">' . $gSettingsManager->getInt('weblinks_redirect_seconds') . '</span>',
            '<strong>' . $getUrl . '</strong>',
            '<a href="' . $getUrl . '" target="_self">',
            '</a>')
        ) .
    '</p>'
);

// show html of complete page
$page->show();
