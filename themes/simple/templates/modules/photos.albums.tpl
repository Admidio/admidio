{if $albumInfo !== null}
    {if $albumInfo.locked}
        <div class="alert alert-warning alert-small" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_ALBUM_NOT_APPROVED')}
        </div>
    {/if}
    <div class="lead">
        <p class="fw-bold">{$albumInfo.datePeriod}</p>
        <p>{$albumInfo.photoCount} {$l10n->get('SYS_PHOTOS_BY_VAR', array($albumInfo.photographer))}</p>
        {if $albumInfo.description !== ''}<div>{$albumInfo.description}</div>{/if}
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
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="{$album.url}">{$album.name}</a>
                            {if $album.editable}
                                <div class="dropdown float-end">
                                    <a class="admidio-icon-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="{$album.editUrl}">
                                            <i class="bi bi-pencil-square"></i> {$l10n->get('SYS_EDIT_ALBUM')}
                                        </a></li>
                                        {if !$album.locked}
                                            <li><a class="dropdown-item admidio-album-lock" href="javascript:void(0)" data-id="{$album.uuid}" data-mode="lock">
                                                <i class="bi bi-lock"></i> {$l10n->get('SYS_LOCK_ALBUM')}
                                            </a></li>
                                        {/if}
                                        <li><a class="dropdown-item admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                               data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($album.name))}"
                                               data-href="callUrlHideElement('panel_pho_{$album.uuid}', '{$album.deleteUrl}', '{$csrfToken}')">
                                            <i class="bi bi-trash"></i> {$l10n->get('SYS_DELETE_ALBUM')}
                                        </a></li>
                                    </ul>
                                </div>
                            {/if}
                        </h5>
                        <p class="card-text">{$album.date}</p>
                        {if $album.description !== ''}<div class="card-text">{$album.description}</div>{/if}
                        <p class="card-text">{$album.photoCount} {$l10n->get('SYS_PHOTOS_BY_VAR', array($album.photographer))}</p>
                        {if $album.folderMissing && $album.editable}
                            <div class="alert alert-warning alert-small" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_ALBUM_FOLDER_NOT_FOUND')}
                            </div>
                        {/if}
                        {if $album.locked}
                            <div class="alert alert-warning alert-small" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>{$l10n->get('SYS_ALBUM_NOT_APPROVED')}
                            </div>
                            {if $album.editable}
                                <button class="btn btn-primary admidio-album-lock" data-id="{$album.uuid}" data-mode="unlock">
                                    {$l10n->get('SYS_UNLOCK_ALBUM')}
                                </button>
                            {/if}
                        {/if}
                    </div>
                </div>
            </div>
        {/foreach}
    </div>
{elseif count($photos) === 0 && $albumsCount === 0}
    <p>{$l10n->get('SYS_ALBUM_CONTAINS_NO_PHOTOS')}</p>
{/if}

{$albumPagination}
