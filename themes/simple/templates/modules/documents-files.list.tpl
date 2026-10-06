{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if}


    <table id="adm_documents_files_table" class="table table-hover" width="100%" style="width: 100%;"
        data-label-copy-link="{$l10n->get('SYS_COPY_LINK')}" data-label-copied="{$l10n->get('SYS_COPIED_CLIPBOARD')}">
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
                        {include 'sys-template-parts/list.functions.tpl' data=$row}

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
                        <a class="admidio-icon-link admidio-send-csrf-token" data-url="{$row.url}" data-csrf-token="{$row.csrfToken}">
                            <i class="bi bi-plus-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ADD_TO_DATABASE')}"></i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
{/if}

{literal}
<script>
// Copy the link of a file or folder to the clipboard, so it can be sent to other people.
// The clipboard API is only available in secure contexts (https), otherwise the older
// execCommand is used and if this also fails the link is shown in a prompt.
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('adm_documents_files_table');
    if (!table) return;

    const copyLinkLabel = table.dataset.labelCopyLink;
    const copiedLabel = table.dataset.labelCopied;

    const copyWithExecCommand = function (text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.cssText = 'position: fixed; top: 0; left: 0; opacity: 0;';
        document.body.appendChild(textarea);
        textarea.select();
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }
        textarea.remove();
        return copied;
    };

    const copyText = function (text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () {
                return true;
            }, function () {
                return copyWithExecCommand(text);
            });
        }
        return Promise.resolve(copyWithExecCommand(text));
    };

    // show a check icon and the tooltip "copied" at the icon of the link, within the dropdown of small
    // screens at the icon that opens the dropdown, because the dropdown is closed after the click
    const showCopied = function (link) {
        const dropdown = link.closest('.dropdown');
        const icon = dropdown ? dropdown.querySelector('[data-bs-toggle="dropdown"] i') : link.querySelector('i');
        if (!icon) return;

        // a tooltip is only shown if the element had a title, so create a new one for the message
        const iconClass = icon.className;
        const existingTooltip = bootstrap.Tooltip.getInstance(icon);
        if (existingTooltip) {
            existingTooltip.dispose();
        }
        icon.className = 'bi bi-clipboard-check';
        const copiedTooltip = new bootstrap.Tooltip(icon, {title: copiedLabel, trigger: 'manual'});
        copiedTooltip.show();

        setTimeout(function () {
            copiedTooltip.dispose();
            icon.className = iconClass;
            if (existingTooltip) {
                new bootstrap.Tooltip(icon);
            }
        }, 2000);
    };

    table.addEventListener('click', function (e) {
        const link = e.target.closest('a.admidio-copy-link');
        if (!link) return;
        e.preventDefault();

        copyText(link.href).then(function (copied) {
            if (copied) {
                showCopied(link);
            } else {
                window.prompt(copyLinkLabel, link.href);
            }
        });
    });
});
</script>
{/literal}
