
<div class="modal-header">
    <h3 class="modal-title">{$messageHeadline}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">{$message}</div>

<div class="modal-footer">
    {if $url != ''}
        {if $showYesNoButtons}
            <button id="admButtonYes" class="btn btn-primary" type="button" onclick="{$url}">
                <i class="bi bi-check-circle-fill"></i>
                &nbsp;&nbsp;{$l10n->get("SYS_YES")}&nbsp;&nbsp;&nbsp;
            </button>
            <button id="admButtonNo" class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                <i class="bi bi-dash-circle-fill"></i>
                &nbsp;{$l10n->get("SYS_NO")}
            </button>
        {else}
            {* when forwarding, always display a next button *}
            <button class="btn btn-primary admidio-margin-bottom" onclick="{$url}">{$l10n->get("SYS_NEXT")}
                <i class="bi bi-arrow-right-circle-fill"></i>
            </button>
        {/if}
        <div id="statusMessage" class="mt-4 w-100"></div>
    {/if}
</div>
