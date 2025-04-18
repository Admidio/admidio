<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('PLG_LATEST_FILES_HEADLINE')}</h3>

    {if count($documentsFiles) > 0}
        <ul class="list-group list-group-flush">
            {foreach $documentsFiles as $document}
                <li class="list-group-item" style="word-break: break-word;">
                    <a class="icon-link" data-bs-toggle="tooltip" data-html="true" title="{$document.tooltip}" href="{$urlAdmidio}/modules/documents-files.php?mode=download&file_uuid={$document.uuid}">
                        <i class="bi {$document.icon}"></i>{$document.fileName}.{$document.fileExtension}</a>
                </li>
            {/foreach}
            <li class="list-group-item">
                <a href="{$urlAdmidio}/modules/documents-files.php">{$l10n->get('PLG_LATEST_FILES_MORE_DOWNLOADS')}</a>
            </li>
        </ul>
    {else}
        {$message}
    {/if}
</div>
