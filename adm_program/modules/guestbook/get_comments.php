<?php
/**
 ***********************************************************************************************
 * Script creates html output for guestbook comments
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * gbo_uuid   : UUID of the corresponding guestbook entry
 * moderation : false - (Default) - Guestbookviww
 *              true  - Moderation mode, every entry could be released
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getCommentGboUuid = admFuncVariableIsValid($_GET, 'gbo_uuid', 'string');
$getModeration     = admFuncVariableIsValid($_GET, 'moderation', 'bool');

if ($getCommentGboUuid !== '') {
    // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
    if ((int) $gSettingsManager->get('enable_guestbook_moderation') > 0 && $getModeration) {
        $conditions = ' AND gbc_locked = true ';
    } else {
        $conditions = ' AND gbc_locked = false ';
    }

    $sql = 'SELECT *
              FROM '.TBL_GUESTBOOK_COMMENTS.'
        INNER JOIN '.TBL_GUESTBOOK.'
                ON gbo_id = gbc_gbo_id
             WHERE gbo_uuid   = ? -- $getCommentGboUuid
               AND gbo_org_id = ? -- $gCurrentOrgId
                   '.$conditions.'
          ORDER BY gbc_timestamp_create ASC';
    $commentStatement = $gDb->queryPrepared($sql, array($getCommentGboUuid, $gCurrentOrgId));

    if ($commentStatement->rowCount() > 0) {
        $gbComment = new TableGuestbookComment($gDb);

        // Jetzt nur noch die Kommentare auflisten
        while ($row = $commentStatement->fetch()) {
            // GBComment-Objekt initialisieren und neuen DS uebergeben
            $gbComment->clear();
            $gbComment->setArray($row);

            $gbcUuid  = $gbComment->getValue('gbc_uuid');
            $gbcEmail = $gbComment->getValue('gbc_email');

            echo '
            <div class="card admidio-blog-comment" id="gbc_'.$gbcUuid.'">
                <div class="card-header">
                    <i class="fas fa-comment"></i>' .
                        $gL10n->get('SYS_USERNAME_WITH_TIMESTAMP', array($gbComment->getValue('gbc_name'), $gbComment->getValue(
                            'gbc_timestamp_create',
                            $gSettingsManager->getString('system_date')
                        ), $gbComment->getValue('gbc_timestamp_create', $gSettingsManager->getString('system_time'))));

            // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
            if (strlen($gbcEmail) > 0) {
                echo '<a class="admidio-icon-link" href="mailto:'.$gbcEmail.'">
                            <i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', array($gbcEmail)).'"></i></a>';
            }

            // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
            if ($gCurrentUser->editGuestbookRight()) {
                echo '
                        <div class="dropdown float-right">
                            <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php', array('gbc_uuid' => $gbcUuid)).'">
                                    <i class="fas fa-edit"></i> '.$gL10n->get('SYS_EDIT').'</a>
                                <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                                    data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'gbc', 'element_id' => 'gbc_'.$gbcUuid, 'database_id' => $gbcUuid, 'database_id_2' => (int) $gbComment->getValue('gbo_id'), 'name' => $gL10n->get('GBO_COMMENT_BY', array($gbComment->getValue('gbc_name'))))).'">
                                    <i class="fas fa-trash-alt"></i> '.$gL10n->get('SYS_DELETE').'</a>
                            </div>
                        </div>';
            }
            echo '</div>

                <div class="card-body">'.
                    $gbComment->getValue('gbc_text');

            // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
            if ($getModeration) {
                echo '
                        <div class="btn-group" role="group">
                            <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbc_'.$gbcUuid.'\', \''.SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 10, 'gbc_uuid' => $gbcUuid)).'\')">
                                <i class="fas fa-check"></i>'.$gL10n->get('SYS_UNLOCK').'</button>
                            <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbc_'.$gbcUuid.'\', \''.SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 5, 'gbc_uuid' => $gbcUuid)).'\')">
                                <i class="fas fa-trash-alt"></i>'.$gL10n->get('SYS_REMOVE').'</button>
                        </div>';
            }
            echo '</div>';

            // show information about user who edit the recordset
            if ((int) $gbComment->getValue('gbc_usr_id_change') > 0) {
                echo '<div class="card-footer">'.admFuncShowCreateChangeInfoById(
                    0,
                    '',
                    (int) $gbComment->getValue('gbc_usr_id_change'),
                    $gbComment->getValue('gbc_timestamp_change')
                ).'</div>';
            }
            echo '</div>';
        }

        if (!$getModeration && ($gCurrentUser->commentGuestbookRight() || $gSettingsManager->getBool('enable_gbook_comments4all'))) {
            // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
            echo '
            <button type="button" class="btn btn-secondary" onclick="window.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php', array('gbo_uuid' => $getCommentGboUuid)).'\'">
                <i class="fas fa-pencil-alt"></i>'.$gL10n->get('GBO_WRITE_COMMENT').'</button>';
        }
    }
}
