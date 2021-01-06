<?php
/**
 ***********************************************************************************************
 * Show a list of all guestbook entries
 *
 * @copyright 2004-2021 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');

unset($_SESSION['guestbook_entry_request'], $_SESSION['guestbook_comment_request']);

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif((int) $gSettingsManager->get('enable_guestbook_module') === 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require(__DIR__ . '/../../system/login_valid.php');
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
$page = new HtmlPage('admidio-guestbook');

// add rss feed to guestbook
if($gSettingsManager->getBool('enable_rss'))
{
    $page->addRssFile(
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/rss_guestbook.php', array('headline' => $getHeadline)),
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname').' - '.$getHeadline))
    );
}

$page->addJavascript('
    /**
     * @param {int} commentId
     */
    function getComments(commentId) {
        // RequestObjekt abschicken und Kommentar laden
        $.get("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/get_comments.php', array('moderation' => (int) $getModeration)).'&cid=" + commentId, function(data) {
            $("#comments_" + commentId).html(data);
        });
    }

    /**
     * @param {int} commentId
     */
    function toggleComments(commentId) {
        toggleDiv("admCommentsInvisible_" + commentId);
        toggleDiv("admCommentsVisible_" + commentId);

        if (document.getElementById("comments_" + commentId).innerHTML.length === 0) {
            getComments(commentId);
        } else {
            toggleDiv("comments_" + commentId);
        }
    }

    /**
     * @param {string} objectId
     */
    function toggleDiv(objectId) {
        var divElement = $("#" + objectId);
        if (divElement.is(":hidden")) {
            divElement.show();
        } else {
            divElement.hide();
        }
    }
');

// add headline and title of module
if($getModeration)
{
    $page->setHeadline($gL10n->get('GBO_MODERATE_VAR', array($getHeadline)));
}
else
{
    $page->setHeadline($getHeadline);
}

$currOrgId = (int) $gCurrentOrganization->getValue('org_id');

// ------------------------------------------------------
// SQL-Statements zur Anzeige der Eintraege zusammensetzen
// ------------------------------------------------------
$conditionsSpecial = '';
$queryParamsSpecial = array($currOrgId);
// falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
if ($getGboId > 0)
{
    $conditionsSpecial .= ' AND gbo_id = ? ';
    $queryParamsSpecial[] = $getGboId;
}
// pruefen ob das Modul Moderation aktiviert ist
if ((int) $gSettingsManager->get('enable_guestbook_moderation') > 0)
{
    if($getModeration)
    {
        $conditionsSpecial .= ' AND (  gbo_locked = 1
                                    OR EXISTS (SELECT 1
                                                 FROM '.TBL_GUESTBOOK_COMMENTS.'
                                                WHERE gbc_gbo_id = gbo_id
                                                  AND gbc_locked = 1)) ';
    }
    else
    {
        $conditionsSpecial .= ' AND gbo_locked = 0 ';
    }
}

// Maximale Anzahl an Gaestebucheintraegen ermitteln, die angezeigt werden sollen
$sql = 'SELECT COUNT(*) AS count
          FROM '.TBL_GUESTBOOK.'
         WHERE gbo_org_id = ? -- $currOrgId
               '.$conditionsSpecial;
$pdoStatement = $gDb->queryPrepared($sql, $queryParamsSpecial);
$guestbookEntries = (int) $pdoStatement->fetchColumn();

// Anzahl Gaestebucheintraege pro Seite
if($gSettingsManager->getInt('guestbook_entries_per_page') > 0)
{
    $guestbookEntriesPerPage = $gSettingsManager->getInt('guestbook_entries_per_page');
}
else
{
    $guestbookEntriesPerPage = $guestbookEntries;
}

if($getGboId === 0 && !$getModeration)
{
    // show link to create new guestbook entry
    $page->addPageFunctionsMenuItem('menu_item_guestbook_new_entry', $gL10n->get('SYS_WRITE_ENTRY'),
        SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php', array('headline' => $getHeadline)),
        'fa-pencil-alt');
}

if(!$getModeration && $gCurrentUser->editGuestbookRight() && (int) $gSettingsManager->get('enable_guestbook_moderation') > 0)
{
    // show link to moderation with number of entries that must be moderated
    $sql = 'SELECT (SELECT COUNT(*) AS count
                      FROM '.TBL_GUESTBOOK.'
                     WHERE gbo_org_id = ? -- $currOrgId
                       AND gbo_locked = 1) AS count_locked_guestbook,
                   (SELECT COUNT(*) AS count
                      FROM '.TBL_GUESTBOOK_COMMENTS.'
                INNER JOIN '.TBL_GUESTBOOK.'
                        ON gbo_id = gbc_gbo_id
                     WHERE gbo_org_id = ? -- $currOrgId
                       AND gbc_locked = 1) AS count_locked_comments
              FROM '.TBL_ORGANIZATIONS.'
             WHERE org_id = ? -- $currOrgId';
    $pdoStatement = $gDb->queryPrepared($sql, array($currOrgId, $currOrgId, $currOrgId));
    $row = $pdoStatement->fetch();
    $countLockedEntries = $row['count_locked_guestbook'] + $row['count_locked_comments'];

    if($countLockedEntries > 0)
    {
        $page->addPageFunctionsMenuItem('menu_item_guestbook_moderate', $gL10n->get('GBO_MODERATE_ENTRIES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php', array('moderation' => '1', 'headline' => $getHeadline)),
            'fa-tasks', $countLockedEntries);
        /*$guestbookMenu->addItem(
            'admMenuItemModerate', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php', array('moderation' => '1', 'headline' => $getHeadline)),
            $gL10n->get('GBO_MODERATE_ENTRIES').'<span class="badge">'.$countLockedEntries.'</span>', 'fa-tasks'
        );*/
    }
}

$guestbook = new TableGuestbook($gDb);

// Alle Gaestebucheintraege fuer die aktuelle Seite ermitteln
$sql = 'SELECT *
          FROM '.TBL_GUESTBOOK.' AS gbo
         WHERE gbo_org_id = ? -- $currOrgId
               '.$conditionsSpecial.'
      ORDER BY gbo_timestamp_create DESC
         LIMIT '.$guestbookEntriesPerPage.' OFFSET '.$getStart;
$guestbookStatement = $gDb->queryPrepared($sql, $queryParamsSpecial);

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

        $gboId       = (int) $guestbook->getValue('gbo_id');
        $gboName     = $guestbook->getValue('gbo_name');
        $gboHomepage = $guestbook->getValue('gbo_homepage');
        $gboEmail    = $guestbook->getValue('gbo_email');

        $page->addHtml('
        <div class="card admidio-blog" id="gbo_'.$gboId.'">
            <div class="card-header">
                <i class="fas fa-book"></i>'.
                $gL10n->get('SYS_USERNAME_WITH_TIMESTAMP', array($gboName, $guestbook->getValue('gbo_timestamp_create',
                    $gSettingsManager->getString('system_date')), $guestbook->getValue('gbo_timestamp_create', $gSettingsManager->getString('system_time')))));

                // Falls eine Homepage des Users angegeben wurde, soll der Link angezeigt werden...
                if (strlen($gboHomepage) > 0)
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="'.$gboHomepage.'" target="_blank">
                        <i class="fas fa-link" data-toggle="tooltip" title="'.$gboHomepage.'"></i></a>');
                }

                // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
                if (strlen($gboEmail) > 0)
                {
                    $page->addHtml('
                    <a class="admidio-icon-link" href="mailto:'.$gboEmail.'">
                        <i class="fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', array($gboEmail)).'"></i></a>');
                }

                // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                if ($gCurrentUser->editGuestbookRight())
                {
                    $page->addHtml('
                    <div class="dropdown float-right">
                        <a class="" href="#" role="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chevron-circle-down" data-toggle="tooltip"></i></a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item btn" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php', array('id' => $gboId, 'headline' => $getHeadline)). '">
                                <i class="fas fa-edit"></i> '.$gL10n->get('SYS_EDIT').'</a>
                            <a class="dropdown-item btn openPopup" href="javascript:void(0);"
                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'gbo',
                                'element_id' => 'gbo_'.$gboId, 'database_id' => $gboId, 'name' => $gboName)).'">
                                <i class="fas fa-trash-alt"></i> '.$gL10n->get('SYS_DELETE').'</a>
                        </div>
                    </div>');
                }
            $page->addHtml('
            </div>

            <div class="card-body">'.
                $guestbook->getValue('gbo_text'));

                // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
                if($getModeration && $guestbook->getValue('gbo_locked') == 1)
                {
                    $page->addHtml('
                    <div class="btn-group" role="group">
                        <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbo_'.$gboId.'\', \''.SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 9, 'id' => $gboId)).'\')">
                            <i class=\"fas fa-check\"></i>'.$gL10n->get('SYS_UNLOCK').'</button>
                        <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbo_'.$gboId.'\', \''.SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 2, 'id' => $gboId)).'\')">
                            <i class="fas fa-trash-alt"></i>'.$gL10n->get('SYS_REMOVE').'</button>
                    </div>');
                }

                // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
                if ((int) $gSettingsManager->get('enable_guestbook_moderation') > 0 && $getModeration)
                {
                    $conditions = ' AND gbc_locked = 1 ';
                }
                else
                {
                    $conditions = ' AND gbc_locked = 0 ';
                }

                // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
                $sql = 'SELECT *
                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                         WHERE gbc_gbo_id = ? -- $guestbook->getValue(\'gbo_id\')
                               '.$conditions.'
                      ORDER BY gbc_timestamp_create ASC';
                $commentStatement = $gDb->queryPrepared($sql, array($gboId));

                // Falls Kommentare vorhanden sind und diese noch nicht geladen werden sollen...
                if ($getGboId === 0 && $commentStatement->rowCount() > 0)
                {
                    if($gSettingsManager->getBool('enable_intial_comments_loading') || $getModeration)
                    {
                        $displayShowComments = 'none';
                        $displayOthers       = 'block';
                    }
                    else
                    {
                        $displayShowComments = 'block';
                        $displayOthers       = 'none';
                    }

                    // this link will be shown when comments where loaded
                    $page->addHtml('
                    <a id="admCommentsVisible_'. $gboId. '" class="btn" href="javascript:void(0)" onclick="toggleComments('. $gboId. ')" style="display: '. $displayOthers. ';">
                        <i class="fas fa-comment-slash"></i>'.$gL10n->get('GBO_HIDE_COMMENTS').'</a>');

                    // this link will be invisible when comments where loaded
                    $page->addHtml('
                    <a id="admCommentsInvisible_'. $gboId. '" class="btn" href="javascript:void(0)" onclick="toggleComments('. $gboId. ')" style="display: '. $displayShowComments. ';">
                        <i class="fas fa-comment"></i>'.$gL10n->get('GBO_SHOW_COMMENTS_ON_ENTRY', array($commentStatement->rowCount())).'</a>');

                    // Hier ist das div, in das die Kommentare reingesetzt werden
                    $page->addHtml('<div id="comments_'. $gboId. '" class="admidio-guestbook-comments">');
                        if($gSettingsManager->getBool('enable_intial_comments_loading') || $getModeration)
                        {
                            // Get setzen da diese Datei eigentlich als Aufruf ueber Javascript gedacht ist
                            $_GET['cid'] = $gboId;
                            $_GET['moderation'] = $getModeration;

                            // read all comments of this guestbook entry
                            ob_start();
                            require(__DIR__ . '/get_comments.php');
                            $fileContent = ob_get_contents();
                            ob_end_clean();

                            $page->addHtml($fileContent);
                        }
                    $page->addHtml('</div>');
                }

                if ($getGboId === 0 && $commentStatement->rowCount() === 0
                && ($gCurrentUser->commentGuestbookRight() || $gSettingsManager->getBool('enable_gbook_comments4all'))
                && !$getModeration)
                {
                    // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                    $loadUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php', array('id' => $gboId));
                    $page->addHtml('
                    <button type="button" class="btn btn-secondary" onclick="window.location.href=\''.$loadUrl.'\'">
                        <i class="fas fa-pencil-alt"></i>'.$gL10n->get('GBO_WRITE_COMMENT').'</button>');
                }

                // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
                // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
                if ($countGuestbookEntries > 0 && $getGboId > 0)
                {
                    ob_start();
                    require(__DIR__ . '/get_comments.php');
                    $fileContent = ob_get_contents();
                    ob_end_clean();

                    $page->addHtml($fileContent);
                }
            $page->addHtml('</div>');

            // show information about user who edit the recordset
            if(strlen($guestbook->getValue('gbo_usr_id_change')) > 0)
            {
                $page->addHtml('<div class="card-footer">'.admFuncShowCreateChangeInfoById(
                    0, '',
                    (int) $guestbook->getValue('gbo_usr_id_change'), $guestbook->getValue('gbo_timestamp_change')
                ).'</div>');
            }
        $page->addHtml('</div>');
    }  // Ende While-Schleife
}

// If necessary show links to navigate to next and previous recordsets of the query
$baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook.php', array('headline' => $getHeadline, 'moderation' => $getModeration));
$page->addHtml(admFuncGeneratePagination($baseUrl, $guestbookEntries, $guestbookEntriesPerPage, $getStart));

// show html of complete page
$page->show();
