<?php
/**
 ***********************************************************************************************
 * API endpoint to return detailed contact information for a specific user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Users\Entity\User;
use Admidio\ProfileFields\ValueObjects\ProfileFields;

$currentUser = validateApiKey();
$userId = $_GET['contact_id'] ?? '';
$picPath = THEME_PATH. '/images/no_profile_pic.png';

function buildProfileSections(User $currentUser, User $user, ProfileFields $profileFields): array
{
    global $gL10n;

    $sectionsByKey = [];

    // Ensure BASIC_DATA section exists first (web shows it first)
    $basicTitle = $gL10n->get('SYS_BASIC_DATA');
    foreach ($profileFields->getProfileFields() as $field) {
        if ($field->getValue('cat_name_intern') === 'BASIC_DATA') {
            $basicTitle = $field->getValue('cat_name') ?: $basicTitle;
            break;
    }
    }

    $sectionsByKey['BASIC_DATA'] = [
    'key' => 'BASIC_DATA',
    'title' => $basicTitle,
    'items' => [],
    ];

    $loginName = (string) $user->getValue('usr_login_name');
    if ($loginName !== '') {
        $sectionsByKey['BASIC_DATA']['items'][] = [
            'key' => 'usr_login_name',
            'label' => $gL10n->get('SYS_USERNAME'),
            'value' => $loginName,
            'type' => 'TEXT',
        ];
    }

    foreach ($profileFields->getProfileFields() as $field) {
        $fieldNameIntern = $field->getValue('usf_name_intern');
        if (!$fieldNameIntern) {
            continue;
    }

        if (!$currentUser->allowedViewProfileField($user, $fieldNameIntern)) {
            continue;
    }

        $fieldType = (string) $field->getValue('usf_type');
        $label = (string) $field->getValue('usf_name');
        $displayValue = $user->getValue($fieldNameIntern);

        $shouldShow = false;
        if ($fieldType === 'CHECKBOX') {
            $dbValue = $user->getValue($fieldNameIntern, 'database');
            $truthy = (string) $dbValue === '1' || $dbValue === 1 || $dbValue === true;
            $displayValue = $truthy ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO');
            $shouldShow = true;
    } else {
            $shouldShow = (string) $displayValue !== '';
    }

        if (!$shouldShow) {
            continue;
    }

        $categoryKey = (string) $field->getValue('cat_name_intern');
        $categoryTitle = (string) $field->getValue('cat_name');
        if ($categoryKey === '') {
            $categoryKey = $categoryTitle !== '' ? $categoryTitle : 'DETAILS';
    }
        if ($categoryTitle === '') {
            $categoryTitle = $categoryKey;
    }

        if (!isset($sectionsByKey[$categoryKey])) {
            $sectionsByKey[$categoryKey] = [
        'key' => $categoryKey,
        'title' => $categoryTitle,
        'items' => [],
            ];
    }

        $sectionsByKey[$categoryKey]['items'][] = [
            'key' => $fieldNameIntern,
            'label' => $label,
            'value' => (string) $displayValue,
            'type' => $fieldType,
        ];
    }

    // Remove any empty sections (except BASIC_DATA which might contain only login)
    $sections = [];
    foreach ($sectionsByKey as $section) {
        if (!empty($section['items'])) {
            $sections[] = $section;
    }
    }

    return $sections;
}

function encodeProfileImage(string $binary): array
{
    if ($binary === '') {
        return [
            'profile' => null,
            'profile_mime' => null,
            'profile_has_image' => false,
        ];
    }

    $mime = 'image/jpeg';
    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo) {
        $detected = @finfo_buffer($finfo, $binary);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
    }
        @finfo_close($finfo);
    }

    return [
    'profile' => base64_encode($binary),
    'profile_mime' => $mime,
    'profile_has_image' => true,
    ];
}

$contact = (object)[];

$requestedId = (int) $userId;
if ($requestedId <= 0) {
    admidioApiError('Contact identifier missing', 400);
}

$user = new User($gDb, $gProfileFields);
$user->readDataById($requestedId);
if ((int) $user->getValue('usr_id') <= 0) {
    admidioApiError('Contact not found', 404);
}

// Enforce org membership to avoid cross-org access by ID
if (!isMemberOfOrganization($user)) {
    admidioApiError('Contact not found', 404);
}

    $profileBinary = '';
    if ((int) $gSettingsManager->get('profile_photo_storage') === 0) {
        $usr_photo = $user->getValue('usr_photo');
        if (!empty($usr_photo)) {
            $profileBinary = $usr_photo;
    }
    }
    else {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
        if (is_file($file)) {
            $profileBinary = (string) @file_get_contents($file);
    }
    }

    $encodedProfile = encodeProfileImage($profileBinary);

    $sections = buildProfileSections($currentUser, $user, $gProfileFields);
    $contact = [
    'id'            => (int) $user->getValue('usr_id'),
    'login'         => $user->getValue('usr_login_name'),
    'first_name'    => $user->getValue('FIRST_NAME'),
    'last_name'     => $user->getValue('LAST_NAME'),
    'email'         => $user->getValue('EMAIL'),
    'gender'        => $user->getValue('GENDER'),
    'street'        => $user->getValue('STREET'),
    'post_code'     => $user->getValue('POSTCODE'),
    'city'          => $user->getValue('CITY'),
    'country'       => $user->getValue('COUNTRY'),
    'phone'         => $user->getValue('PHONE'),
    'mobile'        => $user->getValue('MOBILE'),
    'birthday'      => $user->getValue('BIRTHDAY'),
    'website'       => $user->getValue('WEBSITE'),
    'profile'           => $encodedProfile['profile'],
    'profile_mime'      => $encodedProfile['profile_mime'],
    'profile_has_image' => $encodedProfile['profile_has_image'],
    'sections'           => $sections
    ];


echo json_encode([ 'contact' => $contact ]);
