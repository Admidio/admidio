<?php
/**
 ***********************************************************************************************
 * RSS feed of the latest 50 forum topics
 * Specification von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * organization : Short name of the organization whose topics should be shown in the RSS feed
 ***********************************************************************************************
 */

use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getOrganization = admFuncVariableIsValid($_GET, 'organization', 'string');

    // check if module is active
    if ($gSettingsManager->getInt('forum_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Show the RSS feed of the forum topics
    $forumService = new ForumService($gDb);
    $forumService->rssFeed($getOrganization);
} catch (Throwable $e) {
    echo $e->getMessage();
}
