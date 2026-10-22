<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('SYS_PHOTOS')}</h3>

    {if strlen($message) == 0}
        <a href="{$urlAdmidio}/modules/photos.php?photo_uuid={$photoUUID}&photo_nr={$photoNr}"><img
            class="rounded d-block w-100" alt="{$photoTitle}" title="{$photoTitle}"
            src="{$urlAdmidio}/modules/photos.php?mode=photo_show&amp;photo_uuid={$photoUUID}&amp;photo_nr={$photoNr}&amp;max_width={$photoMaxWidth}&amp;max_height={$photoMaxHeight}" /></a>
        {if $photoShowLink}
            <a href="{$urlAdmidio}/modules/photos.php?photo_uuid={$photoUUID}">{$photoTitle}</a>
        {/if}
    {else}
        {$message}
    {/if}
</div>
