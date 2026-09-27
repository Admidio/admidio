<?php

namespace Admidio\Infrastructure\Utils;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Users\Entity\User;

/**
 * Authorize image uploads for the editor field that requested them.
 */
final class CkeditorUploadAccess
{
    /**
     * @return string Name of the module's image folder.
     * @throws Exception
     */
    public static function authorizedFolder(string $editorId, User $user, SettingsManager $settings): string
    {
        switch ($editorId) {
            case 'ann_description':
                if ($settings->getInt('announcements_module_enabled') > 0
                    && count($user->getAllEditableCategories('ANN')) > 0) {
                    return 'announcements';
                }
                break;

            case 'dat_description':
                if ($settings->getInt('events_module_enabled') > 0
                    && count($user->getAllEditableCategories('EVT')) > 0) {
                    return 'events';
                }
                break;

            case 'fop_text':
                if ($settings->getInt('forum_module_enabled') > 0
                    && count($user->getAllEditableCategories('FOT')) > 0) {
                    return 'forum';
                }
                break;

            case 'lnk_description':
                if ($settings->getInt('weblinks_module_enabled') > 0
                    && count($user->getAllEditableCategories('LNK')) > 0) {
                    return 'weblinks';
                }
                break;

            case 'msg_body':
                if ($settings->getBool('mail_html_registered_users')
                    && ($settings->getBool('pm_module_enabled')
                        || ($settings->getInt('mail_module_enabled') > 0 && $user->hasEmail()))) {
                    return 'mail';
                }
                break;

            case 'room_description':
                if ($settings->getInt('events_module_enabled') > 0 && $user->isAdministrator()) {
                    return 'rooms';
                }
                break;

            case 'usf_description':
                if ($user->isAdministrator()) {
                    return 'user_fields';
                }
                break;
        }

        // Editors without media controls and unknown IDs must never write to a shared fallback folder.
        throw new Exception('SYS_NO_RIGHTS');
    }
}
