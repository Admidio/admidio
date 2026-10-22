<?php
/**
 * Web App Manifest for Admidio Progressive Web App (PWA)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

try {
    require_once(__DIR__ . '/common.php');

    // Return 404 if PWA support is disabled in system preferences
    if ($gSettingsManager->has('system_pwa_enabled') && !$gSettingsManager->getBool('system_pwa_enabled')) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'PWA is disabled.';
        exit();
    }

    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=3600');

    // Organization name and shortname
    $orgLongname = $gCurrentOrganization->getValue('org_longname');
    $orgShortname = $gCurrentOrganization->getValue('org_shortname');

    $name = !empty($orgLongname) ? $orgLongname : (!empty($orgShortname) ? $orgShortname : 'Admidio');
    $shortName = !empty($orgShortname) ? $orgShortname : $name;

    // Theme colors
    $themeColor = '#349aaa'; // Default Admidio primary color
    if ($gSettingsManager->has('theme_color_primary') && !empty($gSettingsManager->getString('theme_color_primary'))) {
        $themeColor = $gSettingsManager->getString('theme_color_primary');
    } elseif ($gSettingsManager->has('color_primary') && !empty($gSettingsManager->getString('color_primary'))) {
        $themeColor = $gSettingsManager->getString('color_primary');
    }

    $backgroundColor = '#ffffff';
    if ($gSettingsManager->has('theme_color_background') && !empty($gSettingsManager->getString('theme_color_background'))) {
        $backgroundColor = $gSettingsManager->getString('theme_color_background');
    } elseif ($gSettingsManager->has('color_background') && !empty($gSettingsManager->getString('color_background'))) {
        $backgroundColor = $gSettingsManager->getString('color_background');
    }

    $startUrl = ADMIDIO_URL . FOLDER_MODULES . '/overview.php';
    $scopeUrl = ADMIDIO_URL . '/';

    // Custom app icon check:
    // 1. adm_my_files/app_icon_<size>.png or adm_my_files/app_icon.png
    // 2. Specific logo file in adm_my_files/ (e.g. admidio_logo_512.png)
    // 3. Current theme images/ (<defaultFilename>)
    // 4. Custom PNG favicon configured in preferences (theme_favicon_file / favicon_file)
    // 5. Default system/logo/ (<defaultFilename>)
    $customFavicon = '';
    if ($gSettingsManager->has('theme_favicon_file') && !empty($gSettingsManager->getString('theme_favicon_file'))) {
        $customFavicon = $gSettingsManager->getString('theme_favicon_file');
    } elseif ($gSettingsManager->has('favicon_file') && !empty($gSettingsManager->getString('favicon_file'))) {
        $customFavicon = $gSettingsManager->getString('favicon_file');
    }

    $getIconUrl = function (int $size, string $defaultFilename) use ($customFavicon) {
        // 1. Dedicated size-specific app icon in adm_my_files/
        if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/app_icon_' . $size . '.png')) {
            return ADMIDIO_URL . FOLDER_DATA . '/app_icon_' . $size . '.png';
        }
        // 2. Dedicated general app icon in adm_my_files/
        if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/app_icon.png')) {
            return ADMIDIO_URL . FOLDER_DATA . '/app_icon.png';
        }
        // 3. Organization-specific logo file in adm_my_files/ (e.g. admidio_logo_512.png)
        if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/' . $defaultFilename)) {
            return ADMIDIO_URL . FOLDER_DATA . '/' . $defaultFilename;
        }
        // 4. Check in current theme
        if (defined('THEME_PATH') && file_exists(THEME_PATH . '/images/' . $defaultFilename)) {
            return THEME_URL . '/images/' . $defaultFilename;
        }
        // 5. Custom favicon fallback (if PNG)
        if (!empty($customFavicon) && preg_match('/\.png$/i', $customFavicon) && file_exists(ADMIDIO_PATH . '/' . $customFavicon)) {
            return ADMIDIO_URL . '/' . $customFavicon;
        }
        // 6. Fallback to system/logo/
        return ADMIDIO_URL . '/system/logo/' . $defaultFilename;
    };

    $icons = array(
        array(
            'src'   => $getIconUrl(64, 'admidio_logo_64.png'),
            'sizes' => '64x64',
            'type'  => 'image/png'
        ),
        array(
            'src'   => $getIconUrl(114, 'admidio_logo_114.png'),
            'sizes' => '114x114',
            'type'  => 'image/png'
        ),
        array(
            'src'   => $getIconUrl(180, 'apple-touch-icon.png'),
            'sizes' => '180x180',
            'type'  => 'image/png'
        ),
        array(
            'src'     => $getIconUrl(192, 'admidio_logo_192.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any'
        ),
        array(
            'src'     => $getIconUrl(512, 'admidio_logo_512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any'
        )
    );

    // Dedicated maskable icon (only if explicitly provided with edge-to-edge solid bleed).
    // Transparent icons must NOT be marked maskable because Android WebAPK will fill
    // transparency with theme_color, which can blend away foreground logos matching theme_color.
    if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/app_icon_maskable_512.png')) {
        $icons[] = array(
            'src'     => ADMIDIO_URL . FOLDER_DATA . '/app_icon_maskable_512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable'
        );
    } elseif (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/app_icon_maskable.png')) {
        $icons[] = array(
            'src'     => ADMIDIO_URL . FOLDER_DATA . '/app_icon_maskable.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable'
        );
    }

    $manifest = array(
        'name'             => $name,
        'short_name'       => $shortName,
        'description'      => $name,
        'start_url'        => $startUrl,
        'scope'            => $scopeUrl,
        'id'               => $scopeUrl,
        'display'          => 'standalone',
        'orientation'      => 'any',
        'background_color' => $backgroundColor,
        'theme_color'      => $themeColor,
        'icons'            => $icons
    );

    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
