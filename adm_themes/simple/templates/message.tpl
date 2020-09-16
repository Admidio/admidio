
<div class="message">
    <p class="lead">{$message}</p>

    {if $forwardUrl != ''}
        {if $showYesNoButtons}
            <button id="admButtonYes" class="btn btn-primary" type="button" onclick="self.location.href='{$forwardUrl}'">
                <i class="fas fa-check-circle"></i>
                &nbsp;&nbsp;{$l10n->get("SYS_YES")}&nbsp;&nbsp;&nbsp;
            </button>
            <button id="admButtonNo" class="btn btn-secondary" type="button" onclick="history.back()">
                <i class="fas fa-minus-circle"></i>
                &nbsp;{$l10n->get("SYS_NO")}
            </button>
        {else}
            {* when forwarding, always display a next button *}
            <button class="btn btn-primary admidio-margin-bottom" onclick="self.location.href='{$forwardUrl}'">{$l10n->get("SYS_NEXT")}
                <i class="fas fa-arrow-circle-right"></i>
            </button>
        {/if}
    {else}
        {* If not forwarded, then always display a back button *}
        <button class="btn btn-primary admidio-margin-bottom" onclick="history.back()">
            <i class="fas fa-arrow-circle-left"></i>
            {$l10n->get("SYS_BACK")}
        </button>
    {/if}
</div>
