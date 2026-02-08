<?php
global $gDb, $gL10n, $gProfileFields, $gCurrentUser;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Infrastructure\Language;

header('Content-Type: application/json; charset=utf-8');
$endpointName = 'event/filters';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

try {
    // Event Calendar type 'EVT'
    $catType = 'EVT';
    $calendars = array();
    $catTypeIds = $gCurrentUser->getAllVisibleCategories($catType);
    if (count($catTypeIds) !== 0) {       

            $placeholders = implode(',', array_fill(0, count($catTypeIds), '?'));

            $sql = 'SELECT DISTINCT cat_id, cat_org_id, cat_uuid, cat_name, cat_sequence FROM ' . TBL_CATEGORIES . '
                            WHERE cat_id IN (' . $placeholders . ') AND cat_type = ? ORDER BY cat_sequence ASC';
                    $queryParams = array_merge($catTypeIds, [$catType]);
                    $pdoStatement = $gDb->queryPrepared($sql, $queryParams, false);
                    if ($pdoStatement === false) {
                        admidioApiError('Database error', 500, [
                            'endpoint' => $endpointName,
                            'user_id' => $currentUserId,
                        ]);
                    }

            $calendars = array();

            while ($row = $pdoStatement->fetch()) {
                    // if text is a translation-id then translate it
                    $name = Language::translateIfTranslationStrId($row['cat_name']);
                    $calendars[] = ['id' => $row['cat_uuid'], 'name' => $name];
            }
    }

    $defaultDates = [
        'start' => date('Y-m-d'),
        'end' => date('Y-m-t')
    ];

    echo json_encode([
            'calendars' => $calendars,
            'dates' => $defaultDates
    ]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
        'endpoint' => $endpointName,
        'user_id' => $currentUserId,
        'exception' => get_class($exception)
    ]);
}

