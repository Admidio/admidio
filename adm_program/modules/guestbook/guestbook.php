<?php
/**
 ***********************************************************************************************
 * Show a list of all guestbook entries
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * start      : Position of query recordset where the visual output should start
 * headline   - Title of the guestbook module. This will be shown in the whole module.
 *              (Default) GBO_GUESTBOOK
 * id         - Id of one guestbook entry that should be shown
 * moderation : false (Default) - Guestbookviww
 *              true - Moderation mode, every entry could be released
 ***********************************************************************************************
 */
require_once('../../system/common.php');

unset($_SESSION['guestbook_entry_request'], $_SESSION['guestbook_comment_request']);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Initialize and check the parameters
$getStart      = admFuncVariableIsValid($_GET, 'start',      'int');
$getHeadline   = admFuncVariableIsValid($_GET, 'headline',   'string', array('defaultValue' => $gL10n->get('GBO_GUESTBOOK')));
$getGboId      = admFuncVariableIsValid($_GET, 'id',         'int');
$getModeration = admFuncVariableIsValid($_GET, 'moderation', 'bool');

if($getModeration && !$gCurrentUser->editGuestbookRight())
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// Navigation faengt hier im Modul an, wenn keine Eintrag direkt aufgerufen wird
if($getGboId === 0)
{
    $gNavigation->clear();
}
$gNavigation->addUrl(CURRENT_URL);

// create html page object
$page = new HtmlPage();
$page->enableModal();

// add rss feed to guestbook
if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/rss_guestbook.php?headline='.$getHeadline, $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname').' - '.$getHeadline));
}

$page->addJavascript('
    function getComments(commentId) {
        // RequestObjekt abschicken und Kommentar laden
        $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/guestbook/get_comments.php?cid=" + commentId + "&moderation=" + '.(int) $getModeration.',
        function(data) {
            $("#comments_" + commentId).html(data);
        });
    }

    function toggleComments(commentId) {
        toggleDiv("admCommentsInvisible_" + commentId);
        toggleDiv("admCommentsVisible_" + commentId);

        if (document.getElementById("comments_" + commentId).innerHTML.length === 0) {
            getComments(commentId);
        } else {
            toggleDiv("comments_" + commentId);
        }
    }

    function toggleDiv(objectId) {
        if ($("#" + objectId).is(":hidden")) {
            $("#" + objectId).show();
        } else {
            $("#" + objectId).hide();
        }
    }
');

// add headline and title of module
if($getModeration)
{
    $page->setHeadline($gL10n->get('GBO_MODERATE_VAR', $getHeadline));
}
else
{
    $page->setHeadline($getHeadline);
}

// ------------------------------------------------------
// SQL-Statements zur Anzeige der Eintraege zusammensetzen
// ------------------------------------------------------
$conditions = '';

// falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
if ($getGboId > 0)
{
    $conditions .= ' AND gbo_id = '. $getGboId;
}
// pruefen ob das Modul Moderation aktiviert ist
if ($gPreferences['enable_guestbook_moderation'] > 0)
{
    if($getModeration)
    {
        $conditions .= ' AND (  gbo_locked = 1
                             OR EXISTS (SELECT 1
                                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                                         WHERE gbc_gbo_id = gbo_id
                                           AND gbc_locked = 1)) ';
    }
    else
    {
        $conditions .= ' AND gbo_locked = 0 ';
    }
}

// Maximale Anzahl an Gaestebucheintraegen ermitteln, die angezeigt werden sollen
$sql = 'SELECT COUNT(*) AS count
          FROM '.TBL_GUESTBOOK.'
         WHERE gbo_org_id = '.$gCurrentOrganization->getValue('org_id').
               $conditions;
$pdoStatement = $gDb->query($sql);
$num_guestbook = (int) $pdoStatement->fetchColumn();

// Anzahl Gaestebucheintraege pro Seite
if($gPreferences['guestbook_entries_per_page'] > 0)
{
    $guestbook_entries_per_page = (int) $gPreferences['guestbook_entries_per_page'];
}
else
{
    $guestbook_entries_per_page = $num_guestbook;
}

// get module menu
$guestbookMenu = $page->getMenu();

if($getGboId === 0 && !$getModeration)
{
    // show link to create new guestbook entry
    $guestbookMenu->addItem('admMenuItemNewEntry', ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php?headline='. $getHeadline,
                            $gL10n->get('GBO_CREATE_ENTRY'), 'add.png');
}

if($getGboId > 0 || $getModeration)
{
    // show link to navigate back to guestbook
    $guestbookMenu->addItem('admMenuItemNavigateBack', ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php?headline='. $getHeadline,
                            $gL10n->get('GBO_BACK_TO_GUESTBOOK'), 'back.png');
}

if(!$getModeration && $gCurrentUser->editGuestbookRight() && $gPreferences['enable_guestbook_moderation'] > 0)
{
    // show link to moderation with number of entries that must be moderated
    $sql = 'SELECT (SELECT COUNT(*) AS count
                      FROM '.TBL_GUESTBOOK.'
                     WHERE gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       AND gbo_locked = 1) AS count_locked_guestbook,
                   (SELECT COUNT(*) AS count
                      FROM '.TBL_GUESTBOOK_COMMENTS.'
                INNER JOIN '.TBL_GUESTBOOK.'
                        ON gbo_id = gbc_gbo_id
                     WHERE gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       AND gbc_locked = 1) AS count_locked_comments
              FROM '.TBL_ORGANIZATIONS.'
             WHERE org_id = '.$gCurrentOrganization->getValue('org_id');
    $pdoStatement = $gDb->query($sql);
    $row = $pdoStatement->fetch();
    $countLockedEntries = $row['count_locked_guestbook'] + $row['count_locked_comments'];

    if($countLockedEntries > 0)
    {
        $guestbookMenu->addItem('admMenuItemModerate', ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php?moderation=1&amp;headline='. $getHeadline,
                                $gL10n->get('GBO_MODERATE_ENTRIES').'<span class="badge">'.$countLockedEntries.'</span>', 'star.png');
    }
}

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of announcements
    $guestbookMenu->addItem('admMenuItemPreferencesGuestbook', ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=guestbook',
                            $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$guestbook = new TableGuestbook($gDb);

// Alle Gaestebucheintraege fuer die aktuelle Seite ermitteln
$sql = 'SELECT *
          FROM '.TBL_GUESTBOOK.' gbo
         WHERE gbo_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               '.$conditions.'
      ORDER BY gbo_timestamp_create DESC
         LIMIT '. $guestbook_entries_per_page.' OFFSET '.$getStart;
$guestbookStatement = $gDb->query($sql);

$countGuestbookEntries = $guestbookStatement->rowCount();

if ($countGuestbookEntries === 0)
{
    // Keine Gaestebucheintraege gefunden
    if ($getGboId > 0)
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
    }
    else
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
}
else
{
    // Gaestebucheintraege auflisten
    while ($row = $guestbookStatement->fetch())
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $guestbook->clear();
        $guestbook->setArray($row);

        $page->addHtml('
        <div class="panel panel-primary" id="gbo_'.$guestbook->getValue('gbo_id').'">
            <div class="panel-heading">
                <div class="pull-left">
                    <img class="admidio-panel-heading-icon" src="'. THEME_URL. '/icons/guestbook.png" alt="'.$guestbook->getValue('gbo_name').'" />'.$guestbook->getValue('gbo_name'));

                    // Falls eine Homepage des Users angegeben wurde, soll der Link angezeigt werden...
                    if (strlen($guestbook->getValue('gbo_homepage')) > 0)
                    {
                        $page->addHtml('
                        <a class="admidio-icon-link" href="'.$guestbook->getValue('gbo_homepage').'" target="_blank"><img src="'. THEME_URL. '/icons/weblinks.png"
                            alt="'.$guestbook->getValue('gbo_homepage').'" title="'.$guestbook->getValue('gbo_homepage').'" /></a>');
                    }

                    // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                    if (strlen($guestbook->getValue('gbo_email')) > 0)
                    {
                        $page->addHtml('
                        <a class="admidio-icon-link" href="mailto:'.$guestbook->getValue('gbo_email').'"><img src="'. THEME_URL. '/icons/email.png"
                            alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $guestbook->getValue('gbo_email')).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $guestbook->getValue('gbo_email')).'" /></a>');
                    }
                $page->addHtml('</div>
                <div class="pull-right text-right">'. $guestbook->getValue('gbo_timestamp_create'));

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($gCurrentUser->editGuestbookRight())
                    {
                        $page->addHtml('
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php?id='.$guestbook->getValue('gbo_id').'&amp;headline='. $getHeadline. '"><img
                            src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=gbo&amp;element_id=gbo_'.
                            $guestbook->getValue('gbo_id').'&amp;database_id='.$guestbook->getValue('gbo_id').'&amp;name='.urlencode($guestbook->getValue('gbo_name')).'"><img
                            src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                    }
                $page->addHtml('</div>
            </div>

            <div class="panel-body">'.
                $guestbook->getValue('gbo_text'));

                // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
                if($getModeration && $guestbook->getValue('gbo_locked') == 1)
                {
                    $page->addHtml('
                    <div class="btn-group" role="group">
                        <button class="btn btn-default" onclick="callUrlHideElement(\'gbo_'.$guestbook->getValue('gbo_id').'\', \'guestbook_function.php?mode=9&id='.$guestbook->getValue('gbo_id').'\')"><img
                            src="'. THEME_URL. '/icons/ok.png" alt="'.$gL10n->get('SYS_UNLOCK').'" />'.$gL10n->get('SYS_UNLOCK').'</button>
                        <button class="btn btn-default" onclick="callUrlHideElement(\'gbo_'.$guestbook->getValue('gbo_id').'\', \'guestbook_function.php?mode=2&id='.$guestbook->getValue('gbo_id').'\')"><img
                            src="'. THEME_URL. '/icons/no.png" alt="'.$gL10n->get('SYS_REMOVE').'" />'.$gL10n->get('SYS_REMOVE').'</button>
                    </div>');
                }

                $conditions = '';

                // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
                if ($gPreferences['enable_guestbook_moderation'] > 0 && $getModeration)
                {
                    $conditions .= ' AND gbc_locked = 1 ';
                }
                else
                {
                    $conditions .= ' AND gbc_locked = 0 ';
                }

                // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
                $sql = 'SELECT *
                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                         WHERE gbc_gbo_id = '.$guestbook->getValue('gbo_id').'
                               '.$conditions.'
                      ORDER BY gbc_timestamp_create ASC';
                $commentStatement = $gDb->query($sql);

                // Falls Kommentare vorhanden sind und diese noch nicht geladen werden sollen...
                if ($getGboId === 0 && $commentStatement->rowCount() > 0)
                {
                    if($gPreferences['enable_intial_comments_loading'] == 1 || $getModeration)
                    {
                        $visibility_show_comments = 'hidden';
                        $display_show_comments    = 'none';
                        $visibility_others        = 'visible';
                        $display_others           = 'block';
                    }
                    else
                    {
                        $visibility_show_comments = 'visible';
                        $display_show_comments    = 'block';
                        $visibility_others        = 'hidden';
                        $display_others           = 'none';
                    }

                    $gboId = $guestbook->getValue('gbo_id');

                    // this link will be shown when comments where loaded
                    $page->addHtml('
                    <a id="admCommentsVisible_'. $gboId. '" class="btn" href="javascript:void(0)" onclick="toggleComments('. $gboId. ')" style="display: '. $display_others. ';"><img
                        src="'. THEME_URL. '/icons/comment.png" alt="'.$gL10n->get('GBO_HIDE_COMMENTS').'" />'.$gL10n->get('GBO_HIDE_COMMENTS').'</a>');

                    // this link will be invisible when comments where loaded
                    $page->addHtml('
                    <a id="admCommentsInvisible_'. $gboId. '" class="btn" href="javascript:void(0)" onclick="toggleComments('. $gboId. ')" style="display: '. $display_show_comments. ';"><img
                        src="'. THEME_URL. '/icons/comment.png" alt="'.$gL10n->get('GBO_SHOW_COMMENTS').'" />'.$gL10n->get('GBO_SHOW_COMMENTS_ON_ENTRY', $commentStatement->rowCount()).'</a>');

                    // Hier ist das div, in das die Kommentare reingesetzt werden
                    $page->addHtml('<div id="comments_'. $gboId. '" class="admidio-guestbook-comments">');
                        if($gPreferences['enable_intial_comments_loading'] == 1 || $getModeration)
                        {
                            // Get setzen da diese Datei eigentlich als Aufruf ueber Javascript gedacht ist
                            $_GET['cid'] = $gboId;
                            $_GET['moderation'] = $getModeration;

                            // read all comments of this guestbook entry
                            ob_start();
                            include('get_comments.php');
                            $page->addHtml(ob_get_contents());
                            ob_end_clean();
                        }
                    $page->addHtml('</div>');
                }

                if ($getGboId === 0 && $commentStatement->rowCount() === 0
                && ($gCurrentUser->commentGuestbookRight() || $gPreferences['enable_gbook_comments4all'] == 1)
                && !$getModeration)
                {
                    // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                    $load_url = ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php?id='.$guestbook->getValue('gbo_id');
                    $page->addHtml('
                    <button type="button" class="btn btn-default" onclick="window.location.href=\''.$load_url.'\'"><img src="'. THEME_URL. '/icons/comment_new.png"
                        alt="'.$gL10n->get('GBO_WRITE_COMMENT').'" title="'.$gL10n->get('GBO_WRITE_COMMENT').'" />'.$gL10n->get('GBO_WRITE_COMMENT').'</button>');
                }

                // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
                // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
                if ($countGuestbookEntries > 0 && $getGboId > 0)
                {
                    ob_start();
                    include('get_comments.php');
                    $page->addHtml(ob_get_contents());
                    ob_end_clean();
                }
            $page->addHtml('</div>');

            // show information about user who edit the recordset
            if(strlen($guestbook->getValue('gbo_usr_id_change')) > 0)
            {
                $page->addHtml('<div class="panel-footer">'.admFuncShowCreateChangeInfoById(0, '', $guestbook->getValue('gbo_usr_id_change'), $guestbook->getValue('gbo_timestamp_change')).'</div>');
            }
        $page->addHtml('</div>');
    }  // Ende While-Schleife
}

// If necessary show links to navigate to next and previous recordsets of the query
$base_url = ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php?headline='. $getHeadline.'&amp;moderation='.$getModeration;
$page->addHtml(admFuncGeneratePagination($base_url, $num_guestbook, $guestbook_entries_per_page, $getStart, true));

// show html of complete page
$page->show();
