<?php
/******************************************************************************
 * Script mit HTML-Code fuer die Kommentare eines Gaestebucheintrages
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * cid: Hiermit wird die ID des Gaestebucheintrages uebergeben
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/



if (isset($_GET['cid']) && is_numeric($_GET['cid']))
{
    // Script wurde ueber Ajax aufgerufen
    $cid = $_GET['cid'];

    require("../../system/common.php");
    require("../../system/bbcode.php");

    if ($g_preferences['enable_bbcode'] == 1)
    {
        // Klasse fuer BBCode
        $bbcode = new ubbParser();
    }
}
else
{
    $cid = 0;
}



if ($cid > 0)
{
    $sql    = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS. ", ". TBL_GUESTBOOK. "
                                   WHERE gbo_id     = {0}
                                     AND gbc_gbo_id = gbo_id
                                     AND gbo_org_id = '$g_current_organization->id'
                                   ORDER by gbc_timestamp asc";

    $sql    = prepareSQL($sql, array($cid));

    $comment_result = mysql_query($sql, $g_adm_con);
    db_error($comment_result,__FILE__,__LINE__);
}

if (isset($comment_result))
{

    echo"
    <div id=\"comments_$cid\" style=\"visibility: visible; display: block; text-align: left;\">
        <br />";

    //Kommentarnummer auf 1 setzen
    $commentNumber = 1;

    // Jetzt nur noch die Kommentare auflisten
    while ($row = mysql_fetch_object($comment_result))
    {
        $cid = $row->gbc_gbo_id;

        echo "
        <div class=\"groupBox\" style=\"overflow: hidden; margin-left: 20px; margin-right: 20px;\">
            <div class=\"groupBoxHeadline\">
                <div style=\"text-align: left; float: left;\">
                    <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: top;\" alt=\"Kommentar ". $commentNumber. "\">&nbsp;".
                    "Kommentar von ". strSpecialChars2Html($row->gbc_name);

                // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                if (isValidEmailAddress($row->gbc_email))
                {
                    echo "
                    <a href=\"mailto:$row->gbc_email\">
                    <img src=\"$g_root_path/adm_program/images/email.png\" style=\"vertical-align: middle;\" alt=\"Mail an $row->gbc_email\"
                    title=\"Mail an $row->gbc_email\" border=\"0\"></a>";
                }

                echo "
                </div>";


                echo "
                <div style=\"text-align: right;\">". mysqldatetime("d.m.y h:i", $row->gbc_timestamp). "&nbsp;";

                // aendern und loeschen von Kommentaren duerfen nur User mit den gesetzten Rechten
                if ($g_current_user->editGuestbookRight())
                {
                        echo "
                        <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                         onclick=\"self.location.href='guestbook_comment_new.php?cid=$row->gbc_id'\">";

                        echo "
                        <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"
                         onclick=\"self.location.href='guestbook_function.php?id=$row->gbc_id&amp;mode=7'\">";

                }

                echo "&nbsp;</div>";
            echo "
            </div>

            <div style=\"margin: 8px 4px 4px 4px;\">";
                // wenn BBCode aktiviert ist, den Text noch parsen, ansonsten direkt ausgeben
                if ($g_preferences['enable_bbcode'] == 1)
                {
                    echo strSpecialChars2Html($bbcode->parse($row->gbc_text));
                }
                else
                {
                    echo nl2br(strSpecialChars2Html($row->gbc_text));
                }
            echo "</div>";

            // Falls der Kommentar editiert worden ist, wird dies angezeigt
            if($row->gbc_usr_id_change > 0)
            {
                // Userdaten des Editors holen...
                $user_change = new User($g_adm_con);
                $user_change->getUser($row->gbc_usr_id_change);

                echo "
                <div class=\"smallFontSize\" style=\"margin: 8px 4px 4px 4px;\">Zuletzt bearbeitet von ".
                strSpecialChars2Html($user_change->first_name). " ". strSpecialChars2Html($user_change->last_name).
                " am ". mysqldatetime("d.m.y h:i", $row->gbc_last_change). "</div>";
            }

        echo"
        </div>

        <br />";

        // Kommentarnummer um 1 erhoehen
        $commentNumber = $commentNumber + 1;

    }

    if ($g_current_user->commentGuestbookRight() || $g_preferences['enable_gbook_comments4all'] == 1)
    {
        // Bei Kommentierungsrechten, wird der Link zur Kommentarseite angezeigt...
        $load_url = "$g_root_path/adm_program/modules/guestbook/guestbook_comment_new.php?id=$cid";
        echo "
        <div class=\"smallFontSize\" style=\"margin: 8px 4px 4px 4px;\">
            <a href=\"$load_url\">
            <img src=\"$g_root_path/adm_program/images/comment_new.png\" style=\"vertical-align: middle;\" alt=\"Kommentieren\"
            title=\"Kommentieren\" border=\"0\"></a>
            <a href=\"$load_url\">Einen Kommentar zu diesem Beitrag schreiben.</a>
        </div>";
    }

    echo"
    </div>";
}

?>