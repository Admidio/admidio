<?php
global $gDb, $gL10n, $gProfileFields, $gCurrentUser;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');

header('Content-Type: application/json; charset=utf-8');
$endpointName = 'invoice/filters';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

try {
    // Permission check: only residents admin or payment admin
    $canViewAll = isResidentsAdmin() || isPaymentAdmin();

    $groups = [];
    $users  = [];

    if ($canViewAll) {
        // 1. Groups 
        $allRoles = residentsGetRoleOptions();
        foreach ($allRoles as $id => $name) {
            $groups[] = [
        'id'   => (int)$id,
        'name' => $name
            ];
    }

        // 2. Users (filtered by group)
        $filterGroupId = admFuncVariableIsValid($_GET, 'group_id', 'int');

        $firstNameFieldId = (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
        $lastNameFieldId  = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');

        $allUsers = TableResidentsPayment::fetchUserOptions(
            $gDb,
            true,
            $firstNameFieldId,
            $lastNameFieldId,
            $currentUserId,
            $filterGroupId
        );

        foreach ($allUsers as $id => $name) {
            $users[] = [
        'id'   => (int)$id,
        'name' => $name
            ];
    }
    }

    $defaultDates = [
            'start' => date('Y-m-01'),
            'end' => date('Y-m-t')
    ];

    echo json_encode([
    'is_admin' => $canViewAll,
    'groups'   => $groups,
    'users'    => $users,
    'dates'    => $defaultDates
    ]);

} catch (Exception $exception) {
    admidioApiError(
    $exception->getMessage(),
    500,
    [
            'endpoint'  => $endpointName,
            'user_id'   => $currentUserId,
            'exception' => get_class($exception)
    ]
    );
}
