<?php

require_once(SERVER_PATH.'/adm_program/libs/jquery-file-upload/server/php/uploadhandler.php');

/**
 * Class UploadHandlerPhoto
 */
class UploadHandlerPhoto extends UploadHandler
{
    /**
     * Override the default method to handle the specific things of the photo module.
     * This method has the same parameters as the default.
     * @param  $uploaded_file
     * @param  $name
     * @param  $size
     * @param  $type
     * @param  $error
     * @param  $index
     * @param  $content_range
     * @return stdClass
     */
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null)
    {
        global $photoAlbum, $gPreferences, $gL10n;

        $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);

        if(!isset($file->error))
        {
            $fileLocation = SERVER_PATH.'/adm_my_files/photos/upload/'.$file->name;
            $albumFolder  = SERVER_PATH.'/adm_my_files/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$photoAlbum->getValue('pho_id');

            // create folder if not exists
            if(file_exists($albumFolder) === false)
            {
                $error = $photoAlbum->createFolder();

                if($error['text'] !== '')
                {
                    $file->error = $gL10n->get($error['text'], $error['path']);
                    return $file;
                }
            }

            $newFotoFileNumber = $photoAlbum->getValue('pho_quantity') + 1;

            // read image size
            $imageProperties = getimagesize($fileLocation);
            $imageDimensions = $imageProperties[0] * $imageProperties[1];

            if($imageDimensions > admFuncProcessableImageSize())
            {
                $file->error = $gL10n->get('PHO_RESOLUTION_MORE_THAN').' '.round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('MEGA_PIXEL');
                return $file;
            }

            // check mime type and set file extension
            if($imageProperties['mime'] === 'image/jpeg')
            {
                $fileExtension = 'jpg';
            }
            elseif($imageProperties['mime'] === 'image/png')
            {
                $fileExtension = 'png';
            }
            else
            {
                $file->error = $gL10n->get('PHO_PHOTO_FORMAT_INVALID');
                return $file;
            }

            // create image object and scale image to defined size of preferences
            $image = new Image($fileLocation);
            $image->setImageType('jpeg');
            $image->scaleLargerSide($gPreferences['photo_save_scale']);
            $image->copyToFile(null, $albumFolder.'/'.$newFotoFileNumber.'.jpg');
            $image->delete();

            // if enabled then save original image
            if ($gPreferences['photo_keep_original'] == 1)
            {
                if(!file_exists($albumFolder.'/originals'))
                {
                    $folder = new Folder($albumFolder);
                    $folder->createFolder('originals', true);
                }

                rename($fileLocation, $albumFolder.'/originals/'.$newFotoFileNumber.'.'.$fileExtension);
            }

            // save thumbnail
            if(!file_exists($albumFolder.'/thumbnails'))
            {
                $folder = new Folder($albumFolder);
                $folder->createFolder('thumbnails', true);
            }

            $image = new Image($fileLocation);
            $image->scaleLargerSide($gPreferences['photo_thumbs_scale']);
            $image->copyToFile(null, $albumFolder.'/thumbnails/'.$newFotoFileNumber.'.jpg');
            $image->delete();

            // delete image from upload folder
            if(file_exists($fileLocation))
            {
                unlink($fileLocation);
            }

            // if image was successfully saved in filesystem then update image count of album
            if(file_exists($albumFolder.'/'.$newFotoFileNumber.'.jpg'))
            {
                $photoAlbum->setValue('pho_quantity', $photoAlbum->getValue('pho_quantity')+1);
                $photoAlbum->save();
            }
            else
            {
                $file->error = $gL10n->get('PHO_PHOTO_PROCESSING_ERROR');
            }
        }

        return $file;
    }
}
