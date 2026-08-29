{* Who made the plugin, below its description. Shown only for a plugin that names one. *}
{if $data.author neq '' || $data.url neq ''}
    <div class="ms-3 mt-1"><small class="text-body-secondary">
        {if $data.author neq ''}{$l10n->get('SYS_AUTHOR')}: {$data.author}{/if}
        {if $data.url neq ''}
            {if $data.author neq ''}({/if}<a href="{$data.url}" target="_blank" rel="noopener noreferrer"
                data-bs-toggle="tooltip" title="{$data.url}">{$data.urlHost}</a>{if $data.author neq ''}){/if}
        {/if}
    </small></div>
{/if}
