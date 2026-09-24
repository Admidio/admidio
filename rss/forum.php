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

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getOrganization = admFuncVariableIsValid($_GET, 'organization', 'string');

    // The service checks RSS and module access for the requested organization.
    $forumService = new ForumService($gDb);
    $forumService->rssFeed($getOrganization);
} catch (Throwable $e) {
    handleException($e);
}
