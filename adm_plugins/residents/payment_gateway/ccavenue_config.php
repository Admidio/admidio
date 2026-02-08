<?php
/**
    * CCAvenue Configuration File
    *
    * Contains merchant credentials and configuration for CCAvenue payment gateway
    * Fetched dynamically from database settings
    */

// Fetch configuration from database
$residentsConfig = residentsReadConfig();
$pgConf = $residentsConfig['payment_gateway'] ?? array();

// CCAvenue Credentials
if (!defined('CCAVENUE_ACCESS_CODE')) {
    define('CCAVENUE_ACCESS_CODE', $pgConf['access_code'] ?? '');
}
if (!defined('CCAVENUE_WORKING_KEY')) {
    define('CCAVENUE_WORKING_KEY', $pgConf['working_key'] ?? '');
}
if (!defined('CCAVENUE_MERCHANT_ID')) {
    define('CCAVENUE_MERCHANT_ID', $pgConf['merchant_id'] ?? '');
}

// CCAvenue API URL
if (!defined('CCAVENUE_API_URL')) {
    define('CCAVENUE_API_URL', $pgConf['gateway_url'] ?? '');
}
