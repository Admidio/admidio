<?php
/**
 ***********************************************************************************************
 * Manage token based calendar feeds of the current user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode - html   : (Default) show the list of feeds and the form to create a new feed
 *        create : create a new feed and show the token once
 *        revoke : revoke one of the user's feeds
 * cat_uuid - optional: preselect this visible calendar in the create form
 ***********************************************************************************************
 */

use Admidio\Events\Service\CalendarFeedService;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\DateTimeUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    header('Content-type: text/html; charset=utf-8');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'html',
        'validValues' => array('html', 'create', 'revoke')
    ));
    $getCatUuid = admFuncVariableIsValid($_GET, 'cat_uuid', 'uuid');

    $orgId = (int)$gCurrentOrgId;
    $userId = (int)$gCurrentUserId;
    $feedService = new CalendarFeedService($gDb);
    $pageUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/calendar_feeds.php');
    $pageParams = $getCatUuid !== '' ? array('cat_uuid' => $getCatUuid) : array();
    $isAjaxResponse = false;
    $newFeed = null;
    $message = '';

    if ($getMode === 'create') {
        $createForm = $gCurrentSession->getFormObject((string)($_POST['adm_csrf_token'] ?? ''));
        $formValues = $createForm->validate($_POST);

        $label = trim((string)($formValues['cfd_name'] ?? ''));
        $categoryUuid = (string)($formValues['cfd_cat_uuid'] ?? '');
        $expires = null;
        $expiresValue = trim((string)($formValues['cfd_expires'] ?? ''));
        if ($expiresValue !== '') {
            $expires = DateTimeUtils::parseDate($expiresValue);
            if ($expires === null) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_CALENDAR_FEED_EXPIRES', 'YYYY-MM-DD'));
            }
            $expires->setTime(23, 59, 59);
        }

        $createdFeed = $feedService->createFeed($orgId, $userId, $label, $expires, $categoryUuid);
        $newFeed = array(
            'name' => $label,
            'token' => $createdFeed['token'],
            'url' => $createdFeed['url']
        );
        $isAjaxResponse = true;
    }

    if ($getMode === 'revoke') {
        SecurityUtils::validateCsrfToken((string)($_POST['adm_csrf_token'] ?? ''));
        $feedId = admFuncVariableIsValid($_POST, 'feed_id', 'int', array('requireValue' => true));
        $feedService->revokeFeed($feedId, $userId, $orgId);
        $message = $gL10n->get('SYS_CALENDAR_FEED_REVOKED');
        $isAjaxResponse = true;
    }

    // Read visible calendars for the optional binding of a token to one calendar.
    $calendarValues = array();
    $visibleCategoryUuids = $gCurrentUser->getAllVisibleCategories('EVT', 'uuid');
    if (count($visibleCategoryUuids) > 0) {
        $sql = 'SELECT cat_uuid, cat_name
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ?
                   AND cat_uuid IN (' . Database::getQmForValues($visibleCategoryUuids) . ')
              ORDER BY cat_sequence';
        $statement = $gDb->queryPrepared($sql, array_merge(array('EVT'), $visibleCategoryUuids));
        foreach ($statement->fetchAll() as $category) {
            $categoryName = (string)$category['cat_name'];
            if (Language::isTranslationStringId($categoryName)) {
                $categoryName = $gL10n->get($categoryName);
            }
            $calendarValues[$category['cat_uuid']] = $categoryName;
        }
    }

    $defaultCategoryUuid = $getCatUuid !== '' && array_key_exists($getCatUuid, $calendarValues) ? $getCatUuid : '';

    $feeds = $feedService->listFeeds($orgId, $userId);
    $dateFormat = $gSettingsManager->getString('system_date');
    $timeFormat = $gSettingsManager->getString('system_time');
    foreach ($feeds as &$feed) {
        $feed['cfd_active'] = filter_var($feed['cfd_active'], FILTER_VALIDATE_BOOLEAN);
        if ($feed['cat_name'] !== null && Language::isTranslationStringId((string)$feed['cat_name'])) {
            $feed['cat_name'] = $gL10n->get((string)$feed['cat_name']);
        }
        $expiresTimestamp = empty($feed['cfd_timestamp_expires']) ? false : strtotime((string)$feed['cfd_timestamp_expires']);
        $feed['is_expired'] = $expiresTimestamp !== false && $expiresTimestamp <= time();
        $feed['url'] = $feedService->buildFeedUrl($gCurrentOrganization, (string)$feed['cfd_cat_uuid']);
        $feed['create_display'] = admCalendarFeedFormatTimestamp($feed['cfd_timestamp_create'], $dateFormat, $timeFormat);
        $feed['last_used_display'] = admCalendarFeedFormatTimestamp($feed['cfd_timestamp_last_used'], $dateFormat, $timeFormat);
        $feed['expires_display'] = admCalendarFeedFormatTimestamp($feed['cfd_timestamp_expires'], $dateFormat, $timeFormat);
    }
    unset($feed);

    $template = 'modules/profile.calendar-feeds.tpl';
    $createForm = new FormPresenter(
        'adm_calendar_feed_create_form',
        $template,
        SecurityUtils::encodeUrl($pageUrl, array_merge($pageParams, array('mode' => 'create'))),
        options: array('type' => 'vertical')
    );
    $createForm->addInput(
        'cfd_name',
        $gL10n->get('SYS_CALENDAR_FEED_NAME'),
        '',
        array('property' => FormPresenter::FIELD_REQUIRED, 'maxLength' => 255)
    );
    $createForm->addSelectBox(
        'cfd_cat_uuid',
        $gL10n->get('SYS_CALENDAR'),
        $calendarValues,
        array(
            'defaultValue' => $defaultCategoryUuid,
            'showContextDependentFirstEntry' => true,
            'firstEntry' => $gL10n->get('SYS_ALL_CALENDARS')
        )
    );
    $createForm->addInput(
        'cfd_expires',
        $gL10n->get('SYS_CALENDAR_FEED_EXPIRES'),
        '',
        array('type' => 'date', 'maxLength' => 10, 'helpTextId' => 'SYS_CALENDAR_FEED_EXPIRES_DESC')
    );
    $createForm->addSubmitButton(
        'adm_button_create_feed',
        $gL10n->get('SYS_CALENDAR_FEED_ADD'),
        array('icon' => 'bi-plus-circle-fill')
    );

    $smarty = PagePresenter::createSmartyObject();
    $createForm->addToSmarty($smarty);
    $gCurrentSession->addFormObject($createForm);

    $smarty->assign('feeds', $feeds);
    $smarty->assign('newFeed', $newFeed);
    $smarty->assign('message', $message);
    $smarty->assign('loginName', $gCurrentUser->getValue('usr_login_name'));
    $smarty->assign('urlRevoke', SecurityUtils::encodeUrl($pageUrl, array_merge($pageParams, array('mode' => 'revoke'))));
    $smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());

    if ($isAjaxResponse) {
        echo $smarty->fetch('modules/profile.calendar-feeds.content.tpl');
    } else {
        echo $smarty->fetch($template);
    }
} catch (Throwable $e) {
    $isAjaxResponse = isset($getMode) && in_array($getMode, array('create', 'revoke'), true);
    if (!$isAjaxResponse) {
        $gMessage->showInModalWindow();
    }
    handleException($e, $isAjaxResponse);
}

/**
 * Format a database timestamp for display. Empty or invalid values are shown
 * as an empty string, the template decides which placeholder to use.
 */
function admCalendarFeedFormatTimestamp(mixed $timestamp, string $dateFormat, string $timeFormat): string
{
    if ($timestamp === null || $timestamp === '' || $timestamp === false) {
        return '';
    }

    $dateTime = DateTimeUtils::parseDateTime((string)$timestamp);
    if ($dateTime === null) {
        return '';
    }

    return $dateTime->format($dateFormat . ' ' . $timeFormat);
}
