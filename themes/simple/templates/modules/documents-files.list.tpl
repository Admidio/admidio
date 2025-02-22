{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if}


    <table id="documents-files-table" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th style="word-break: break-word;">{$l10n->get('SYS_DATE_MODIFIED')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th class="text-end">{$l10n->get('SYS_COUNTER')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="row_{$row.uuid}">
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td style="word-break: break-word;"><a href="{$row.url}">{$row.name}</a>
                        {if strlen($row.description) > 0}
                            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                title="{$l10n->get('SYS_DESCRIPTION')}" data-bs-content="{$row.description}"></i>
                        {/if}
                    </td>
                    <td>{$row.timestamp}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">{$row.counter}</td>
                    <td class="text-end">
                        {if {array_key_exists array=$row key='actions'}}
                            <span class="d-none d-lg-inline admidio-functions">
                                {foreach $row.actions as $actionItem}
                                    <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                        data-buttons="yes-no" data-message="{$l10n->get('SYS_DELETE_ENTRY', array({$row.name}))}" data-href="{$actionItem.dataHref}"
                                            {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                        <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                                {/foreach}
                            </span>
                            <div class="dropdown d-lg-none">
                                <a id="adm_dropdown_menu_button_{$row.uuid}" class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-display="static">
                                    <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adm_dropdown_menu_button_{$row.uuid}">
                                    {foreach $row.actions as $actionItem}
                                        <li>
                                            <a {if isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                                                data-buttons="yes-no" data-message="{$l10n->get('SYS_DELETE_ENTRY', array({$row.name}))}" data-href="{$actionItem.dataHref}"
                                                    {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                                                <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                                        </li>
                                    {/foreach}
                                </ul>
                            </div>
                        {/if}
                        {if $row.existsInFileSystem == false}
                            <i class="bi bi-exclamation-triangle-fill" style="color:red;" data-bs-toggle="popover" data-bs-trigger="hover click" data-bs-placement="left"
                               title="{$l10n->get('SYS_WARNING')}" data-bs-content="{if $row.folder}{$l10n->get('SYS_FOLDER_NOT_EXISTS')}{else}{$l10n->get('SYS_FILE_NOT_EXIST_DELETE_FROM_DB')}{/if}"></i>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>


{if count($unregisteredList) > 0}
    <h2>{$l10n->get('SYS_UNMANAGED_FILES')}</h2>
    <p class="lead">{$l10n->get('SYS_ADDITIONAL_FILES')}</p>
    <div class="table-responsive">
        <table id="documents-files-unregistered-table" class="table table-hover" width="100%" style="width: 100%;">
            <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            {foreach $unregisteredList as $row}
                <tr>
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td>{$row.name}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">
                        <a class="admidio-icon-link" href="{$row.url}">
                            <i class="bi bi-plus-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ADD_TO_DATABASE')}"></i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
{/if}
