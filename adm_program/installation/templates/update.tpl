
<h3>{$l10n->get('INS_WELCOME_TO_UPDATE')}</h3>

<p>{$l10n->get(
                'INS_WELCOME_TEXT_UPDATE',
                array(ADMIDIO_VERSION_TEXT,
                    $installedDbVersion,
                    '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update" target="_blank">',
                    '</a>',
                    '<a href="https://www.admidio.org/forum" target="_blank">',
                    '</a>'
                )
            )}</p>

<p class="font-weight-bolder">{$l10n->get('SYS_CURRENT_DATABASE_VERSION')}: {$installedDbVersion}</p>
<p class="font-weight-bolder">{$l10n->get('SYS_DATABASE_VERSION_AFTER_UPDATE')}: {ADMIDIO_VERSION_TEXT}</p>

{* if this is a beta version then show a warning message *}
{if ADMIDIO_VERSION_BETA > 0}
    <div class="alert alert-warning alert-small" role="alert">
        <i class="fas fa-exclamation-triangle"></i>
        {$l10n->get('INS_WARNING_BETA_VERSION')}
    </div>
{/if}

{$content}
