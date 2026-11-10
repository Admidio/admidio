{* The notice of a new release of Admidio for administrators. A browser in which the notice of this
   version was closed does not show it again; the notice of the next version it shows. *}
<div id="adm_update_notice" class="alert alert-info alert-dismissible d-none" role="status" data-version="{$availableUpdate}">
    <i class="bi bi-arrow-up-circle-fill"></i>
    {$l10n->get('SYS_UPDATE_NOTICE', array($availableUpdate))}
    <a class="alert-link" href="{$availableUpdateUrl}">{$l10n->get('SYS_CORE_UPDATE_START')}</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
</div>
<script>
    (function () {
        const notice = document.getElementById("adm_update_notice");
        const key = "{$cookiePrefix}_update_notice_closed";
        let closed = null;
        try {
            closed = window.localStorage.getItem(key);
        } catch (error) {
            // without localStorage the notice is shown on every page
        }
        if (closed !== notice.dataset.version) {
            notice.classList.remove("d-none");
        }
        notice.addEventListener("closed.bs.alert", function () {
            try {
                window.localStorage.setItem(key, notice.dataset.version);
            } catch (error) {
                // nothing to remember it in
            }
        });
    })();
</script>
