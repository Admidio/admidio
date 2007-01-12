<?php
/******************************************************************************
 * Gaestebucheintraege auflisten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * start     - Angabe, ab welchem Datensatz Gaestebucheintraege angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Gaestebucheintraegen steht
 *             (Default) Gaestebuch
 * id          - Nur einen einzigen Gaestebucheintrag anzeigen lassen.
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

require("../../system/common.php");
require("../../system/bbcode.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// Uebergabevariablen pruefen

if (array_key_exists("start", $_GET))
{
    if (is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["start"] = 0;
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "G&auml;stebuch";
}

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}

if ($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode
    $bbcode = new ubbParser();
}

unset($_SESSION['guestbook_entry_request']);

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - ". $_GET["headline"]. "</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

    if ($g_preferences['enable_rss'] == 1)
    {
        echo "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_current_organization->longname - Gaestebuch\"
        href=\"$g_root_path/adm_program/modules/guestbook/rss_guestbook.php\">";
    }

    echo "

    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>

    <script type=\"text/javascript\">
        var resObject     = createXMLHttpRequest();
        var gbookId          = 0;

        function getComments(commentId)
        {
            gbookId = commentId;
            resObject.open('get', 'get_comments.php?cid=' + gbookId, false);
            resObject.onreadystatechange = handleResponse;
            resObject.send(null);
        }

        function handleResponse()
        {
            if (resObject.readyState == 4)
            {
                var objectId = 'commentSection_' + gbookId;
                document.getElementById(objectId).innerHTML = resObject.responseText;
                toggleComments(gbookId);
            }
        }

        function toggleComments(commentId)
        {
            if (document.getElementById('commentSection_' + commentId).innerHTML.length == 0)
            {
                getComments(commentId);
            }
            else
            {
                toggleDiv('commentsInvisible_' + commentId);
                toggleDiv('commentsVisible_' + commentId);
                toggleDiv('commentSection_' + commentId);
            }
        }

        function toggleDiv(objectId)
        {
            if (document.getElementById(objectId).style.visibility == 'visible')
            {
                document.getElementById(objectId).style.visibility = 'hidden';
                document.getElementById(objectId).style.display    = 'none';
            }
            else
            {
                document.getElementById(objectId).style.visibility = 'visible';
                document.getElementById(objectId).style.display    = 'block';
            }
        }

    </script>

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>". strspace($_GET["headline"]). "</h1>";

        // falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
        if ($_GET['id'] > 0)
        {
            $sql    = "SELECT * FROM ". TBL_GUESTBOOK. "
                       WHERE gbo_id = {0} and gbo_org_id = '$g_current_organization->id'";

            $sql    = prepareSQL($sql, array($_GET['id']));
        }
        //...ansonsten alle fuer die Gruppierung passenden Gaestebucheintraege aus der DB holen.
        else
        {
            $sql    = "SELECT * FROM ". TBL_GUESTBOOK. "
                       WHERE gbo_org_id = '$g_current_organization->id'
                       ORDER BY gbo_timestamp DESC
                       LIMIT {0}, 10 ";

            $sql    = prepareSQL($sql, array($_GET['start']));
        }

        $guestbook_result = mysql_query($sql, $g_adm_con);
        db_error($guestbook_result);

        // Gucken wieviele Gaestebucheintraege insgesamt vorliegen...
        // Das ist wichtig für die Seitengenerierung...
        $sql    = "SELECT COUNT(*) FROM ". TBL_GUESTBOOK. "
                   WHERE gbo_org_id = '$g_current_organization->id'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $row = mysql_fetch_array($result);
        $num_guestbook = $row[0];

        // Icon-Links und Navigation anzeigen

        if ($_GET['id'] == 0)
        {
            // Neuen Gaestebucheintrag anlegen
            echo "<p>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"guestbook_new.php?headline=". $_GET["headline"]. "\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Neuen Eintrag anlegen\"></a>
                    <a class=\"iconLink\" href=\"guestbook_new.php?headline=". $_GET["headline"]. "\">Neuen Eintrag anlegen</a>
                </span>
            </p>";

            // Navigation mit Vor- und Zurueck-Buttons
            $base_url = "$g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET["headline"];
            echo generatePagination($base_url, $num_guestbook, 10, $_GET["start"], TRUE);
        }
        else
        {
            echo "<p>
                <span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"guestbook.php?headline=". $_GET["headline"]. "&start=". $_GET["start"] ."\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck zum G&auml;stebuch\"></a>
                    <a class=\"iconLink\" href=\"guestbook.php?headline=". $_GET["headline"]. "&start=". $_GET["start"] ."\"\">Zur&uuml;ck zum G&auml;stebuch</a>
                </span>
            </p>";
        }

        if (mysql_num_rows($guestbook_result) == 0)
        {
            // Keine Gaestebucheintraege gefunden
            if ($_GET['id'] > 0)
            {
                echo "<p>Der angeforderte Eintrag exisitiert nicht (mehr) in der Datenbank.</p>";
            }
            else
            {
                echo "<p>Es sind keine Eintr&auml;ge vorhanden.</p>";
            }
        }
        else
        {

            // Gaestebucheintraege auflisten
            while ($row = mysql_fetch_object($guestbook_result))
            {
                echo "
                <div class=\"boxBody\" style=\"overflow: hidden;\">
                    <div class=\"boxHead\">
                        <div style=\"text-align: left; float: left;\">
                            <img src=\"$g_root_path/adm_program/images/comment.png\" style=\"vertical-align: middle;\" alt=\"". strSpecialChars2Html($row->gbo_name). "\">&nbsp;".
                            strSpecialChars2Html($row->gbo_name);

                            // Falls eine Homepage des Users angegeben wurde, soll der Link angezeigt werden...
                            if (strlen(trim($row->gbo_homepage)) > 0)
                            {
                                echo "
                                <a href=\"$row->gbo_homepage\" target=\"_blank\">
                                <img src=\"$g_root_path/adm_program/images/globe.png\" style=\"vertical-align: middle;\" alt=\"Gehe zu $row->gbo_homepage\"
                                title=\"Gehe zu $row->gbo_homepage\" border=\"0\"></a>";
                            }

                            // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                            if (isValidEmailAddress($row->gbo_email))
                            {
                                echo "
                                <a href=\"mailto:$row->gbo_email\">
                                <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle;\" alt=\"Mail an $row->gbo_email\"
                                title=\"Mail an $row->gbo_email\" border=\"0\"></a>";
                            }

                        echo "</div>";


                        echo "<div style=\"text-align: right;\">". mysqldatetime("d.m.y h:i", $row->gbo_timestamp). "&nbsp;";

                            // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                            if ($g_current_user->editGuestbookRight())
                            {
                                    echo "
                                    <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                                    onclick=\"self.location.href='guestbook_new.php?id=$row->gbo_id&amp;headline=". $_GET['headline']. "'\">";

                                    echo "
                                    <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"
                                     onclick=\"self.location.href='guestbook_function.php?id=$row->gbo_id&amp;mode=6'\">";

                            }


                            echo "&nbsp;</div>";
                        echo "</div>

                        <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">";
                            // wenn BBCode aktiviert ist, den Text noch parsen, ansonsten direkt ausgeben
                            if ($g_preferences['enable_bbcode'] == 1)
                            {
                                echo strSpecialChars2Html($bbcode->parse($row->gbo_text));
                            }
                            else
                            {
                                echo nl2br(strSpecialChars2Html($row->gbo_text));
                            }
                        echo "</div>";


                        // Falls der Eintrag editiert worden ist, wird dies angezeigt
                        if($row->gbo_usr_id_change > 0)
                        {
                            // Userdaten des Editors holen...
                            $user_change = new User($g_adm_con);
                            $user_change->getUser($row->gbo_usr_id_change);

                            echo "
                            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">Zuletzt bearbeitet von ".
                            strSpecialChars2Html($user_change->first_name). " ". strSpecialChars2Html($user_change->last_name).
                            " am ". mysqldatetime("d.m.y h:i", $row->gbo_last_change). "</div>";
                        }


                        // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
                        $sql    = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS. "
                                   WHERE gbc_gbo_id = '$row->gbo_id'
                                   ORDER by gbc_timestamp asc";

                        $comment_result = mysql_query($sql, $g_adm_con);
                        db_error($comment_result);


                        // Falls Kommentare vorhanden sind...
                        if ($_GET['id'] == 0 && mysql_num_rows($comment_result) > 0)
                        {
                            // Dieses div wird erst gemeinsam mit den Kommentaren ueber Javascript eingeblendet
                            echo "
                            <div id=\"commentsVisible_$row->gbo_id\" style=\"visibility: hidden; display: none; margin: 8px 4px 4px; font-size: 10pt; text-align: left;\">
                                <a href=\"#\" onClick=\"toggleComments($row->gbo_id);\">
                                <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: middle;\" alt=\"Kommentare ausblenden\"
                                title=\"Kommentare ausblenden\" border=\"0\"></a>
                                <a href=\"#\" onClick=\"toggleComments($row->gbo_id);\">Kommentare ausblenden</a>
                            </div>";

                            // Dieses div wird ausgeblendet wenn die Kommetare angezeigt werden
                            echo "
                            <div id=\"commentsInvisible_$row->gbo_id\" style=\"visibility: visible; display: block; margin: 8px 4px 4px; font-size: 10pt; text-align: left;\">
                                <a href=\"#\" onClick=\"toggleComments($row->gbo_id);\">
                                <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: middle;\" alt=\"Kommentare anzeigen\"
                                title=\"Kommentare anzeigen\" border=\"0\"></a>
                                <a href=\"#\" onClick=\"toggleComments($row->gbo_id);\">". mysql_num_rows($comment_result). " Kommentar(e) zu diesem Eintrag</a>
                                <div id=\"comments_$row->gbo_id\" style=\"text-align: left;\"></div>
                            </div>";

                            // Hier ist das div, in die die Kommentare reingesetzt werden
                            echo "
                            <div id=\"commentSection_$row->gbo_id\" style=\"visibility: hidden; display: none;\"></div>";
                        }


                        if ($_GET['id'] == 0 && mysql_num_rows($comment_result) == 0 && $g_current_user->commentGuestbookRight())
                        {
                            // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                            $load_url = "$g_root_path/adm_program/modules/guestbook/guestbook.php?id=$row->gbo_id";
                            echo "
                            <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
                                <a href=\"$load_url\">
                                <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: middle;\" alt=\"Kommentieren\"
                                title=\"Kommentieren\" border=\"0\"></a>
                                <a href=\"$load_url\">Einen Kommentar zu diesem Beitrag schreiben.</a>
                            </div>";
                        }


                        // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
                        // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
                        if (mysql_num_rows($guestbook_result) > 0 && $_GET['id'] > 0)
                        {
                            include("get_comments.php");
                        }

                echo "</div>

                <br />";
            }  // Ende While-Schleife
        }


        // Ab hier kommt nun das Formular um neue Kommentare hinzuzufuegen...
        // ...natuerlich nur wenn das Recht gesetzt ist, eine ID uebergeben wurde und der Eintrag wirklich vorhanden ist...
        if ($g_current_user->commentGuestbookRight() && mysql_num_rows($guestbook_result) > 0 && $_GET['id'] > 0)
        {
            echo "
            <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
                <form action=\"guestbook_function.php?id=". $_GET['id']. "&amp;headline=". $_GET['headline']. "&amp;mode=4\" method=\"post\" name=\"KommentarEintragen\">

                    <div class=\"formHead\">";
                        $formHeadline = " Kommentar anlegen";

                        echo strspace($formHeadline, 2);
                    echo "</div>
                    <div class=\"formBody\">
                        <div>
                            <div style=\"text-align: right; width: 25%; float: left;\">Name:</div>
                            <div style=\"text-align: left; margin-left: 27%;\">
                                <input class=\"readonly\" readonly type=\"text\" id=\"name\" name=\"name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". htmlspecialchars($g_current_user->first_name. " ". $g_current_user->last_name, ENT_QUOTES). "\">
                            </div>
                        </div>

                        <div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 25%; float: left;\">Kommentar:";
                                if ($g_preferences['enable_bbcode'] == 1)
                                {
                                  echo "<br><br>
                                  <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                                }
                            echo "</div>
                            <div style=\"text-align: left; margin-left: 27%;\">
                                <textarea  name=\"text\" tabindex=\"3\" style=\"width: 350px;\" rows=\"10\" cols=\"40\"></textarea>
                            </div>
                        </div>";


                        echo "<hr width=\"85%\" />

                        <div style=\"margin-top: 6px;\">
                            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\" tabindex=\"5\">
                                <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                                width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                                &nbsp;Zur&uuml;ck</button>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"4\">
                                <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                                width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                                &nbsp;Speichern</button>
                        </div>";

                    echo "</div>
                </form>
            </div>";
        }

        if (mysql_num_rows($guestbook_result) > 2)
        {
            // Navigation mit Vor- und Zurueck-Buttons
            // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
            $base_url = "$g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET["headline"];
            echo generatePagination($base_url, $num_guestbook, 10, $_GET["start"], TRUE);
        }
    echo "</div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>