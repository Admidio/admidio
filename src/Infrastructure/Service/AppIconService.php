<?php
namespace Admidio\Infrastructure\Service;

/**
 * @brief Resolves the app icons that an organization can place in the adm_my_files folder.
 *
 * The folder adm_my_files is protected by a .htaccess file, so the icons can't be linked directly.
 * This class returns the URL of the public endpoint system/pwa_icon.php for an icon and resolves the
 * icon file for that endpoint. Only a fixed list of file names is used, never a path from the request.
 *
 * Lookup order for an icon of a size:
 * 1. adm_my_files/app_icon_<size>.png
 * 2. adm_my_files/app_icon.png
 * 3. adm_my_files/<default file name of the size> (e.g. admidio_logo_192.png)
 *
 * Lookup order for the maskable icon:
 * 1. adm_my_files/app_icon_maskable_512.png
 * 2. adm_my_files/app_icon_maskable.png
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class AppIconService
{
    /**
     * The supported icon sizes with the default file name of each size.
     */
    public const SIZES = array(
        64 => 'admidio_logo_64.png',
        114 => 'admidio_logo_114.png',
        180 => 'apple-touch-icon.png',
        192 => 'admidio_logo_192.png',
        512 => 'admidio_logo_512.png'
    );

    /**
     * Returns the absolute path of the icon file in adm_my_files for the given size.
     * @param int $size One of the sizes of AppIconService::SIZES. The size is ignored for the maskable icon.
     * @param bool $maskable If set to true, the maskable icon is returned.
     * @param string|null $folder Folder with the icon files. If not set, the adm_my_files folder is used.
     * @return string|null Path of the icon file or null if the size isn't supported or no icon file exists.
     */
    public static function getIconFile(int $size, bool $maskable = false, ?string $folder = null): ?string
    {
        $folder ??= ADMIDIO_PATH . FOLDER_DATA;

        if ($maskable) {
            $fileNames = array('app_icon_maskable_512.png', 'app_icon_maskable.png');
        } elseif (array_key_exists($size, self::SIZES)) {
            $fileNames = array('app_icon_' . $size . '.png', 'app_icon.png', self::SIZES[$size]);
        } else {
            return null;
        }

        foreach ($fileNames as $fileName) {
            $path = $folder . '/' . $fileName;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Returns the URL of the public endpoint that serves the icon of the given size from adm_my_files.
     * The modification time of the file is added as parameter, so browsers load a changed icon again.
     * @param int $size One of the sizes of AppIconService::SIZES. The size is ignored for the maskable icon.
     * @param bool $maskable If set to true, the URL of the maskable icon is returned.
     * @return string|null URL of the icon or null if no icon file exists in adm_my_files.
     */
    public static function getIconUrl(int $size, bool $maskable = false): ?string
    {
        $path = self::getIconFile($size, $maskable);
        if ($path === null) {
            return null;
        }

        $params = $maskable ? array('maskable' => 1) : array('size' => $size);
        $params['v'] = filemtime($path);
        return ADMIDIO_URL . '/system/pwa_icon.php?' . http_build_query($params);
    }
}
