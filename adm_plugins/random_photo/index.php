<?php
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Photos\Entity\Album;

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
try {
    $rootPath = dirname(dirname(__DIR__));
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/adm_program/system/common.php');

    // only include config file if it exists
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    $randomPhotoPlugin = new Overview($pluginFolder);

    // set default values if there no value has been stored in the config.php
    if (!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word)) {
        $plg_max_char_per_word = 0;
    }

    if (!isset($plg_photos_max_width) || !is_numeric($plg_photos_max_width)) {
        $plg_photos_max_width = 0;
    }

    if (!isset($plg_photos_max_height) || !is_numeric($plg_photos_max_height)) {
        $plg_photos_max_height = 0;
    }
    if (!isset($plg_photos_albums) || !is_numeric($plg_photos_albums)) {
        $plg_photos_albums = 0;
    }
    if (!isset($plg_photos_picnr) || !is_numeric($plg_photos_picnr)) {
        $plg_photos_picnr = 0;
    }
    if (!isset($plg_photos_show_link)) {
        $plg_photos_show_link = true;
    }

    if ($gSettingsManager->getInt('photo_module_enabled') > 0) {
        if ($gSettingsManager->getInt('photo_module_enabled') === 1
            || ($gSettingsManager->getInt('photo_module_enabled') === 2 && $gValidLogin)) {
            // call photo albums
            $sql = 'SELECT *
                  FROM ' . TBL_PHOTOS . '
                 WHERE pho_org_id   = ? -- $gCurrentOrgId
                   AND pho_locked   = false
                   AND pho_quantity > 0
              ORDER BY pho_begin DESC';

            // optional set a limit which albums should be scanned
            if ($plg_photos_albums > 0) {
                $sql .= ' LIMIT ' . $plg_photos_albums;
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
                if ($plg_photos_picnr === 0) {
                    $photoNr = mt_rand(1, (int)$album->getValue('pho_quantity'));
                } else {
                    $photoNr = $plg_photos_picnr;
                }

                // Compose image path
                $photoServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $album->getValue('pho_begin', 'Y-m-d') . '_' . (int)$album->getValue('pho_id') . '/' . $photoNr . '.jpg';
                ++$i;
            }

            if ($plg_photos_show_link && $plg_max_char_per_word > 0) {
                // Wrap link text if necessary
                $words = explode(' ', $album->getValue('pho_name'));

                foreach ($words as $word) {
                    if (strlen($word) > $plg_max_char_per_word) {
                        $linkText .= substr($word, 0, $plg_max_char_per_word) . '-<br />' .
                            substr($word, $plg_max_char_per_word) . ' ';
                    } else {
                        $linkText .= $word . ' ';
                    }
                }
            } else {
                $linkText = $album->getValue('pho_name');
            }

            $randomPhotoPlugin->assignTemplateVariable('photoUUID', $album->getValue('pho_uuid'));
            $randomPhotoPlugin->assignTemplateVariable('photoNr', $photoNr);
            $randomPhotoPlugin->assignTemplateVariable('photoTitle', $linkText);
            $randomPhotoPlugin->assignTemplateVariable('photoMaxWidth', $plg_photos_max_width);
            $randomPhotoPlugin->assignTemplateVariable('photoMaxHeight', $plg_photos_max_height);
            $randomPhotoPlugin->assignTemplateVariable('photoShowLink', $plg_photos_show_link);
        } else {
            $randomPhotoPlugin->assignTemplateVariable('message',$gL10n->get('PLG_RANDOM_PHOTO_NO_ENTRIES_VISITORS'));
        }

        if (isset($page)) {
            echo $randomPhotoPlugin->html('plugin.random-photo.tpl');
        } else {
            $randomPhotoPlugin->showHtmlPage('plugin.random-photo.tpl');
        }
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
