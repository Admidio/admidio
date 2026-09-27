<p class="lead">
    {$datePeriod}<br />
    {$l10n->get('SYS_PHOTOS_BY_VAR', array($photographer))}
</p>

<div class="admidio-img-presenter">
    {if $nextUrl !== ''}<a href="{$nextUrl}">{/if}
        <img src="{$photoUrl}" alt="{$l10n->get('SYS_PHOTO')}" />
    {if $nextUrl !== ''}</a>{/if}
</div>

<div class="btn-group admidio-margin-bottom">
    {if $previousUrl !== ''}
        <a class="btn btn-secondary" href="{$previousUrl}">
            <i class="bi bi-arrow-left-circle-fill"></i>{$l10n->get('SYS_PREVIOUS_PHOTO')}
        </a>
    {/if}
    {if $nextUrl !== ''}
        <a class="btn btn-primary" href="{$nextUrl}">
            <i class="bi bi-arrow-right-circle-fill"></i>{$l10n->get('SYS_NEXT_PHOTO')}
        </a>
    {/if}
</div>
