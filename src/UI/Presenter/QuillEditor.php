<?php

namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Utils\SecurityUtils;

/** Shared configuration for Quill fields in both form implementations. */
final class QuillEditor
{
    public static function options(string $id, string $toolbar): array
    {
        global $gL10n, $gCurrentSession;

        return array(
            'toolbar' => $toolbar,
            'uploadUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/editor_upload_handler.php', array('id' => $id)),
            'csrfToken' => $gCurrentSession->getCsrfToken(),
            'uploadError' => $gL10n->get('SYS_FILES_UPLOAD_NOT_SUCCESSFUL'),
            'labels' => array(
                'bold' => $gL10n->get('SYS_BOLD'),
                'italic' => $gL10n->get('SYS_ITALIC'),
                'underline' => $gL10n->get('SYS_UNDERLINE'),
                'fontSize' => $gL10n->get('SYS_FONT_SIZE'),
                'fontColor' => $gL10n->get('SYS_COLOR_TEXT'),
                'sizeSmall' => $gL10n->get('SYS_SMALL'),
                'sizeNormal' => $gL10n->get('SYS_NORMAL'),
                'sizeLarge' => $gL10n->get('SYS_LARGE'),
                'sizeHuge' => $gL10n->get('SYS_VERY_LARGE'),
                'defaultColor' => $gL10n->get('SYS_DEFAULT_COLOR'),
                'alignment' => $gL10n->get('SYS_ALIGNMENT'),
                'alignLeft' => $gL10n->get('SYS_ALIGN_LEFT'),
                'alignCenter' => $gL10n->get('SYS_ALIGN_CENTER'),
                'alignRight' => $gL10n->get('SYS_ALIGN_RIGHT'),
                'alignJustify' => $gL10n->get('SYS_ALIGN_JUSTIFY'),
                'ordered' => $gL10n->get('SYS_ORDERED_LIST'),
                'bullet' => $gL10n->get('SYS_BULLETED_LIST'),
                'link' => $gL10n->get('SYS_INSERT_LINK'),
                'image' => $gL10n->get('SYS_INSERT_IMAGE'),
                'video' => $gL10n->get('SYS_INSERT_VIDEO'),
                'videoUrl' => $gL10n->get('SYS_VIDEO_URL'),
                'invalidVideoUrl' => $gL10n->get('SYS_VIDEO_URL_INVALID'),
                'table' => $gL10n->get('SYS_TABLE'),
                'insertTable' => $gL10n->get('SYS_INSERT_TABLE'),
                'insertRowAbove' => $gL10n->get('SYS_INSERT_ROW_ABOVE'),
                'insertRowBelow' => $gL10n->get('SYS_INSERT_ROW_BELOW'),
                'insertColumnLeft' => $gL10n->get('SYS_INSERT_COLUMN_LEFT'),
                'insertColumnRight' => $gL10n->get('SYS_INSERT_COLUMN_RIGHT'),
                'deleteRow' => $gL10n->get('SYS_DELETE_ROW'),
                'deleteColumn' => $gL10n->get('SYS_DELETE_COLUMN'),
                'deleteTable' => $gL10n->get('SYS_DELETE_TABLE'),
                'resizeImage' => $gL10n->get('SYS_RESIZE_IMAGE'),
                'clean' => $gL10n->get('SYS_REMOVE_FORMATTING')
            )
        );
    }

    public static function initializationCode(string $id, string $toolbar): string
    {
        return 'admidioInitQuill(' . json_encode($id, JSON_HEX_TAG | JSON_HEX_AMP)
            . ', ' . json_encode(self::options($id, $toolbar), JSON_HEX_TAG | JSON_HEX_AMP) . ');';
    }
}
