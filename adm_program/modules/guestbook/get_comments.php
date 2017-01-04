<?php
/**
 ***********************************************************************************************
 * Script creates html output for guestbook comments
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * cid        : Id of the corresponding guestbook entry
 * moderation : false - (Default) - Guestbookviww
 *              true  - Moderation mode, every entry could be released
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// Initialize and check the parameters
$getGbcId      = admFuncVariableIsValid($_GET, 'cid',        'int');
$getModeration = admFuncVariableIsValid($_GET, 'moderation', 'bool');

if ($getGbcId > 0)
{
    $conditions = '';

    // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
    if($gPreferences['enable_guestbook_moderation'] > 0 && $getModeration)
    {
        $conditions .= ' AND gbc_locked = 1 ';
    }
    else
    {
        $conditions .= ' AND gbc_locked = 0 ';
    }

    $sql = 'SELECT *
              FROM '.TBL_GUESTBOOK_COMMENTS.'
        INNER JOIN '.TBL_GUESTBOOK.'
                ON gbo_id = gbc_gbo_id
             WHERE gbo_id     = '.$getGbcId.'
               AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id').
                   $conditions.'
          ORDER BY gbc_timestamp_create ASC';
    $commentStatement = $gDb->query($sql);

    if($commentStatement->rowCount() > 0)
    {
        $gbComment = new TableGuestbookComment($gDb);

        // Jetzt nur noch die Kommentare auflisten
        while ($row = $commentStatement->fetch())
        {
            // GBComment-Objekt initialisieren und neuen DS uebergeben
            $gbComment->clear();
            $gbComment->setArray($row);

            $getGbcId = $gbComment->getValue('gbc_gbo_id');

            echo '
            <div class="panel panel-info admidio-panel-comment" id="gbc_'.$gbComment->getValue('gbc_id').'">
                <div class="panel-heading">
                    <div class="pull-left">
                        <img class="admidio-panel-heading-icon" src="'. THEME_URL. '/icons/comment.png" style="vertical-align: top;" alt="'.$gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name')).'" />&nbsp;'.
                        $gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name'));

            // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
            if(strlen($gbComment->getValue('gbc_email')) > 0)
            {
                echo '<a class="admidio-icon-link" href="mailto:'.$gbComment->getValue('gbc_email').'"><img src="'. THEME_URL. '/icons/email.png"
                    alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $gbComment->getValue('gbc_email')).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $gbComment->getValue('gbc_email')).'" /></a>';
            }
            echo '</div>
            <div class="pull-right text-right">'. $gbComment->getValue('gbc_timestamp_create', $gPreferences['system_date'].' '.$gPreferences['system_time']);

            // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
            if ($gCurrentUser->editGuestbookRight())
            {
                echo '
                <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php?cid='.$gbComment->getValue('gbc_id').'"><img
                    src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                    href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=gbc&amp;element_id=gbc_'.
                    $gbComment->getValue('gbc_id').'&amp;database_id='.$gbComment->getValue('gbc_id').'&amp;database_id_2='.$gbComment->getValue('gbo_id').'&amp;name='.urlencode($gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name'))).'"><img
                    src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
            }
            echo '</div>
            </div>

            <div class="panel-body">'.
                $gbComment->getValue('gbc_text');

            // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
            if($getModeration)
            {
                echo '
                <div class="btn-group" role="group">
                    <button class="btn btn-default" onclick="callUrlHideElement(\'gbc_'.$gbComment->getValue('gbc_id').'\', \'guestbook_function.php?mode=10&id='.$gbComment->getValue('gbc_id').'\')"><img
                        src="'. THEME_URL. '/icons/ok.png" alt="'.$gL10n->get('SYS_UNLOCK').'" />'.$gL10n->get('SYS_UNLOCK').'</button>
                    <button class="btn btn-default" onclick="callUrlHideElement(\'gbc_'.$gbComment->getValue('gbc_id').'\', \'guestbook_function.php?mode=5&id='.$gbComment->getValue('gbc_id').'\')"><img
                        src="'. THEME_URL. '/icons/no.png" alt="'.$gL10n->get('SYS_REMOVE').'" />'.$gL10n->get('SYS_REMOVE').'</button>
                </div>';
            }
            echo '</div>';

            // show information about user who edit the recordset
            if(strlen($gbComment->getValue('gbc_usr_id_change')) > 0)
            {
                echo '<div class="panel-footer">'.admFuncShowCreateChangeInfoById(0, '', $gbComment->getValue('gbc_usr_id_change'), $gbComment->getValue('gbc_timestamp_change')).'</div>';
            }
            echo '</div>';
        }

        if (!$getModeration && ($gCurrentUser->commentGuestbookRight() || $gPreferences['enable_gbook_comments4all'] == 1))
        {
            // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
            echo '
            <button type="button" class="btn btn-default" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php?id='.$getGbcId.'\'"><img
                src="'. THEME_URL. '/icons/comment_new.png" alt="'.$gL10n->get('GBO_WRITE_COMMENT').'"
                title="'.$gL10n->get('GBO_WRITE_COMMENT').'" />'.$gL10n->get('GBO_WRITE_COMMENT').'</button>';
        }
    }
}
