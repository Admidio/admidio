<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of invoices for the current user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gDb, $gCurrentUser, $gCurrentOrgId, $gProfileFields, $gDbType;

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');

header('Content-Type: application/json; charset=utf-8');

$endpointName = 'invoice/list';

try {
    // -------------------------------------------------------------------------
    // AUTH
    // -------------------------------------------------------------------------
    $currentUser   = validateApiKey();
    $currentUserId = (int) $currentUser->getValue('usr_id');

    // -------------------------------------------------------------------------
    // PERMISSION
    // -------------------------------------------------------------------------
    $canViewAll = isResidentsAdmin() || isPaymentAdmin();

    // -------------------------------------------------------------------------
    // FILTERS
    // -------------------------------------------------------------------------
    $filters = [];
    $filters['org_id'] = $gCurrentOrgId;

    if (!$canViewAll) {
        $filters['current_user_id'] = $currentUserId;
    } else {
        // ---------------- ADMIN FILTERS ----------------
        // Group filter
        $groupId = admFuncVariableIsValid($_GET, 'group_id', 'int');
        if ($groupId > 0) {
            $filters['filter_group'] = $groupId;
    }

        // User filter
        $userId = admFuncVariableIsValid($_GET, 'user_id', 'int');
        if ($userId > 0) {
            $filters['filter_user'] = $userId;
    }

        $search = admFuncVariableIsValid($_GET, 'search', 'string');
        if ($search !== '') {
        $filters['search'] = trim($search);
    }
    }
    
    // for mobile payment page 
    if (!empty($_GET['only_payable'])) {
        $filters['filter_paid'] = 0;
    }

    // Date Filters
    $startDate = admFuncVariableIsValid($_GET, 'start_date', 'string');
    $endDate   = admFuncVariableIsValid($_GET, 'end_date', 'string');

    if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $filters['date_from'] = $startDate;
    }

    if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $filters['date_to'] = $endDate;
    }

    // Status Filter
    $status = admFuncVariableIsValid($_GET, 'status', 'string');
    if ($status === 'paid') {
        $filters['filter_paid'] = 1;
    } elseif ($status === 'unpaid') {
        $filters['filter_paid'] = 0;
    }

    // -------------------------------------------------------------------------
    // PAGINATION
    // -------------------------------------------------------------------------
    $limit = admFuncVariableIsValid($_GET, 'limit', 'int', ['defaultValue' => 25]);
    $page  = admFuncVariableIsValid($_GET, 'page', 'int', ['defaultValue' => 1]);
    
    // Explicitly calculate offset to ensure page 1 = offset 0
    $offset = ($page - 1) * $limit;

    // -------------------------------------------------------------------------
    // OPTIONS
    // -------------------------------------------------------------------------
    $options = [
    'profile_first_name_id' => $gProfileFields ? (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id') : 0,
    'profile_last_name_id'  => $gProfileFields ? (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id') : 0,
    'length'                => $limit,
    'offset'                => $offset,
    'db_type'               => $gDbType ?? 'mysql'
    ];

    // -------------------------------------------------------------------------
    // FETCH DATA
    // -------------------------------------------------------------------------
    $filters['sort_col'] = 'date';
    $filters['sort_dir'] = 'DESC';

    $listResult = TableResidentsInvoice::fetchList($gDb, $filters, $options);

    $rows      = $listResult['rows'] ?? [];
    $total     = (int) ($listResult['total'] ?? 0);
    $totalBase = (int) ($listResult['total_base'] ?? 0);
    $currencyFallback = $gSettingsManager->getString('system_currency');
    // -------------------------------------------------------------------------
    // RESPONSE MAPPING
    // -------------------------------------------------------------------------
    $invoices = [];

    foreach ($rows as $row) {
        $isPaid = (int) ($row['riv_is_paid'] ?? 0) === 1;
        $ownsInvoice = ((int)($row['riv_usr_id'] ?? 0) === $currentUserId);
        $currencySymbol = !empty($row['total_currency'])
        ? (string)$row['total_currency']
        : (string)$currencyFallback;

        $invoices[] = [
            'id'         => (int) $row['riv_id'],
            'user'       => $row['user_name'],
            'user_id'    => (int) ($row['riv_usr_id'] ?? 0), // Added for frontend consistency
            'number'     => $row['riv_number'],
            'date'       => residentsFormatDateForApi((string)($row['riv_date'] ?? '')),
            'start_date' => residentsFormatDateForApi((string)($row['riv_start_date'] ?? '')),
            'end_date'   => residentsFormatDateForApi((string)($row['riv_end_date'] ?? '')),
            'due_date'   => residentsFormatDateForApi((string)($row['riv_due_date'] ?? '')),
            'amount'     => (float) $row['total_amount'],
            'currency_symbol' => $currencySymbol,
            'is_paid'    => $isPaid ? 'Paid' : 'Unpaid',
            'can_pay'         => ($ownsInvoice && !$isPaid),
        ];
    }

    // -------------------------------------------------------------------------
    // OUTPUT
    // -------------------------------------------------------------------------
    echo json_encode([
    'invoices' => $invoices,
    'meta' => [
            'total'            => $total,
            'total_unfiltered' => $totalBase,
            'limit'            => $limit,
            'offset'           => $offset,
            'page'             => $page
    ]
    ]);

} catch (Exception $exception) {
    admidioApiError(
    $exception->getMessage(),
    500,
    [
            'endpoint'  => $endpointName,
            'user_id'   => isset($currentUserId) ? $currentUserId : 0,
            'exception' => get_class($exception)
    ]
    );
}