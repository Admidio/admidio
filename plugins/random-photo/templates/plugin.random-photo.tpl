<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('SYS_PHOTOS')}</h3>

    {if strlen($message) == 0}
        <a href="{$urlAdmidio}/modules/photos/photos.php?photo_uuid={$photoUUID}&photo_nr={$photoNr}"><img
            class="rounded d-block w-100" alt="{$photoTitle}" title="{$photoTitle}"
            src="{$urlAdmidio}/modules/photos/photo_show.php?photo_uuid={$photoUUID}&photo_nr={$photoNr}&thumb=1&album_id={$photoAlbumId}&album_begin={$photoAlbumBegin}" loading="lazy" decoding="async" fetchpriority="low" /></a>
        {if $photoShowLink}
            <a href="{$urlAdmidio}/modules/photos/photos.php?photo_uuid={$photoUUID}">{$photoTitle}</a>
        {/if}
    {else}
        {$message}
    {/if}
</div>
