<?php
global $gDb, $gCurrentUser;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
$endpointName = 'payment/list';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

try {
    // Permission check: admins can view all, regular users can only view their own payments
    $canViewAll = isResidentsAdmin() || isPaymentAdmin();
    
    $filters = [];
    // Always filter by organization
    $filters['org_id'] = $gCurrentOrgId;

    if (!$canViewAll) {
        $filters['current_user_id'] = $currentUserId;
    } else {
        // Admin filters
        $groupId = admFuncVariableIsValid($_GET, 'group_id', 'int');
        if ($groupId > 0) {
            $filters['filter_group'] = $groupId;
    }

        $userId = admFuncVariableIsValid($_GET, 'user_id', 'int');
        if ($userId > 0) {
            $filters['filter_user'] = $userId;
    }
    }

    $type = admFuncVariableIsValid($_GET, 'type', 'string');
    if ($type !== '') {
        $filters['filter_type'] = $type;
    }

    // Date filters apply to all users
    $startDate = admFuncVariableIsValid($_GET, 'start_date', 'string');
    if ($startDate !== '') {
        $filters['filter_start'] = $startDate;
    }

    $endDate = admFuncVariableIsValid($_GET, 'end_date', 'string');
    if ($endDate !== '') {
        $filters['filter_end'] = $endDate;
    }
    
    // Pagination
    $limit = admFuncVariableIsValid($_GET, 'limit', 'int', ['defaultValue' => 25]);
    $page = admFuncVariableIsValid($_GET, 'page', 'int', ['defaultValue' => 1]);
    $offset = admFuncVariableIsValid($_GET, 'offset', 'int', ['defaultValue' => ($page - 1) * $limit]);
    
    $options = [
    'profile_first_name_id' => (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    'profile_last_name_id' => (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    'length' => $limit,
    'offset' => $offset,
    'db_type' => $gDbType ?? 'mysql'
    ];

    $listResult = TableResidentsPayment::fetchList($gDb, $filters, $options);
    $rows = $listResult['rows'];
    $total = (int)($listResult['total'] ?? 0);
    $totalBase = (int)($listResult['total_base'] ?? 0);

    $payments = [];

    foreach ($rows as $row) {
        $payments[] = [
            'id' => (int)$row['rpa_id'],
            'user' => $row['user_name'],
            'date' => $row['rpa_date'],
            'pay_method' => $row['rpa_pg_pay_method'],
            'pay_type' => $row['rpa_pay_type'],
            'amount' => (float)$row['total_amount'],
            'currency' => $row['total_currency'] ?? $gSettingsManager->getString('system_currency'),
            'reference_no' => $row['rpa_bank_ref_no'] ?? null
        ];
    }

    echo json_encode([
    'payments' => $payments,
    'meta' => [
            'total' => $total,
            'total_unfiltered' => $totalBase,
            'limit' => $limit,
            'offset' => $offset,
            'page' => $page
    ]
    ]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ]);
}

