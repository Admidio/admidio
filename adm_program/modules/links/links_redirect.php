<?php
/**
 ***********************************************************************************************
 * Redirect to chosen weblink
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid   - UUID of the weblink that should be redirected
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Weblinks\Entity\Weblink;

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'uuid');

    // check if the module is enabled for use
    if ((int)$gSettingsManager->get('enable_weblinks_module') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }
    if ((int)$gSettingsManager->get('enable_weblinks_module') === 2) {
        // available only with valid login
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // read link from id
    $weblink = new Weblink($gDb);
    $weblink->readDataByUuid($getLinkUuid);
    $lnkUrl = $weblink->getValue('lnk_url');

    // if no link is set or the weblink is not visible to the user show error
    if (strlen($lnkUrl) === 0 || !$weblink->isVisible()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // If link is valid, increase counter by one position
    $weblink->setValue('lnk_counter', (int)$weblink->getValue('lnk_counter') + 1);
    $weblink->saveChangesWithoutRights();
    $weblink->save();

    // direct forwarding or show page with notice of redirection
    if ($gSettingsManager->getInt('weblinks_redirect_seconds') > 0) {
        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-weblinks-redirect', $gL10n->get('SYS_REDIRECT'));

        // add special header for automatic redirection after x seconds
        $page->addHeader('<meta http-equiv="refresh" content="' . $gSettingsManager->getInt('weblinks_redirect_seconds') . '; url=' . $lnkUrl . '">');

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
        <p class="lead">' . $gL10n->get('SYS_REDIRECT_DESC', array($gCurrentOrganization->getValue('org_longname'),
                    '<span id="counter">' . $gSettingsManager->getInt('weblinks_redirect_seconds') . '</span>',
                    '<strong>' . $weblink->getValue('lnk_name') . '</strong> (' . $lnkUrl . ')',
                    '<a href="' . $lnkUrl . '" target="_self">', '</a>')) . '
        </p>');

        $page->show();
        // => EXIT
    } else {
        admRedirect($lnkUrl);
        // => EXIT
    }
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
