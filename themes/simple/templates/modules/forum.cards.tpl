{foreach $cards as $forumTopic}
    <div class="card admidio-blog" id="adm_topic_{$forumTopic.uuid}">
        <div class="card-header">
            <i class="bi bi-book-half"></i>{$forumTopic.title}

            {if $forumTopic.editable}
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
                </div>
            {/if}
        </div>

        <div class="card-body">
            {$forumTopic.text}
        </div>
    </div>
{/foreach}
