
<div id="installation-message">
    <h3>{$messageHeadline}</h3>

    {if $outputMode == "error"}
        <p>{$l10n->get("SYS_PROCESSING_ERROR_DESC")}</p>
        <p>
            <div class="alert alert-danger alert-small" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <strong>{$messageText}</strong>
            </div>
        </p>
    {elseif $outputMode == "success"}
        <p>
            <div class="alert alert-success alert-small" role="alert">
                <i class="fas fa-check"></i>
                <strong>{$messageText}</strong>
            </div>
        </p>
    {/if}

    <p>{$content}</p>
</div>
