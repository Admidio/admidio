<?php
/**
 ***********************************************************************************************
 * API endpoint to return language translations for the mobile app
 *
 * Returns all translation strings for a specified language from the residents plugin
 * language files and optionally from the main Admidio language files.
 *
 * Parameters:
 *   lang - Language code (e.g., 'en', 'de', 'fr'). Defaults to 'en' if not specified.
 *   prefix - Optional. Filter strings by prefix (e.g., 'RE_' for residents, 'MOB_' for mobile)
 *   include_main - Optional. If '1', include strings from main Admidio language files.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');

header('Content-Type: application/json; charset=utf-8');

// Validate API key for authenticated access
validateApiKey();

// Get parameters
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'en';
$prefix = isset($_GET['prefix']) ? trim($_GET['prefix']) : '';
$includeMain = isset($_GET['include_main']) && $_GET['include_main'] === '1';

// Sanitize language code to prevent directory traversal
$lang = preg_replace('/[^a-zA-Z0-9_-]/', '', $lang);

// Define language file paths
$residentsLangPath = __DIR__ . '/../../languages/' . $lang . '.xml';
$mainLangPath = ADMIDIO_PATH . '/languages/' . $lang . '.xml';

// Fallback to English if specified language doesn't exist in residents plugin
$fallbackLang = 'en';
$actualLang = $lang; // Track which language is actually being returned

if (!file_exists($residentsLangPath)) {
    $residentsLangPath = __DIR__ . '/../../languages/' . $fallbackLang . '.xml';
    $actualLang = $fallbackLang; // Update to reflect fallback was used
}
if (!file_exists($mainLangPath)) {
    $mainLangPath = ADMIDIO_PATH . '/languages/' . $fallbackLang . '.xml';
}

/**
 * Parse an XML language file and return an associative array of translations
 *
 * @param string $filePath Path to the XML language file
 * @param string $prefix Optional prefix to filter strings
 * @return array Associative array of translation key => value
 */
function parseLanguageFile(string $filePath, string $prefix = ''): array
{
    $translations = [];

    if (!file_exists($filePath)) {
        return $translations;
    }

    // Suppress warnings for invalid XML and handle errors gracefully
    $previousUseErrors = libxml_use_internal_errors(true);
    
    $xml = simplexml_load_file($filePath);
    
    if ($xml === false) {
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);
        return $translations;
    }

    foreach ($xml->string as $string) {
        $name = (string) $string['name'];
        $value = (string) $string;
        
        // If prefix is specified, only include matching strings
        if ($prefix === '' || strpos($name, $prefix) === 0) {
            $translations[$name] = $value;
        }
    }

    libxml_use_internal_errors($previousUseErrors);
    return $translations;
}

// Parse residents plugin language file (mobile-specific labels)
$translations = parseLanguageFile($residentsLangPath, $prefix);

// Optionally include main Admidio language strings
if ($includeMain) {
    $mainTranslations = parseLanguageFile($mainLangPath, $prefix);
    // Merge with residents translations taking precedence
    $translations = array_merge($mainTranslations, $translations);
}

// Get list of available languages
$availableLanguages = [];
$residentsLangDir = __DIR__ . '/../../languages/';
$mainLangDir = ADMIDIO_PATH . '/languages/';

// Scan residents plugin languages
if (is_dir($residentsLangDir)) {
    $files = glob($residentsLangDir . '*.xml');
    foreach ($files as $file) {
        $langCode = pathinfo($file, PATHINFO_FILENAME);
        // Skip country files
        if (strpos($langCode, 'countries-') === false) {
            $availableLanguages[$langCode] = $langCode;
        }
    }
}

// Also scan main languages for reference
if ($includeMain && is_dir($mainLangDir)) {
    $files = glob($mainLangDir . '*.xml');
    foreach ($files as $file) {
        $langCode = pathinfo($file, PATHINFO_FILENAME);
        // Skip country files
        if (strpos($langCode, 'countries-') === false) {
            $availableLanguages[$langCode] = $langCode;
        }
    }
}

// Return response
$response = [
    'requested_language' => $lang,
    'language' => $actualLang,
    'fallback' => $fallbackLang,
    'used_fallback' => ($actualLang !== $lang),
    'translations' => $translations,
    'count' => count($translations),
    'available_languages' => array_values($availableLanguages)
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
