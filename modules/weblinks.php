<?php
/**
 * Display and manage weblinks.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\WeblinksPresenter;
use Admidio\Weblinks\Service\WeblinksService;

$getMode = 'cards';
try {
    require_once(__DIR__ . '/../system/common.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'cards',
        'validValues' => array('cards', 'edit', 'save', 'delete', 'sequence', 'redirect')
    ));
    $getLinkUuid = admFuncVariableIsValid($_GET, $getMode === 'sequence' ? 'uuid' : 'link_uuid', 'uuid');
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');

    $moduleEnabled = $gSettingsManager->getInt('weblinks_module_enabled');
    if ($moduleEnabled === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }
    if ($moduleEnabled === 2 || !in_array($getMode, array('cards', 'redirect'), true)) {
        require(__DIR__ . '/../system/login_valid.php');
    }

    switch ($getMode) {
        case 'cards':
            $page = new WeblinksPresenter();
            $page->createCards($getCatUuid, $getLinkUuid, $getStart);
            if ($getLinkUuid !== '') {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            } else {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-link-45deg');
            }
            $page->show();
            break;

        case 'edit':
            $page = new WeblinksPresenter();
            $page->createEditForm($getLinkUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save':
            (new WeblinksService($gDb))->save($getLinkUuid);
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            (new WeblinksService($gDb))->delete($getLinkUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            (new WeblinksService($gDb))->move($getLinkUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'redirect':
            $url = (new WeblinksService($gDb))->visit($getLinkUuid);
            if ($gSettingsManager->getInt('weblinks_redirect_seconds') > 0) {
                $page = new WeblinksPresenter();
                $page->createRedirectPage($getLinkUuid, $url);
                $page->show();
            } else {
                admRedirect($url);
            }
            break;
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('save', 'delete', 'sequence'), true));
}
