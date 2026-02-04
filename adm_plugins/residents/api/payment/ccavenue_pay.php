<?php
/**
    * Mobile CCAvenue start API (HTML response)
    */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_common.php';

global $gCurrentUser;


$gCurrentUser = validateApiKey();

$userId = (int)$gCurrentUser->getValue('usr_id');

// Read invoice IDs from POST body only
$invoiceIds = $_POST['invoice_ids'] ?? [];
$invoiceIds = array_map('intval', (array)$invoiceIds);

// Render payment form
renderCcavenueForMobile($invoiceIds, $userId);
