{if $albumInfo !== null}
    <div class="admidio-album-info rounded p-3 mb-4">
        <div class="row g-3">
            <div class="col-sm-6">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-range text-body-secondary" aria-hidden="true"></i>
                    <span><span class="visually-hidden">{$l10n->get('SYS_PERIOD')}: </span>{$albumInfo.datePeriod}</span>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-images text-body-secondary" aria-hidden="true"></i>
                        <span><span class="visually-hidden">{$l10n->get('SYS_PHOTOS')}: </span>{$albumInfo.photoCount} {$l10n->get('SYS_PHOTOS_BY_VAR', array($albumInfo.photographer))}</span>
                    </span>
                    {if $albumInfo.locked}
                        <span class="badge rounded-pill text-bg-danger ms-auto" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ALBUM_NOT_APPROVED')}">
                            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{$l10n->get('SYS_LOCKED')}
                        </span>
                    {/if}
                </div>
            </div>
        </div>
        {if $albumInfo.description !== ''}
            <div class="d-flex align-items-start gap-2 mt-3 pt-3 border-top">
                <i class="bi bi-card-text text-body-secondary" aria-hidden="true"></i>
                <div><span class="visually-hidden">{$l10n->get('SYS_DESCRIPTION')}: </span>{$albumInfo.description}</div>
            </div>
        {/if}
    </div>
{/if}

{if count($photos) > 0}
    <div class="row">
        {foreach $photos as $photo}
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-sm-6 admidio-photos-thumbnail" id="div_image_{$photo.number}">
                <a {if $showMode === 1}data-lightbox="admidio-gallery" data-title="{$headline}"{/if} href="{$photo.showUrl}">
                    <img class="rounded" id="img_{$photo.number}" src="{$photo.imageUrl}" alt="{$photo.number}" />
                </a>
                {if $showPhotoActions}
                    <div id="image_preferences_{$photo.number}" class="text-center">
                        {if $ecardEnabled}
                            <a class="admidio-icon-link" href="{$photo.ecardUrl}">
                                <i class="bi bi-envelope" data-bs-toggle="tooltip" title="{$l10n->get('SYS_SEND_PHOTO_AS_ECARD')}"></i>
                            </a>
                        {/if}
                        {if $downloadEnabled}
                            <a class="admidio-icon-link" href="{$photo.downloadUrl}">
                                <i class="bi bi-download" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DOWNLOAD_PHOTO')}"></i>
                            </a>
                        {/if}
                        {if $isPhotoAdministrator}
                            <a class="admidio-icon-link admidio-image-rotate" href="javascript:void(0)" data-image="{$photo.number}" data-direction="right">
                                <i class="bi bi-arrow-clockwise" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ROTATE_PHOTO_RIGHT')}"></i>
                            </a>
                            <a class="admidio-icon-link admidio-image-rotate" href="javascript:void(0)" data-image="{$photo.number}" data-direction="left">
                                <i class="bi bi-arrow-counterclockwise" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ROTATE_PHOTO_LEFT')}"></i>
                            </a>
                            <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                               data-message="{$l10n->get('SYS_WANT_DELETE_PHOTO')}"
                               data-href="callUrlHideElement('div_image_{$photo.number}', '{$photo.deleteUrl}', '{$csrfToken}')">
                                <i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE')}"></i>
                            </a>
                        {/if}
                    </div>
                {/if}
            </div>
        {/foreach}
        {foreach $hiddenPhotos as $hiddenPhotoUrl}
            <a class="d-none" data-lightbox="admidio-gallery" data-title="{$headline}" href="{$hiddenPhotoUrl}">&nbsp;</a>
        {/foreach}
    </div>

    {if $albumInfo !== null}
        {include file="sys-template-parts/system.info-create-edit.tpl"
            userCreatedName=$albumInfo.createdName
            userCreatedTimestamp=$albumInfo.createdTimestamp
            lastUserEditedName=$albumInfo.editedName
            lastUserEditedTimestamp=$albumInfo.editedTimestamp}
    {/if}
    {$photoPagination}
{/if}

{if count($albums) > 0}
    {if count($photos) > 0}<hr />{/if}
    <div class="row admidio-margin-bottom">
        {foreach $albums as $album}
            <div class="admidio-album col-sm-6 col-lg-4 col-xl-3" id="panel_pho_{$album.uuid}">
                <div class="card admidio-card">
                    <a href="{$album.url}"><img class="card-img-top" src="{$album.imageUrl}" alt="{$l10n->get('SYS_PHOTOS')}" /></a>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="{$album.url}">{$album.name}</a>
                            {if $album.locked}
                                <i class="bi bi-lock-fill text-danger ms-1" role="img" aria-label="{$l10n->get('SYS_ALBUM_NOT_APPROVED')}" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ALBUM_NOT_APPROVED')}"></i>
                            {/if}
                        </h5>
                        <div class="small text-body-secondary">{$album.date}</div>
                        {if $album.description !== ''}<div class="card-text text-break mt-3">{$album.description}</div>{/if}
                        {if $album.folderMissing && $album.editable}
                            <div class="alert alert-warning alert-small mt-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_ALBUM_FOLDER_NOT_FOUND')}
                            </div>
                        {/if}
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <small class="text-body-secondary">{$album.photoCount} {$l10n->get('SYS_PHOTOS')}</small>
                            {if $album.editable}
                                <div>
                                    {if $album.locked}
                                        <a class="admidio-icon-link admidio-album-lock" href="javascript:void(0)" data-id="{$album.uuid}" data-mode="unlock" aria-label="{$l10n->get('SYS_UNLOCK_ALBUM')}"><i class="bi bi-unlock" data-bs-toggle="tooltip" title="{$l10n->get('SYS_UNLOCK_ALBUM')}"></i></a>
                                    {else}
                                        <a class="admidio-icon-link admidio-album-lock" href="javascript:void(0)" data-id="{$album.uuid}" data-mode="lock" aria-label="{$l10n->get('SYS_LOCK_ALBUM')}"><i class="bi bi-lock" data-bs-toggle="tooltip" title="{$l10n->get('SYS_LOCK_ALBUM')}"></i></a>
                                    {/if}
                                    <a class="admidio-icon-link" href="{$album.editUrl}" aria-label="{$l10n->get('SYS_EDIT_ALBUM')}"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT_ALBUM')}"></i></a>
                                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                       data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($album.name))}"
                                       data-href="callUrlHideElement('panel_pho_{$album.uuid}', '{$album.deleteUrl}', '{$csrfToken}')"
                                       aria-label="{$l10n->get('SYS_DELETE_ALBUM')}"><i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE_ALBUM')}"></i></a>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        {/foreach}
    </div>
{elseif count($photos) === 0 && $albumsCount === 0}
    <p>{$l10n->get('SYS_ALBUM_CONTAINS_NO_PHOTOS')}</p>
{/if}

{$albumPagination}
