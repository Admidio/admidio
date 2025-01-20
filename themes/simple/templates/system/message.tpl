
<div class="message">
    <p class="lead">{$message}</p>

    {if $url != ''}
        {if $showYesNoButtons}
            <button id="adm_button_yes" class="btn btn-primary" type="button" onclick="{$url}">
                <i class="bi bi-check-circle-fill"></i>
                &nbsp;&nbsp;{$l10n->get("SYS_YES")}&nbsp;&nbsp;&nbsp;
            </button>
            <button id="adm_button_no" class="btn btn-secondary" type="button" onclick="history.back()">
                <i class="bi bi-dash-circle-fill"></i>
                &nbsp;{$l10n->get("SYS_NO")}
            </button>
        {else}
            {* when forwarding, always display a next button *}
            <button class="btn btn-primary admidio-margin-bottom" onclick="{$url}">{$l10n->get("SYS_NEXT")}
                <i class="bi bi-arrow-right-circle-fill"></i>
            </button>
        {/if}
    {else}
        {* If not forwarded, then always display a back button *}
        <button class="btn btn-primary admidio-margin-bottom" onclick="history.back()">
            <i class="bi bi-arrow-left-circle-fill"></i>
            {$l10n->get("SYS_BACK")}
        </button>
    {/if}
</div>
