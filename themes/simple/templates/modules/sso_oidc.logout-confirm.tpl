<style>
    #adm_sidebar,
    .admidio-breadcrumb,
    .admidio-content-header,
    #adm_imprint {
        display: none;
    }

    .admidio-content-col {
        flex: 0 0 100%;
        width: 100%;
        max-width: 100%;
    }

    #adm_content {
        max-width: none;
        padding: 2rem;
    }

    .oidc-logout-dialog {
        width: min(600px, calc(100vw - 4rem));
        margin: 0 auto;
    }
</style>

<div class="oidc-logout-dialog">
    <div class="card shadow">
        <div class="card-header">
            <h1 class="h4 mb-0 text-center">{$oidcLogoutHeadline}</h1>
        </div>
        <div class="card-body p-4">
            <form {foreach $attributes as $attribute}
                    {$attribute@key}="{$attribute}"
                {/foreach}>
                {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
                <div class="mb-4">{$elements['oidc_logout_description'].content}</div>
                {if $oidcLogoutClientName !== ''}
                    <div class="alert alert-info">{$oidcLogoutClientName|escape}</div>
                {/if}
                <div class="form-alert mb-3" style="display: none;">&nbsp;</div>
                <div class="d-flex justify-content-center gap-3">
                    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_logout']}
                    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_cancel']}
                </div>
            </form>
        </div>
    </div>
</div>
