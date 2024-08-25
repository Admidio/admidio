<?php
/**
 ***********************************************************************************************
 * Script creates html output for guestbook comments
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * gbo_uuid   : UUID of the corresponding guestbook entry
 * moderation : false - (Default) - Guestbook view
 *              true  - Moderation mode, every entry could be released
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getCommentGboUuid = admFuncVariableIsValid($_GET, 'gbo_uuid', 'uuid');
    $getModeration = admFuncVariableIsValid($_GET, 'moderation', 'bool');

    if ($getCommentGboUuid !== '') {
        // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
        if ((int)$gSettingsManager->get('enable_guestbook_moderation') > 0 && $getModeration) {
            $conditions = ' AND gbc_locked = true ';
        } else {
            $conditions = ' AND gbc_locked = false ';
        }

        $sql = 'SELECT *
              FROM ' . TBL_GUESTBOOK_COMMENTS . '
        INNER JOIN ' . TBL_GUESTBOOK . '
                ON gbo_id = gbc_gbo_id
             WHERE gbo_uuid   = ? -- $getCommentGboUuid
               AND gbo_org_id = ? -- $gCurrentOrgId
                   ' . $conditions . '
          ORDER BY gbc_timestamp_create ASC';
        $commentStatement = $gDb->queryPrepared($sql, array($getCommentGboUuid, $gCurrentOrgId));

        if ($commentStatement->rowCount() > 0) {
            $gbComment = new TableGuestbookComment($gDb);

            // Jetzt nur noch die Kommentare auflisten
            while ($row = $commentStatement->fetch()) {
                // GBComment-Objekt initialisieren und neuen DS uebergeben
                $gbComment->clear();
                $gbComment->setArray($row);

                $gbcUuid = $gbComment->getValue('gbc_uuid');
                $gbcEmail = $gbComment->getValue('gbc_email');

                echo '
            <div class="card admidio-blog-comment" id="gbc_' . $gbcUuid . '">
                <div class="card-header">
                    <i class="bi bi-chat-fill"></i>' .
                    $gL10n->get('SYS_USERNAME_WITH_TIMESTAMP', array($gbComment->getValue('gbc_name'), $gbComment->getValue(
                        'gbc_timestamp_create',
                        $gSettingsManager->getString('system_date')
                    ), $gbComment->getValue('gbc_timestamp_create', $gSettingsManager->getString('system_time'))));

                // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                if (strlen($gbcEmail) > 0) {
                    echo '<a class="admidio-icon-link" href="mailto:' . $gbcEmail . '">
                            <i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_EMAIL_TO', array($gbcEmail)) . '"></i></a>';
                }

                // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
                if ($gCurrentUser->editGuestbookRight()) {
                    echo '
                        <div class="dropdown float-end">
                            <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_comment_new.php', array('gbc_uuid' => $gbcUuid)) . '">
                                    <i class="bi bi-pencil-square"></i> ' . $gL10n->get('SYS_EDIT') . '</a>
                                </li>
                                <li><a class="dropdown-item openPopup" href="javascript:void(0);"
                                    data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/system/popup_message.php', array('type' => 'gbc', 'element_id' => 'gbc_' . $gbcUuid, 'database_id' => $gbcUuid, 'database_id_2' => (int)$gbComment->getValue('gbo_id'), 'name' => $gL10n->get('GBO_COMMENT_BY', array($gbComment->getValue('gbc_name'))))) . '">
                                    <i class="bi bi-trash"></i> ' . $gL10n->get('SYS_DELETE') . '</a>
                                </li>
                            </ul>
                        </div>';
                }
                echo '</div>

                <div class="card-body">' .
                    $gbComment->getValue('gbc_text');

                // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
                if ($getModeration) {
                    echo '
                        <div class="btn-group" role="group">
                            <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbc_' . $gbcUuid . '\', \'' . SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 'moderate_comment', 'gbc_uuid' => $gbcUuid)) . '\')">
                                <i class="bi bi-check-lg"></i>' . $gL10n->get('SYS_UNLOCK') . '</button>
                            <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbc_' . $gbcUuid . '\', \'' . SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 'delete_comment', 'gbc_uuid' => $gbcUuid)) . '\')">
                                <i class="bi bi-trash"></i>' . $gL10n->get('SYS_REMOVE') . '</button>
                        </div>';
                }
                echo '</div>';

                // show information about user who edit the recordset
                if ((int)$gbComment->getValue('gbc_usr_id_change') > 0) {
                    echo '<div class="card-footer">' . admFuncShowCreateChangeInfoById(
                            0,
                            '',
                            (int)$gbComment->getValue('gbc_usr_id_change'),
                            $gbComment->getValue('gbc_timestamp_change')
                        ) . '</div>';
                }
                echo '</div>';
            }

            if (!$getModeration && ($gCurrentUser->commentGuestbookRight() || $gSettingsManager->getBool('enable_gbook_comments4all'))) {
                // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
                echo '
            <button type="button" class="btn btn-primary" onclick="window.location.href=\'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_comment_new.php', array('gbo_uuid' => $getCommentGboUuid)) . '\'">
                <i class="bi bi-pencil-fill"></i>' . $gL10n->get('GBO_WRITE_COMMENT') . '</button>';
            }
        }
    }
} catch (Throwable $e) {
    $gMessage->show($e->getMessage());
}
