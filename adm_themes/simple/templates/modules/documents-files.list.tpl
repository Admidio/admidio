{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="fas fa-info-circle"></i>{$infoAlert}</div>
{/if}

<div class="table-responsive">
    <table id="documents-files-table" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th><i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th>{$l10n->get('SYS_DATE_MODIFIED')}</th>
                <th class="text-right">{$l10n->get('SYS_SIZE')}</th>
                <th class="text-right">{$l10n->get('SYS_COUNTER')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="row_{$row.id}">
                    <td><a class="admidio-icon-link" href="{$row.url}"><i class="{$row.icon}" data-toggle="tooltip" title="{$row.title}"></i></a></td>
                    <td><a href="{$row.url}">{$row.name}</a>
                        {if strlen($row.description) > 0}
                            <i class="fas fa-info-circle admidio-info-icon" data-toggle="popover"
                                data-html="true" data-trigger="hover click" data-placement="auto"
                                title="{$l10n->get('SYS_DESCRIPTION')}" data-content="{$row.description}"></i>
                        {/if}
                    </td>
                    <td>{$row.timestamp}</td>
                    <td class="text-right">{$row.size}</td>
                    <td class="text-right">{$row.counter}</td>
                    <td class="text-right">
                        {if array_key_exists('actions', $row)}
                            {foreach $row.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                            {/foreach}
                        {/if}
                        {if $row.existsInFileSystem == false}
                            <i class="fas fa-exclamation-triangle" style="color:red;" data-toggle="popover" data-trigger="hover click" data-placement="left"
                               title="{$l10n->get('SYS_WARNING')}" data-content="{if $row.folder}{$l10n->get('SYS_FOLDER_NOT_EXISTS')}{else}{$l10n->get('SYS_FILE_NOT_EXIST_DELETE_FROM_DB')}{/if}"></i>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>

{if count($unregisteredList) > 0}
    <h2>{$l10n->get('SYS_UNMANAGED_FILES')}</h2>
    <p class="lead admidio-max-with">{$l10n->get('SYS_ADDITIONAL_FILES')}</p>
    <div class="table-responsive">
        <table id="documents-files-unregistered-table" class="table table-hover" width="100%" style="width: 100%;">
            <thead>
            <tr>
                <th><i class="fas fa-fw fa-folder-open" data-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th class="text-right">{$l10n->get('SYS_SIZE')}</th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            {foreach $unregisteredList as $row}
                <tr>
                    <td><i class="{$row.icon}" data-toggle="tooltip" title="{$row.title}"></i></td>
                    <td>{$row.name}</td>
                    <td class="text-right">{$row.size}</td>
                    <td class="text-right">
                        <a class="admidio-icon-link" href="{$row.url}">
                            <i class="fas fa-plus-circle" data-toggle="tooltip" title="{$l10n->get('SYS_ADD_TO_DATABASE')}"></i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
{/if}
