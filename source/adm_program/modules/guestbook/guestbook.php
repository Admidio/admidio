<?php
/******************************************************************************
 * Gaestebucheintraege auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * start     - Angabe, ab welchem Datensatz Gaestebucheintraege angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Gaestebucheintraegen steht
 *             (Default) Gaestebuch
 * id          - Nur einen einzigen Gaestebucheintrag anzeigen lassen.
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
unset($_SESSION['guestbook_comment_request']);

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Gaestebuch\"
        href=\"$g_root_path/adm_program/modules/guestbook/rss_guestbook.php\">";
};

$g_layout['header'] = $g_layout['header']. "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>

    <script type=\"text/javascript\">
        var resObject     = createXMLHttpRequest();
        var gbookId          = 0;

        function getComments(commentId)
        {
            gbookId = commentId;
            resObject.open('get', '$g_root_path/adm_program/modules/guestbook/get_comments.php?cid=' + gbookId, true);
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

    </script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">". $_GET["headline"]. "</h1>";

// falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
if ($_GET['id'] > 0)
{
    $sql    = "SELECT * FROM ". TBL_GUESTBOOK. "
               WHERE gbo_id = ". $_GET['id']. " and gbo_org_id = ". $g_current_organization->getValue("org_id");
}
//...ansonsten alle fuer die Gruppierung passenden Gaestebucheintraege aus der DB holen.
else
{
    $sql    = "SELECT * FROM ". TBL_GUESTBOOK. "
               WHERE gbo_org_id = ". $g_current_organization->getValue("org_id"). "
               ORDER BY gbo_timestamp DESC
               LIMIT ". $_GET['start']. ", 10 ";
}

$guestbook_result = $g_db->query($sql);

// Gucken wieviele Gaestebucheintraege insgesamt vorliegen...
// Das ist wichtig für die Seitengenerierung...
$sql    = "SELECT COUNT(*) FROM ". TBL_GUESTBOOK. "
           WHERE gbo_org_id = ". $g_current_organization->getValue("org_id");
$result = $g_db->query($sql);
$row = $g_db->fetch_array($result);
$num_guestbook = $row[0];

// Icon-Links und Navigation anzeigen

if ($_GET['id'] == 0)
{
    // Neuen Gaestebucheintrag anlegen
    echo "<p>
        <span class=\"iconLink\">
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/guestbook/guestbook_new.php?headline=". $_GET["headline"]. "\"><img
            class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" alt=\"Neuen Eintrag anlegen\"></a>
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/guestbook/guestbook_new.php?headline=". $_GET["headline"]. "\">Neuen Eintrag anlegen</a>
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
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET["headline"]. "&start=". $_GET["start"] ."\"><img
            class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck zum G&auml;stebuch\"></a>
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET["headline"]. "&start=". $_GET["start"] ."\"\">Zur&uuml;ck zum G&auml;stebuch</a>
        </span>
    </p>";
}

if ($g_db->num_rows($guestbook_result) == 0)
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
    while ($row = $g_db->fetch_object($guestbook_result))
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
                        <img src=\"$g_root_path/adm_program/images/email.png\" style=\"vertical-align: middle;\" alt=\"Mail an $row->gbo_email\"
                        title=\"Mail an $row->gbo_email\" border=\"0\"></a>";
                    }

                echo "</div>";


                echo "<div style=\"text-align: right;\">". mysqldatetime("d.m.y h:i", $row->gbo_timestamp). "&nbsp;";

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($g_current_user->editGuestbookRight())
                    {
                            echo "
                            <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                            onclick=\"self.location.href='$g_root_path/adm_program/modules/guestbook/guestbook_new.php?id=$row->gbo_id&amp;headline=". $_GET['headline']. "'\">";

                            echo "
                            <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"
                             onclick=\"self.location.href='$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=$row->gbo_id&amp;mode=6'\">";

                    }


                    echo "&nbsp;</div>";
                echo "</div>

                <div style=\"margin: 8px 4px 4px 4px;\">";
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
                    $user_change = new User($g_db, $row->gbo_usr_id_change);

                    echo "
                    <div class=\"smallFontSize\" style=\"margin: 8px 4px 4px 4px;\">Zuletzt bearbeitet von ".
                    $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                    " am ". mysqldatetime("d.m.y h:i", $row->gbo_last_change). "</div>";
                }


                // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
                $sql    = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS. "
                           WHERE gbc_gbo_id = '$row->gbo_id'
                           ORDER by gbc_timestamp asc";
                $comment_result = $g_db->query($sql);


                // Falls Kommentare vorhanden sind...
                if ($_GET['id'] == 0 && $g_db->num_rows($comment_result) > 0)
                {
                    // Dieses div wird erst gemeinsam mit den Kommentaren ueber Javascript eingeblendet
                    echo "
                    <div id=\"commentsVisible_$row->gbo_id\" style=\"visibility: hidden; display: none; margin: 8px 4px 4px;\">
                        <a href=\"javascript:toggleComments($row->gbo_id)\">
                        <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: middle;\" alt=\"Kommentare ausblenden\"
                        title=\"Kommentare ausblenden\" border=\"0\"></a>
                        <a href=\"javascript:toggleComments($row->gbo_id)\">Kommentare ausblenden</a>
                    </div>";

                    // Dieses div wird ausgeblendet wenn die Kommetare angezeigt werden
                    echo "
                    <div id=\"commentsInvisible_$row->gbo_id\" style=\"visibility: visible; display: block; margin: 8px 4px 4px;\">
                        <a href=\"javascript:toggleComments($row->gbo_id)\">
                        <img src=\"$g_root_path/adm_program/images/comments.png\" style=\"vertical-align: middle;\" alt=\"Kommentare anzeigen\"
                        title=\"Kommentare anzeigen\" border=\"0\"></a>
                        <a href=\"javascript:toggleComments($row->gbo_id)\">". $g_db->num_rows($comment_result). " Kommentar(e) zu diesem Eintrag</a>
                        <div id=\"comments_$row->gbo_id\" style=\"text-align: left;\"></div>
                    </div>";

                    // Hier ist das div, in das die Kommentare reingesetzt werden
                    echo "
                    <div id=\"commentSection_$row->gbo_id\" style=\"visibility: hidden; display: none;\"></div>";
                }


                if ($_GET['id'] == 0 && $g_db->num_rows($comment_result) == 0 && ($g_current_user->commentGuestbookRight() || $g_preferences['enable_gbook_comments4all'] == 1) )
                {
                    // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                    $load_url = "$g_root_path/adm_program/modules/guestbook/guestbook_comment_new.php?id=$row->gbo_id";
                    echo "
                    <div class=\"smallFontSize\" style=\"margin: 8px 4px 4px 4px;\">
                        <a href=\"$load_url\">
                        <img src=\"$g_root_path/adm_program/images/comment_new.png\" style=\"vertical-align: middle;\" alt=\"Kommentieren\"
                        title=\"Kommentieren\" border=\"0\"></a>
                        <a href=\"$load_url\">Einen Kommentar zu diesem Beitrag schreiben.</a>
                    </div>";
                }


                // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
                // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
                if ($g_db->num_rows($guestbook_result) > 0 && $_GET['id'] > 0)
                {
                    include("get_comments.php");
                }

        echo "</div>

        <br />";
    }  // Ende While-Schleife
}


if ($g_db->num_rows($guestbook_result) > 2)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $base_url = "$g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET["headline"];
    echo generatePagination($base_url, $num_guestbook, 10, $_GET["start"], TRUE);
}

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>