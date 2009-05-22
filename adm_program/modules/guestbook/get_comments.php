<?php
/******************************************************************************
 * Script mit HTML-Code fuer die Kommentare eines Gaestebucheintrages
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * cid: Hiermit wird die ID des Gaestebucheintrages uebergeben
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_guestbook_comment.php');

$cid = 0;

if (isset($_GET['cid']) && is_numeric($_GET['cid']))
{
    // Script wurde ueber Ajax aufgerufen
    $cid = $_GET['cid'];
}

if ($cid > 0)
{
    $sql    = 'SELECT * FROM '. TBL_GUESTBOOK_COMMENTS. ', '. TBL_GUESTBOOK. '
                                   WHERE gbo_id     = '.$cid.'
                                     AND gbc_gbo_id = gbo_id
                                     AND gbo_org_id = '. $g_current_organization->getValue('org_id'). '
                                   ORDER by gbc_timestamp asc';
    $comment_result = $g_db->query($sql);
}

if (isset($comment_result))
{

    echo'<div id="comments_$cid" style="visibility: visible; display: block; text-align: left;">';

    //Kommentarnummer auf 1 setzen
    $commentNumber = 1;
    $gbComment = new TableGuestbookComment($g_db);

    // Jetzt nur noch die Kommentare auflisten
    while ($row = $g_db->fetch_object($comment_result))
    {
        // GBComment-Objekt initialisieren und neuen DS uebergeben
        $gbComment->clear();
        $gbComment->setArray($row);
    
        $cid = $gbComment->getValue('gbc_gbo_id');

        echo '
        <div class="groupBox" id="gbc_'.$gbComment->getValue('gbc_id').'" style="overflow: hidden; margin-left: 20px; margin-right: 20px;">
            <div class="groupBoxHeadline">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/comments.png" style="vertical-align: top;" alt="Kommentar '. $commentNumber. '" />&nbsp;'.
                    'Kommentar von '. $gbComment->getValue('gbc_name');

                // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                if (isValidEmailAddress($gbComment->getValue('gbc_email')))
                {
                    echo '<a class="iconLink" href="mailto:'.$gbComment->getValue('gbc_email').'"><img src="'. THEME_PATH. '/icons/email.png" 
                        alt="Mail an '.$gbComment->getValue('gbc_email').'" title="Mail an '.$gbComment->getValue('gbc_email').'" /></a>';
                }

                echo '
                </div>

                <div class="boxHeadRight">'. mysqldatetime('d.m.y h:i', $gbComment->getValue('gbc_timestamp'));

                // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
                if ($g_current_user->editGuestbookRight())
                {
                    echo '
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook_comment_new.php?cid='.$gbComment->getValue('gbc_id').'"><img 
                        src="'. THEME_PATH. '/icons/edit.png" alt="Bearbeiten" title="Bearbeiten" /></a>
                    <a class="iconLink" href="javascript:deleteObject(\'gbc\', \'gbc_'.$gbComment->getValue('gbc_id').'\','.$gbComment->getValue('gbc_id').',\''.$row->gbc_name.'\')"><img 
                        src="'. THEME_PATH. '/icons/delete.png" alt="Löschen" title="Löschen" /></a>';
                }

                echo '</div>
            </div>

            <div class="groupBoxBody">'.
                $gbComment->getText('HTML');

                // Falls der Kommentar editiert worden ist, wird dies angezeigt
                if($gbComment->getValue('gbc_usr_id_change') > 0)
                {
                    // Userdaten des Editors holen...
                    $user_change = new User($g_db, $gbComment->getValue('gbc_usr_id_change'));

                    echo '
                    <div class="editInformation">
                        Zuletzt bearbeitet von '.$user_change->getValue('Vorname'). ' '. $user_change->getValue('Nachname').
                        ' am '. mysqldatetime('d.m.y h:i', $gbComment->getValue('gbc_timestamp_change')). '
                    </div>';
                }
            echo '
            </div>
        </div>

        <br />';

        // Kommentarnummer um 1 erhoehen
        $commentNumber = $commentNumber + 1;

    }

    if ($g_current_user->commentGuestbookRight() || $g_preferences['enable_gbook_comments4all'] == 1)
    {
        // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
        $load_url = $g_root_path.'/adm_program/modules/guestbook/guestbook_comment_new.php?id='.$cid.'';
        echo '
        <div class="editInformation">
            <span class="iconTextLink">
                <a href="'.$load_url.'"><img src="'. THEME_PATH. '/icons/comment_new.png" 
                alt="Kommentieren" title="Kommentieren" /></a>
                <a href="'.$load_url.'">Einen Kommentar zu diesem Beitrag schreiben.</a>
            </span>
        </div>';
    }

    echo'
    </div>';
}

?>