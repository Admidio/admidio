<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['system_language']}
    {if $admidioBetaVersion > 0}
        <div class="alert alert-warning alert-small" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('INS_WARNING_BETA_VERSION')}
        </div>
    {/if}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_next_page']}
</form>
<br /><br />
