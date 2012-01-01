<?php
/******************************************************************************
 * Script creates html output for guestbook comments
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * cid        : Hiermit wird die ID des Gaestebucheintrages uebergeben
 * moderation : 0 (Default) - Gaestebuchansicht
 *              1 - Moderationsmodus, Beitraege koennen freigegeben werden 
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook_comment.php');

// Initialize and check the parameters
$getGbcId      = admFuncVariableIsValid($_GET, 'cid', 'numeric', 0);
$getModeration = admFuncVariableIsValid($_GET, 'moderation', 'boolean', 0);

if ($getGbcId > 0)
{
    $conditions = '';

    // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
    if($gPreferences['enable_guestbook_moderation'] > 0 && $getModeration == 1)
    {
        $conditions .= ' AND gbc_locked = 1 ';
    }
    else
    {
        $conditions .= ' AND gbc_locked = 0 ';
    }

    $sql    = 'SELECT * FROM '. TBL_GUESTBOOK_COMMENTS. ', '. TBL_GUESTBOOK. '
                WHERE gbo_id     = '.$getGbcId.'
                  AND gbc_gbo_id = gbo_id
                  AND gbo_org_id = '. $gCurrentOrganization->getValue('org_id').
                      $conditions.'
                ORDER by gbc_timestamp_create asc';
    $comment_result = $gDb->query($sql);
}

if (isset($comment_result))
{
    echo '<div id="comments_'.$getGbcId.'" style="visibility: visible; display: block; text-align: left;">';

    $gbComment = new TableGuestbookComment($gDb);

    // Jetzt nur noch die Kommentare auflisten
    while ($row = $gDb->fetch_object($comment_result))
    {
        // GBComment-Objekt initialisieren und neuen DS uebergeben
        $gbComment->clear();
        $gbComment->setArray($row);
    
        $getGbcId = $gbComment->getValue('gbc_gbo_id');

        echo '
        <div class="groupBox" id="gbc_'.$gbComment->getValue('gbc_id').'" style="overflow: hidden; margin-left: 20px; margin-right: 20px;">
            <div class="groupBoxHeadline">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/comments.png" style="vertical-align: top;" alt="'.$gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name')).'" />&nbsp;'.
                    $gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name'));

                    // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                    if(strlen($gbComment->getValue('gbc_email')) > 0)
                    {
                        echo '<a class="iconLink" href="mailto:'.$gbComment->getValue('gbc_email').'"><img src="'. THEME_PATH. '/icons/email.png" 
                            alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $gbComment->getValue('gbc_email')).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $gbComment->getValue('gbc_email')).'" /></a>';
                    }
                echo '
                </div>

                <div class="boxHeadRight">'. $gbComment->getValue('gbc_timestamp_create', $gPreferences['system_date'].' '.$gPreferences['system_time']);

                // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
                if ($gCurrentUser->editGuestbookRight())
                {
                    echo '
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_comment_new.php?cid='.$gbComment->getValue('gbc_id').'"><img 
                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                    <a class="iconLink" rel="lnkPopupWindow" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=gbc&amp;element_id=gbc_'.
                        $gbComment->getValue('gbc_id').'&amp;database_id='.$gbComment->getValue('gbc_id').'&amp;name='.urlencode($gL10n->get('GBO_COMMENT_BY', $gbComment->getValue('gbc_name'))).'"><img 
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                }

                echo '</div>
            </div>

            <div class="groupBoxBody">'.
                $gbComment->getValue('gbc_text');

                // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
                if($getModeration == 1)
                {
                    echo '
                    <ul class="iconTextLinkList">
                        <li>
                            <span class="iconTextLink">
                                <a rel="lnkPopupWindow" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=gbc_mod&amp;element_id=gbc_'.$gbComment->getValue('gbc_id').'&amp;database_id='.
                                    $gbComment->getValue('gbc_id').'&amp;name='.urlencode($gbComment->getValue('gbc_name')).'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('SYS_UNLOCK').'" /></a>
                                <a rel="lnkPopupWindow" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=gbc_mod&amp;element_id=gbc_'.$gbComment->getValue('gbc_id').'&amp;database_id='.
                                    $gbComment->getValue('gbc_id').'&amp;name='.urlencode($gbComment->getValue('gbc_name')).'">'.$gL10n->get('SYS_UNLOCK').'</a>
                            </span>
                        </li>
                        <li>
                            <span class="iconTextLink">
                                <a rel="lnkPopupWindow" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=gbc&amp;element_id=gbc_'.$gbComment->getValue('gbc_id').'&amp;database_id='.
                                    $gbComment->getValue('gbc_id').'&amp;name='.urlencode($gbComment->getValue('gbc_name')).'"><img src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('SYS_REMOVE').'" /></a>
                                <a rel="lnkPopupWindow" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=gbc&amp;element_id=gbc_'.$gbComment->getValue('gbc_id').'&amp;database_id='.
                                    $gbComment->getValue('gbc_id').'&amp;name='.urlencode($gbComment->getValue('gbc_name')).'">'.$gL10n->get('SYS_REMOVE').'</a>
                            </span>
                        </li>
                    </ul>';
                }

                // Falls der Kommentar editiert worden ist, wird dies angezeigt
                if($gbComment->getValue('gbc_usr_id_change') > 0)
                {
                    // Userdaten des Editors holen...
                    $user_change = new User($gDb, $gProfileFields, $gbComment->getValue('gbc_usr_id_change'));

                    echo '
                    <div class="editInformation">'.
                        $gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $gbComment->getValue('gbc_timestamp_change')). '
                    </div>';
                }
            echo '
            </div>
        </div>

        <br />';
    }

    if (($gCurrentUser->commentGuestbookRight() || $gPreferences['enable_gbook_comments4all'] == 1)
    && $getModeration == 0)
    {
        // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
        $load_url = $g_root_path.'/adm_program/modules/guestbook/guestbook_comment_new.php?id='.$getGbcId;

        echo '
        <div class="commentLink">
            <span class="iconTextLink">
                <a href="'.$load_url.'"><img src="'. THEME_PATH. '/icons/comment_new.png" 
                alt="'.$gL10n->get('GBO_WRITE_COMMENT').'" title="'.$gL10n->get('GBO_WRITE_COMMENT').'" /></a>
                <a href="'.$load_url.'">'.$gL10n->get('GBO_WRITE_COMMENT').'</a>
            </span>
        </div>';
    }

    echo'
    </div>';
}

?>