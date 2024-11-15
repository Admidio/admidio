{if $data.icon}
    <i class="bi {$data.icon}" {if isset($data.label)}data-bs-toggle="tooltip" title="{$data.label}"{/if}></i>
{/if}
