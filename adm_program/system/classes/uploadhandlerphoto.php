<?php


require_once(SERVER_PATH.'/adm_program/libs/jquery-file-upload/server/php/uploadhandler.php');

class UploadHandlerPhoto extends UploadHandler
{
    /* Override the default method to handle the specific things of the photo module.
       This method has the same parameters as the default.
    */
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error,
        $index = null, $content_range = null)
    {
        global $photoAlbum, $gPreferences, $gL10n;

        $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);

        if(isset($file->error) == false)
        {
            $fileLocation = SERVER_PATH. '/adm_my_files/photos/upload/'.$file->name;
            $albumFolder  = SERVER_PATH. '/adm_my_files/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$photoAlbum->getValue('pho_id');
            $newFotoFileNumber = $photoAlbum->getValue('pho_quantity') + 1;

            // read image size
            $imageProperties = getimagesize($fileLocation);
            $imageDimensions = $imageProperties[0] * $imageProperties[1];

            if($imageDimensions > admFuncProcessableImageSize())
            {
                $file->error = $gL10n->get('PHO_RESOLUTION_MORE_THAN').' '.round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('MEGA_PIXEL');
            }

            // check mime type and set file extension
            if($imageProperties['mime'] == 'image/jpeg')
            {
                $fileExtension = 'jpg';
            }
            elseif($imageProperties['mime'] == 'image/png')
            {
                $fileExtension = 'png';
            }
            else
            {
                $file->error = $gL10n->get('PHO_PHOTO_FORMAT_INVALID');
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
                if(file_exists($albumFolder.'/originals') == false)
                {
                    $folder = new Folder($albumFolder);
                    $folder->createFolder('originals', true);
                }

                rename($fileLocation, $albumFolder.'/originals/'.$newFotoFileNumber.'.'.$fileExtension);
            }

            // save thumbnail
            if(file_exists($albumFolder.'/thumbnails') == false)
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
                $newFotoFileNumber --;
                $file->error = $gL10n->get('PHO_PHOTO_PROCESSING_ERROR');
            }
        }
        return $file;
    }
}
