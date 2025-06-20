{$fieldGroupOpened = false}
{foreach $category as $profileField}
    {if $fieldGroupOpened eq false}
        <div class="admidio-form-group row mb-3">
    {/if}
    <div class="col-sm-2">
        {if strlen($profileField.icon) > 0}
            {$profileField.icon}
        {/if}
        {$profileField.label}
    </div>
    <div class="col-sm-4">
        <strong>{$profileField.value}</strong>
    </div>
    {if $fieldGroupOpened eq false}
        {$fieldGroupOpened = true}
    {else}
        {$fieldGroupOpened = false}
        </div>
    {/if}
{/foreach}
{if $fieldGroupOpened}
        </div>
{/if}