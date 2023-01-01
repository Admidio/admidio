<input
    {foreach $data.attributes as $itemvar}
    {$itemvar@key}="{$itemvar}"
    {/foreach}
    >