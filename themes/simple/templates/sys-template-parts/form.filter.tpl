<nav class="navbar navbar-expand-lg navbar-filter rounded">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">{$l10n->get('SYS_FILTER')}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#{$navbarID}" aria-controls="{$navbarID}" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="{$navbarID}" class="collapse navbar-collapse">
            <form {foreach $attributes as $attribute}
                    {$attribute@key}="{$attribute}"
                {/foreach}>

                {foreach $elements as $element}
                    {if $element.type == 'text' || $element.type == 'date'}
                        {include 'sys-template-parts/form.input.tpl' data=$element}
                    {/if}
                    {if $element.type == 'button-group.radio'}
                        {include 'sys-template-parts/form.button-group.radio.tpl' data=$element}
                    {/if}
                    {if $element.type == 'checkbox'}
                        {include 'sys-template-parts/form.checkbox.tpl' data=$element}
                    {/if}
                    {if $element.type == 'select'}
                        {include 'sys-template-parts/form.select.tpl' data=$element}
                    {/if}
                    {if $element.type == 'submit' || $element.type == 'button'}
                        {include 'sys-template-parts/form.button.tpl' data=$element}
                    {/if}
                {/foreach}
            </form>
        </div>
    </div>
</nav>
