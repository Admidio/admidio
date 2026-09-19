<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliEnabled" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_STATUS')}
    </label>
    <div class="col-sm-9">
        <div id="cliEnabled">
            <span class="{$cliEnabledColorClass}"><strong>{$cliEnabledText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliFile" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_FILE')}
    </label>
    <div class="col-sm-9">
        <div id="cliFile"><strong>{$cliFile}</strong>{$cliFileInfo}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliAccounts" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_ACCOUNTS')}
    </label>
    <div class="col-sm-9">
        <div id="cliAccounts">{$cliAccounts}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliAllowedCommands" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_ALLOWED_COMMANDS')}
    </label>
    <div class="col-sm-9">
        <div id="cliAllowedCommands">{$cliAllowedCommands}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliDeniedCommands" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_DENIED_COMMANDS')}
    </label>
    <div class="col-sm-9">
        <div id="cliDeniedCommands">{$cliDeniedCommands}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliActor" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_ACTOR')}
    </label>
    <div class="col-sm-9">
        <div id="cliActor"><span class="{$cliActorColorClass}">{$cliActor}</span></div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliActors" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_ACTORS')}
    </label>
    <div class="col-sm-9">
        <div id="cliActors">{$cliActors}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="cliLogFile" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_COMMAND_LINE_LOG')}
    </label>
    <div class="col-sm-9">
        <div id="cliLogFile"><span class="{$cliLogFileColorClass}">{$cliLogFile}</span></div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <div class="col-sm-12">
        <div id="cliDescription" class="form-text">{$cliDescription}</div>
    </div>
</div>
