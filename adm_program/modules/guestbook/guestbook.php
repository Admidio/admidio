<?php
/**
 ***********************************************************************************************
 * Show a list of all guestbook entries
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * start      : Position of query recordset where the visual output should start
 * moderation : false (Default) - Guestbook view
 *              true - Moderation mode, every entry could be released
 * gbo_uuid   : UUID of one guestbook entry that should be shown
 ***********************************************************************************************
 */

use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;

require_once(__DIR__ . '/../../system/common.php');

try {
    unset($_SESSION['guestbook_entry_request'], $_SESSION['guestbook_comment_request']);

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->get('forum_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getStart = admFuncVariableIsValid($_GET, 'start', 'int');
    $getModeration = admFuncVariableIsValid($_GET, 'moderation', 'bool');
    $getGboUuid = admFuncVariableIsValid($_GET, 'gbo_uuid', 'uuid');

    if ($getModeration && !$gCurrentUser->editGuestbookRight()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // add url to navigation stack
    if ($getGboUuid !== '') {
        $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_FORUM'));
    } elseif ($getModeration) {
        $gNavigation->addUrl(CURRENT_URL, $gL10n->get('GBO_MODERATE_VAR', array($gL10n->get('SYS_FORUM'))));
    } else {
        $gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('SYS_FORUM'), 'bi-book-half');
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-guestbook');

    // add rss feed to guestbook
    if ($gSettingsManager->getBool('enable_rss') && (int)$gSettingsManager->get('forum_module_enabled') === 1) {
        $page->addRssFile(
            ADMIDIO_URL . FOLDER_MODULES . '/guestbook/rss_guestbook.php?organization_short_name=' . $gCurrentOrganization->getValue('org_shortname'),
            $gL10n->get('SYS_RSS_FEED_FOR_VAR', array($gCurrentOrganization->getValue('org_longname') . ' - ' . $gL10n->get('SYS_FORUM')))
        );
    }

    $page->addJavascript('
    $(".admidio-toggle-comments").click(function() {
        const uuid = $(this).data("uuid");
        toggleDiv("admCommentsInvisible_" + uuid);
        toggleDiv("admCommentsVisible_" + uuid);

        if (document.getElementById("comments_" + uuid).innerHTML.length === 0) {
            // Send request object and load comment
            $.get("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/get_comments.php', array('moderation' => (int)$getModeration)) . '&gbo_uuid=" + uuid, function(data) {
                $("#comments_" + uuid).html(data);
            });
        } else {
            toggleDiv("comments_" + uuid);
        }
    });

    /**
     * @param {string} objectId
     */
    function toggleDiv(objectId) {
        var divElement = $("#" + objectId);
        if (divElement.is(":hidden")) {
            divElement.attr("class", "d-block mt-2 icon-link admidio-toggle-comments");
        } else {
            divElement.attr("class", "d-none mt-2 icon-link admidio-toggle-comments");
        }
    }
    ', true);

    // add headline and title of module
    if ($getModeration) {
        $page->setHeadline($gL10n->get('GBO_MODERATE_VAR', array($gL10n->get('SYS_FORUM'))));
    } else {
        $page->setHeadline($gL10n->get('SYS_FORUM'));
    }

    // ------------------------------------------------------
    // SQL-Statements zur Anzeige der Eintraege zusammensetzen
    // ------------------------------------------------------
    $conditionsSpecial = '';
    $queryParamsSpecial = array($gCurrentOrgId);
    // falls eine id fuer einen bestimmten Gaestebucheintrag uebergeben worden ist...
    if ($getGboUuid !== '') {
        $conditionsSpecial .= ' AND gbo_uuid = ? ';
        $queryParamsSpecial[] = $getGboUuid;
    }
    // pruefen ob das Modul Moderation aktiviert ist
    if ((int)$gSettingsManager->get('enable_guestbook_moderation') > 0) {
        if ($getModeration) {
            $conditionsSpecial .= ' AND (  gbo_locked = true
                                    OR EXISTS (SELECT 1
                                                 FROM ' . TBL_GUESTBOOK_COMMENTS . '
                                                WHERE gbc_gbo_id = gbo_id
                                                  AND gbc_locked = true)) ';
        } else {
            $conditionsSpecial .= ' AND gbo_locked = false ';
        }
    }

    // Maximale Anzahl an Gaestebucheintraegen ermitteln, die angezeigt werden sollen
    $sql = 'SELECT COUNT(*) AS count
          FROM ' . TBL_GUESTBOOK . '
         WHERE gbo_org_id = ? -- $gCurrentOrgId
               ' . $conditionsSpecial;
    $pdoStatement = $gDb->queryPrepared($sql, $queryParamsSpecial);
    $guestbookEntries = (int)$pdoStatement->fetchColumn();

    // Anzahl Gaestebucheintraege pro Seite
    if ($gSettingsManager->getInt('forum_topics_per_page') > 0) {
        $guestbookEntriesPerPage = $gSettingsManager->getInt('forum_topics_per_page');
    } else {
        $guestbookEntriesPerPage = $guestbookEntries;
    }

    if ($getGboUuid === '' && !$getModeration) {
        // show link to create new guestbook entry
        $page->addPageFunctionsMenuItem(
            'menu_item_guestbook_new_entry',
            $gL10n->get('SYS_WRITE_ENTRY'),
            ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_new.php',
            'bi bi-pencil-fill'
        );
    }

    if (!$getModeration && $gCurrentUser->editGuestbookRight() && (int)$gSettingsManager->get('enable_guestbook_moderation') > 0) {
        // show link to moderation with number of entries that must be moderated
        $sql = 'SELECT (SELECT COUNT(*) AS count
                      FROM ' . TBL_GUESTBOOK . '
                     WHERE gbo_org_id = ? -- $gCurrentOrgId
                       AND gbo_locked = true) AS count_locked_guestbook,
                   (SELECT COUNT(*) AS count
                      FROM ' . TBL_GUESTBOOK_COMMENTS . '
                INNER JOIN ' . TBL_GUESTBOOK . '
                        ON gbo_id = gbc_gbo_id
                     WHERE gbo_org_id = ? -- $gCurrentOrgId
                       AND gbc_locked = true) AS count_locked_comments
              FROM ' . TBL_ORGANIZATIONS . '
             WHERE org_id = ? -- $gCurrentOrgId';
        $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $gCurrentOrgId, $gCurrentOrgId));
        $row = $pdoStatement->fetch();
        $countLockedEntries = $row['count_locked_guestbook'] + $row['count_locked_comments'];

        if ($countLockedEntries > 0) {
            $page->addPageFunctionsMenuItem(
                'menu_item_guestbook_moderate',
                $gL10n->get('GBO_MODERATE_ENTRIES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('moderation' => '1')),
                'bi-list-check',
                '',
                $countLockedEntries
            );
        }
    }

    $guestbook = new Topic($gDb);

    // Alle Gaestebucheintraege fuer die aktuelle Seite ermitteln
    $sql = 'SELECT *
          FROM ' . TBL_GUESTBOOK . ' AS gbo
         WHERE gbo_org_id = ? -- $gCurrentOrgId
               ' . $conditionsSpecial . '
      ORDER BY gbo_timestamp_create DESC
         LIMIT ' . $guestbookEntriesPerPage . ' OFFSET ' . $getStart;
    $guestbookStatement = $gDb->queryPrepared($sql, $queryParamsSpecial);

    $countGuestbookEntries = $guestbookStatement->rowCount();

    if ($countGuestbookEntries === 0) {
        // Keine Gaestebucheintraege gefunden
        if ($getGboUuid !== '') {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRY') . '</p>');
        } else {
            $page->addHtml('<p>' . $gL10n->get('SYS_NO_ENTRIES') . '</p>');
        }
    } else {
        // Gaestebucheintraege auflisten
        while ($row = $guestbookStatement->fetch()) {
            // GB-Objekt initialisieren und neuen DS uebergeben
            $guestbook->clear();
            $guestbook->setArray($row);

            $gboUuid = $guestbook->getValue('gbo_uuid');
            $gboName = $guestbook->getValue('gbo_name');
            $gboHomepage = $guestbook->getValue('gbo_homepage');
            $gboEmail = $guestbook->getValue('gbo_email');

            $page->addHtml('
        <div class="card admidio-blog" id="gbo_' . $gboUuid . '">
            <div class="card-header">
                <i class="bi bi-book-half"></i>' .
                $gL10n->get('SYS_USERNAME_WITH_TIMESTAMP', array($gboName, $guestbook->getValue(
                    'gbo_timestamp_create',
                    $gSettingsManager->getString('system_date')
                ), $guestbook->getValue('gbo_timestamp_create', $gSettingsManager->getString('system_time')))));

            // Falls eine Homepage des Users angegeben wurde, soll der Link angezeigt werden...
            if (strlen($gboHomepage) > 0) {
                $page->addHtml('
                    <a class="admidio-icon-link" href="' . $gboHomepage . '" target="_blank">
                        <i class="bi bi-link" data-bs-toggle="tooltip" title="' . $gboHomepage . '"></i></a>');
            }

            // Falls eine Mailadresse des Users angegeben wurde, soll ein Maillink angezeigt werden...
            if (strlen($gboEmail) > 0) {
                $page->addHtml('
                    <a class="admidio-icon-link" href="mailto:' . $gboEmail . '">
                        <i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SEND_EMAIL_TO', array($gboEmail)) . '"></i></a>');
            }

            // aendern & loeschen duerfen nur User mit den gesetzten Rechten
            if ($gCurrentUser->editGuestbookRight()) {
                $page->addHtml('
                    <div class="dropdown float-end">
                        <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_new.php', array('gbo_uuid' => $gboUuid)) . '">
                                <i class="bi bi-pencil-square"></i> ' . $gL10n->get('SYS_EDIT') . '</a>
                            </li>
                            <li><a class="dropdown-item admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                    data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($gboName)) . '"
                                    data-href="callUrlHideElement(\'gbo_' . $gboUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_function.php', array('mode' => 'delete_entry', 'gbo_uuid' => $gboUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                                    <i class="bi bi-trash"></i> ' . $gL10n->get('SYS_DELETE') . '</a>
                            </li>
                        </ul>
                    </div>');
            }
            $page->addHtml('
            </div>

            <div class="card-body">' .
                $guestbook->getValue('gbo_text'));

            // Buttons zur Freigabe / Loeschen des gesperrten Eintrags
            if ($getModeration && $guestbook->getValue('gbo_locked') == 1) {
                $page->addHtml('
                    <div class="btn-group" role="group">
                        <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbo_' . $gboUuid . '\', \'' . SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 'moderate_entry', 'gbo_uuid' => $gboUuid)) . '\')">
                            <i class="bi bi-check-lg"></i>' . $gL10n->get('SYS_UNLOCK') . '</button>
                        <button class="btn btn-secondary" onclick="callUrlHideElement(\'gbo_' . $gboUuid . '\', \'' . SecurityUtils::encodeUrl('guestbook_function.php', array('mode' => 'delete_entry', 'gbo_uuid' => $gboUuid)) . '\')">
                            <i class="bi bi-trash"></i>' . $gL10n->get('SYS_REMOVE') . '</button>
                    </div>');
            }

            // falls Eintraege freigeschaltet werden muessen, dann diese nur anzeigen, wenn Rechte vorhanden
            if ((int)$gSettingsManager->get('enable_guestbook_moderation') > 0 && $getModeration) {
                $conditions = ' AND gbc_locked = true ';
            } else {
                $conditions = ' AND gbc_locked = false ';
            }

            // Alle Kommentare zu diesem Eintrag werden nun aus der DB geholt...
            $sql = 'SELECT *
                          FROM ' . TBL_GUESTBOOK_COMMENTS . '
                         WHERE gbc_gbo_id = ? -- (int) $guestbook->getValue(\'gbo_id\')
                               ' . $conditions . '
                      ORDER BY gbc_timestamp_create ASC';
            $commentStatement = $gDb->queryPrepared($sql, array($guestbook->getValue('gbo_id')));

            // Falls Kommentare vorhanden sind und diese noch nicht geladen werden sollen...
            if ($getGboUuid === '' && $commentStatement->rowCount() > 0) {
                if ($gSettingsManager->getBool('enable_intial_comments_loading') || $getModeration) {
                    $displayShowComments = 'd-none';
                    $displayOthers = 'd-block';
                } else {
                    $displayShowComments = 'd-block';
                    $displayOthers = 'd-none';
                }

                // this link will be shown when comments where loaded
                $page->addHtml('
                    <a id="admCommentsVisible_' . $gboUuid . '" class="' . $displayOthers . ' mt-2 icon-link admidio-toggle-comments" href="javascript:void(0)" data-uuid="' . $gboUuid . '">
                        <i class="bi bi-eye-slash-fill"></i>' . $gL10n->get('GBO_HIDE_COMMENTS') . '</a>');

                // this link will be invisible when comments where loaded
                $page->addHtml('
                    <a id="admCommentsInvisible_' . $gboUuid . '" class="' . $displayShowComments . ' mt-2 icon-link admidio-toggle-comments" href="javascript:void(0)" data-uuid="' . $gboUuid . '">
                        <i class="bi bi-eye-fill"></i>' . $gL10n->get('GBO_SHOW_COMMENTS_ON_ENTRY', array($commentStatement->rowCount())) . '</a>');

                // Hier ist das div, in das die Kommentare reingesetzt werden
                $page->addHtml('<div id="comments_' . $gboUuid . '" class="admidio-guestbook-comments">');
                if ($gSettingsManager->getBool('enable_intial_comments_loading') || $getModeration) {
                    // Get setzen da diese Datei eigentlich als Aufruf ueber Javascript gedacht ist
                    $_GET['gbo_uuid'] = $gboUuid;
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

            if ($getGboUuid === '' && $commentStatement->rowCount() === 0
                && ($gCurrentUser->commentGuestbookRight() || $gSettingsManager->getBool('enable_gbook_comments4all'))
                && !$getModeration) {
                // Falls keine Kommentare vorhanden sind, aber das Recht zur Kommentierung, wird der Link zur Kommentarseite angezeigt...
                $loadUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook_comment_new.php', array('gbo_uuid' => $gboUuid));
                $page->addHtml('
                    <button type="button" class="btn btn-primary" onclick="window.location.href=\'' . $loadUrl . '\'">
                        <i class="bi bi-pencil-fill"></i>' . $gL10n->get('GBO_WRITE_COMMENT') . '</button>');
            }

            // Falls eine ID uebergeben wurde und der dazugehoerige Eintrag existiert,
            // werden unter dem Eintrag die dazugehoerigen Kommentare (falls welche da sind) angezeigt.
            if ($countGuestbookEntries > 0 && $getGboUuid !== '') {
                ob_start();
                require(__DIR__ . '/get_comments.php');
                $fileContent = ob_get_contents();
                ob_end_clean();

                $page->addHtml($fileContent);
            }
            $page->addHtml('</div>');

            // show information about user who edit the recordset
            if ((int)$guestbook->getValue('gbo_usr_id_change') > 0) {
                $page->addHtml('<div class="card-footer">' . admFuncShowCreateChangeInfoById(
                        0,
                        '',
                        (int)$guestbook->getValue('gbo_usr_id_change'),
                        $guestbook->getValue('gbo_timestamp_change')
                    ) . '</div>');
            }
            $page->addHtml('</div>');
        }  // Ende While-Schleife
    }

    // If necessary show links to navigate to next and previous recordsets of the query
    $baseUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('moderation' => $getModeration));
    $page->addHtml(admFuncGeneratePagination($baseUrl, $guestbookEntries, $guestbookEntriesPerPage, $getStart));

    // show html of complete page
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
