{*
    Adding a plugin from a file the administrator already has.

    Stage 6 adds a second card below this one for the plugins published for Admidio. It is not here
    yet: an empty card would look like a store that failed to load rather than one that does not
    exist.
*}
<div class="card admidio-card">
    <div class="card-header">
        <i class="bi bi-upload me-1"></i>{$l10n->get('SYS_PLUGIN_INSTALL_FROM_FILE')}
    </div>
    <div class="card-body">
        <form {foreach $attributes as $attribute}
                {$attribute@key}="{$attribute}"
            {/foreach}>

            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
            {include 'sys-template-parts/form.file.tpl' data=$elements['userfile']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['plugin_replace']}
            {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_upload_plugin']}

            <div class="form-alert" style="display: none;">&nbsp;</div>
        </form>
    </div>
</div>

{$javascript}
