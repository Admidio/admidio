
<div id="installation-message">
    <h3>{$messageHeadline}</h3>

    {if $outputMode == "error"}
        <p>{$l10n->get("SYS_PROCESSING_ERROR_DESC")}</p>
        <p>
            <div class="alert alert-danger alert-small" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>{$messageText}</strong>
            </div>
        </p>
    {elseif $outputMode == "success"}
        <p>
            <div class="alert alert-success alert-small" role="alert">
                <i class="bi bi-check-lg"></i>
                <strong>{$messageText}</strong>
            </div>
        </p>
    {/if}

    <p>
        <a id="next_page" class="btn btn-secondary admidio-margin-bottom" href="{$destinationUrl}">
            <i class="bi {$buttonIcon}" data-bs-toggle="tooltip" title="{$buttonText}"></i>{$buttonText}
        </a>
    </p>
</div>
