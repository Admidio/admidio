
<div class="modal-header">
    <h3 class="modal-title">{$messageHeadline}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">{$message}</div>

<div class="modal-footer">
    {if $url != ''}
        {if $showYesNoButtons}
            <button id="admButtonYes" class="btn btn-primary" type="button" onclick="{$url}">
                <i class="bi bi-check-lg-circle"></i>
                &nbsp;&nbsp;{$l10n->get("SYS_YES")}&nbsp;&nbsp;&nbsp;
            </button>
            <button id="admButtonNo" class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                <i class="fas fa-minus-circle"></i>
                &nbsp;{$l10n->get("SYS_NO")}
            </button>
        {else}
            {* when forwarding, always display a next button *}
            <button class="btn btn-primary admidio-margin-bottom" onclick="{$url}">{$l10n->get("SYS_NEXT")}
                <i class="fas fa-arrow-circle-right"></i>
            </button>
        {/if}
        <div id="status-message" class="mt-4 w-100"></div>
    {/if}
</div>
