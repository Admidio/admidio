<?php

namespace AdmidioPlugin\RandomPhoto;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Photos\Entity\Album;
use Admidio\UI\Presenter\PagePresenter;

use Exception;

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
final class RandomPhoto
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'random-photo';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 4;

    /**
     * Announce the widget. This is what plugin.php calls. The preferences panel is generated from
     * the manifest, so the plugin declares none of its own.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        PluginWidget::register($plugin, array(self::class, 'renderWidget'), array(
            'sequence' => self::DEFAULT_SEQUENCE
        ));
    }

    /**
     * Build the widget of the overview page. Whether it is shown at all was decided before this is
     * called, by the preference **random_photo_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        $config = $plugin->getSettingValues();
        $variables = array(
            'name' => $plugin->id,
            'message' => '',
            'photoUUID' => '',
            'photoNr' => 0,
            'photoTitle' => '',
            'photoMaxWidth' => $config['random_photo_max_width'],
            'photoMaxHeight' => $config['random_photo_max_height'],
            'photoShowLink' => $config['random_photo_show_album_link']
        );

        $photoModule = $gSettingsManager->getInt('photo_module_enabled');

        if ($photoModule === 0) {
            $variables['message'] = $gL10n->get('SYS_MODULE_DISABLED');
        } elseif ($photoModule === 1 || ($photoModule === 2 && $gValidLogin)) {
            $photoData = self::getPhotoData($config);
            $variables['photoUUID'] = $photoData['uuid'];
            $variables['photoNr'] = $photoData['photoNr'];
            $variables['photoTitle'] = $photoData['linkText'];
        } else {
            $variables['message'] = $gL10n->get('PLG_RANDOM_PHOTO_NO_ENTRIES_VISITORS');
        }

        return $plugin->renderTemplate($page, 'plugin.random-photo.tpl', $variables);
    }

    /**
     * Get the photo data
     * @param array<string,mixed> $pluginConfig
     * @return array Returns the photo data
     * @throws Exception
     */
    private static function getPhotoData(array $pluginConfig) : array
    {
        global $gCurrentOrgId, $gDb;

        $photoData = array();

        // call photo albums
        $sql = 'SELECT *
                FROM ' . TBL_PHOTOS . '
                WHERE pho_org_id   = ? -- $gCurrentOrgId
                AND pho_locked   = false
                AND pho_quantity > 0
            ORDER BY pho_begin DESC';

        // optional set a limit which albums should be scanned
        if ($pluginConfig['random_photo_albums'] > 0) {
            $sql .= ' LIMIT ' . $pluginConfig['random_photo_albums'];
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
            if ($pluginConfig['random_photo_album_photo_number'] === 0) {
                $photoNr = mt_rand(1, (int)$album->getValue('pho_quantity'));
            } else {
                $photoNr = $pluginConfig['random_photo_album_photo_number'];
            }

            // Compose image path
            $photoServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $album->getValue('pho_begin', 'Y-m-d') . '_' . (int)$album->getValue('pho_id') . '/' . $photoNr . '.jpg';
            ++$i;
        }

        if ($pluginConfig['random_photo_show_album_link'] && $pluginConfig['random_photo_max_char_per_word'] > 0) {
            // Wrap link text if necessary
            $words = explode(' ', $album->getValue('pho_name'));

            foreach ($words as $word) {
                if (strlen($word) > $pluginConfig['random_photo_max_char_per_word']) {
                    $linkText .= substr($word, 0, $pluginConfig['random_photo_max_char_per_word']) . '-<br />' .
                        substr($word, $pluginConfig['random_photo_max_char_per_word']) . ' ';
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
}
