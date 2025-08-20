<?php

namespace Plugins\RandomPhoto\classes;

use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Photos\Entity\Album;

use InvalidArgumentException;
use Exception;
use Throwable;

/**
 ***********************************************************************************************
 * Random Photo
 *
 * Plugin displays a randomly selected photo from the photo module and links the
 * corresponding album next to the image
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class RandomPhoto extends PluginAbstract
{
    private static array $pluginConfig = array();
    /**
     * Get the photo data
     * @return array Returns the photo data
     */
    private static function getPhotoData() : array
    {
        global $gCurrentOrgId, $gDb;

        self::$pluginConfig = self::getPluginConfigValues();
        $photoData = array();

        // call photo albums
        $sql = 'SELECT *
                FROM ' . TBL_PHOTOS . '
                WHERE pho_org_id   = ? -- $gCurrentOrgId
                AND pho_locked   = false
                AND pho_quantity > 0
            ORDER BY pho_begin DESC';

        // optional set a limit which albums should be scanned
        if (self::$pluginConfig['random_photo_albums'] > 0) {
            $sql .= ' LIMIT ' . self::$pluginConfig['random_photo_albums'];
        }

        $albumStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
        $albumList = $albumStatement->fetchAll();

        $i = 0;
        $photoNr = 0;
        $photoServerPath = '';
        $linkText = '';
        $album = new Album($gDb);

        // loop, if an image is not found directly, but limit to 20 passes
        while (!is_file($photoServerPath) && $i < 20 && $albumStatement->rowCount() > 0) {
            $album->setArray($albumList[mt_rand(0, $albumStatement->rowCount() - 1)]);

            // optionally select an image randomly
            if (self::$pluginConfig['random_photo_album_photo_number'] === 0) {
                $photoNr = mt_rand(1, (int)$album->getValue('pho_quantity'));
            } else {
                $photoNr = self::$pluginConfig['random_photo_album_photo_number'];
            }

            // Compose image path
            $photoServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $album->getValue('pho_begin', 'Y-m-d') . '_' . (int)$album->getValue('pho_id') . '/' . $photoNr . '.jpg';
            ++$i;
        }

        if (self::$pluginConfig['random_photo_show_album_link'] && self::$pluginConfig['random_photo_max_char_per_word'] > 0) {
            // Wrap link text if necessary
            $words = explode(' ', $album->getValue('pho_name'));

            foreach ($words as $word) {
                if (strlen($word) > self::$pluginConfig['random_photo_max_char_per_word']) {
                    $linkText .= substr($word, 0, self::$pluginConfig['random_photo_max_char_per_word']) . '-<br />' .
                        substr($word, self::$pluginConfig['random_photo_max_char_per_word']) . ' ';
                } else {
                    $linkText .= $word . ' ';
                }
            }
        } else {
            $linkText = $album->getValue('pho_name');
        }
        $photoData['photoNr'] = $photoNr;
        $photoData['uuid'] = $album->getValue('pho_uuid');
        $photoData['linkText'] = $linkText;
        return $photoData;
    }

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        // show random photo
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $randomPhotoPlugin = new Overview($pluginFolder);

            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }

            if ($gSettingsManager->getInt('photo_module_enabled') > 0) {
                if (($gSettingsManager->getInt('photo_module_enabled') === 1 || ($gSettingsManager->getInt('photo_module_enabled') === 2 && $gValidLogin)) &&
                    ($gSettingsManager->getInt('random_photo_plugin_enabled') === 1 || ($gSettingsManager->getInt('random_photo_plugin_enabled') === 2 && $gValidLogin))) {
                    $photoData = self::getPhotoData();
                    $randomPhotoPlugin->assignTemplateVariable('photoUUID', $photoData['uuid']);
                    $randomPhotoPlugin->assignTemplateVariable('photoNr', $photoData['photoNr']);
                    $randomPhotoPlugin->assignTemplateVariable('photoTitle', $photoData['linkText']);
                    $randomPhotoPlugin->assignTemplateVariable('photoMaxWidth', self::$pluginConfig['random_photo_max_width']);
                    $randomPhotoPlugin->assignTemplateVariable('photoMaxHeight', self::$pluginConfig['random_photo_max_height']);
                    $randomPhotoPlugin->assignTemplateVariable('photoShowLink', self::$pluginConfig['random_photo_show_album_link']);
                } else {
                    $randomPhotoPlugin->assignTemplateVariable('message',$gL10n->get('PLG_RANDOM_PHOTO_NO_ENTRIES_VISITORS'));
                }
            } else {
                $randomPhotoPlugin->assignTemplateVariable('message', $gL10n->get('SYS_MODULE_DISABLED'));
            }
            
            if (isset($page)) {
                echo $randomPhotoPlugin->html('plugin.random-photo.tpl');
            } else {
                $randomPhotoPlugin->showHtmlPage('plugin.random-photo.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}