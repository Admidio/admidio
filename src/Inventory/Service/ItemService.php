<?php

namespace Admidio\Inventory\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\ValueObjects\ItemsData;
use RuntimeException;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ItemService
{
    protected ItemsData $itemRessource;
    protected Database $db;
    protected string $itemUUID;
    protected int $postCopyField;
    protected int $postCopyNumber;
    protected bool $postImported;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $itemUUID UUID if the item that should be managed within this class
     * @param int $postCopyField Field ID which should be used for numbering when copying items
     * @param int $postCopyNumber Number of items to be created when copying
     * @param bool $postImported Indicates whether the item is being imported
     * @throws Exception
     */
    public function __construct(Database $database, string $itemUUID = '', int $postCopyField = 0, int $postCopyNumber = 1, bool $postImported = false)
    {
        global $gCurrentOrgId;

        $this->db = $database;
        $this->itemUUID = $itemUUID;
        $this->postCopyField = $postCopyField;
        $this->postCopyNumber = $postCopyNumber;
        $this->postImported = $postImported;

        $this->itemRessource = new ItemsData($database, $gCurrentOrgId);
        $this->itemRessource->readItemData($itemUUID);
    }

    /**
     * Marks the item as retired.
     *
     * @throws Exception
     */
    public function retireItem(): void
    {
        $this->itemRessource->retireItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Reverts the item to its previous state.
     * @throws Exception
     */
    public function reinstateItem(): void
    {
        $this->itemRessource->reinstateItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Delete the current profile field form into the database.
     *
     * @throws Exception
     */
    public function delete(): void
    {
        $this->itemRessource->deleteItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Save data from the profile field form into the database.
     * @param bool $multiEdit If true, the form is used for multi-editing of items.
     * @throws Exception
     */
    public function save(bool $multiEdit = false): void
    {
        global $gCurrentSession, $gL10n, $gSettingsManager;

        // check form field input and sanitized it from malicious content
        if (!$this->postImported) {
            $itemFieldsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $itemFieldsEditForm->validate($_POST, $multiEdit);
        } else {
            $formValues = $_POST;
        }

        $startIdx = 1;
        if ($this->postCopyField > 0) {
            foreach ($this->itemRessource->getItemFields() as $itemField) {
                if ($itemField->getValue('inf_id') == $this->postCopyField) {
                    $itemCopyFieldName = $itemField->getValue('inf_name_intern');
                    break;
                }
            }

            if (isset($itemCopyFieldName)) {
                $startIdx = (int)$formValues['INF-' . $itemCopyFieldName] + 1;
            }
        }
        $stopIdx = $startIdx + $this->postCopyNumber;

        for ($i = $startIdx; $i < $stopIdx; ++$i) {
            if (isset($itemCopyFieldName)) {
                $formValues['INF-' . $itemCopyFieldName] = $i;
            }

            $this->itemRessource->readItemData($this->itemUUID);

            if ($this->itemUUID === '') {
                $this->itemRessource->createNewItem($formValues['INF-CATEGORY']);
            }

            // check all item fields
            foreach ($this->itemRessource->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');
                $postKey = 'INF-' . $infNameIntern;

                if (isset($formValues[$postKey])) {
                    if (is_array($formValues[$postKey]) && empty($formValues[$postKey])) {
                        throw new Exception($gL10n->get('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name'))));
                    } elseif (is_string($formValues[$postKey]) && (strlen($formValues[$postKey]) === 0 && $itemField->getValue('inf_required_input') == 1)) {
                        throw new Exception($gL10n->get('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name'))));
                    }

                    if ($itemField->getValue('inf_type') === 'DATE' && $gSettingsManager->get('inventory_field_date_time_format') == 'datetime') {
                        // Check if time is set separately
                        isset($formValues[$postKey . '_time']) ? $dateValue = $formValues[$postKey] . ' ' . $formValues[$postKey . '_time'] : $dateValue = $formValues[$postKey];

                        // Write value from field to the item class object with time
                        $this->itemRessource->setValue($infNameIntern, $dateValue);
                    } else {
                        // Write value from field to the item class object
                        $this->itemRessource->setValue($infNameIntern, $formValues[$postKey]);
                    }
                } elseif ($itemField->getValue('inf_type') === 'CHECKBOX' && !$multiEdit) {
                    // Set value to '0' for unchecked checkboxes
                    $this->itemRessource->setValue($itemField->getValue('inf_name_intern'), '0');
                }
            }

            // save item data
            $this->itemRessource->saveItemData();
        }

        //mark item as imported to prevent notification
        if ($this->postImported) {
            $this->itemRessource->setImportedItem();
        }

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Show the picture of the item.
     *
     * @throws Exception
     */
    public function showItemPicture($getNewPicture = false): void
    {
        global $gCurrentSession, $gSettingsManager;
        $item = new Item($this->db, $this->itemRessource, $this->itemRessource->getItemId());

        // Initialize default picture path
        $picturePath = getThemedFile('/images/inventory-item-picture.png');

        if ($item->getValue('ini_id') !== 0) {
            if ($getNewPicture) {
                // show temporary saved new picture from upload in database
                if ($gSettingsManager->getInt('inventory_item_picture_storage') === 0) {
                    $image = new Image();
                    $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
                } // show temporary saved new picture from upload in filesystem
                else {
                    $picturePath = ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '_new.jpg';
                    $image = new Image($picturePath);
                }
            } else {
                // show picture from database
                if ($gSettingsManager->getInt('inventory_item_picture_storage') === 0) {
                    if ((string)$item->getValue('ini_picture') !== '') {
                        $image = new Image();
                        $image->setImageFromData($item->getValue('ini_picture'));
                    } else {
                        $image = new Image($picturePath);
                    }
                } // show picture from folder adm_my_files
                else {
                    $file = ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '.jpg';
                    if (is_file($file)) {
                        $picturePath = $file;
                    }
                    $image = new Image($picturePath);
                }
            }
        } else {
            // If no item exists, show default picture
            $image = new Image($picturePath);
        }

        header('Content-Type: ' . $image->getMimeType());
        // Caching-Header setzen
        header("Last-Modified: " . $item->getValue('ini_timestamp_changed', 'D, d M Y H:i:s') . " GMT");
        header("ETag: " . md5_file($picturePath));

        $image->copyToBrowser();
        $image->delete();
    }

    /**
     * Upload a new item picture.
     *
     * @throws Exception
     */
    public function uploadItemPicture(): void
    {
        global $gCurrentSession, $gSettingsManager;
        // Confirm cache picture
        // check form field input and sanitized it from malicious content
        $itemPictureUploadForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $itemPictureUploadForm->validate($_POST);

        // File size
        if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
            throw new Exception('SYS_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2)));
        }

        // check if a file was really uploaded
        if (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
            throw new Exception('SYS_NO_PICTURE_SELECTED');
        }

        // File ending
        $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
        if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
            throw new Exception('SYS_PHOTO_FORMAT_INVALID');
        }

        // Resolution control
        $imageDimensions = $imageProperties[0] * $imageProperties[1];
        if ($imageDimensions > SystemInfoUtils::getProcessableImageSize()) {
            throw new Exception('SYS_PHOTO_RESOLUTION_TO_LARGE', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2)));
        }

        // Adjust picture to appropriate size
        $itemImage = new Image($_FILES['userfile']['tmp_name'][0]);
        $itemImage->setImageType('jpeg');

        if ($gSettingsManager->getInt('inventory_item_picture_storage') === 1) {
            // Folder storage
            $itemImage->scale($gSettingsManager->getInt('inventory_item_picture_width'), $gSettingsManager->getInt('inventory_item_picture_height'));
            $itemImage->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '_new.jpg');
        } else {
            // Database storage
            $itemImage->scale(130, 170);
            $itemImage->copyToFile(null, $_FILES['userfile']['tmp_name'][0]);
            $itemImageData = fread(fopen($_FILES['userfile']['tmp_name'][0], 'rb'), $_FILES['userfile']['size'][0]);

            $gCurrentSession->setValue('ses_binary', $itemImageData);
            $gCurrentSession->save();
        }

        // delete image object
        $itemImage->delete();
    }

    /**
     * Save the picture of the item.
     *
     * @throws Exception
     */
    public function saveItemPicture(): void
    {
        global $gLogger, $gSettingsManager, $gCurrentSession;

        if ($gSettingsManager->getInt('inventory_item_picture_storage') === 1) {
            // Save picture in the file system

            // Check if a picture was saved for the user
            $fileOld = ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '_new.jpg';
            if (is_file($fileOld)) {
                $fileNew = ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '.jpg';
                try {
                    FileSystemUtils::deleteFileIfExists($fileNew);

                    try {
                        FileSystemUtils::moveFile($fileOld, $fileNew);
                    } catch (RuntimeException $exception) {
                        $gLogger->error('Could not move file!', array('from' => $fileOld, 'to' => $fileNew));
                        // TODO
                    }
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $fileNew));
                    // TODO
                }
            }
        } else {
            // Save picture in the database
            $item = new Item($this->db, $this->itemRessource, $this->itemRessource->getItemId());

            // Check if a picture was saved for the user
            if (strlen($gCurrentSession->getValue('ses_binary')) > 0) {
                $this->db->startTransaction();
                // write the picture data into the database
                $item->setValue('ini_picture', $gCurrentSession->getValue('ses_binary'));
                $item->save();

                // remove temporary picture data from session
                $gCurrentSession->setValue('ses_binary', '');
                $gCurrentSession->save();
                $this->db->endTransaction();
            }
        }
    }

    /**
     * Delete the picture of the item.
     *
     * @throws Exception
     */
    public function deleteItemPicture(): void
    {
        global $gLogger, $gSettingsManager;
        if ($gSettingsManager->getInt('inventory_item_picture_storage') === 1) {
            // Folder storage, delete file
            $filePath = ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $this->itemRessource->getItemId() . '.jpg';
            try {
                FileSystemUtils::deleteFileIfExists($filePath);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $filePath));
                // TODO
            }
        } else {
            // Database storage, remove data from session
            $item = new Item($this->db, $this->itemRessource, $this->itemRessource->getItemId());
            $item->setValue('ini_picture', '');
            $item->save();
        }
    }
}
