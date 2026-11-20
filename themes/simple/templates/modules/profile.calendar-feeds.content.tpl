<script type="text/javascript">
    function initCalendarFeedCopyButtons() {
        $("#adm_modal .copy-container").each(function () {
            var $element = $(this);
            if ($element.data("calendar-feed-copy-initialized")) {
                return;
            }
            $element.data("calendar-feed-copy-initialized", true);

            var $wrapper = $("<div>").css({
                "display": "flex",
                "align-items": "center",
                "width": "100%"
            });
            $element.css({
                "flex": "1",
                "width": "100%"
            });

            var $copyButton = $("<div>")
                .addClass("copy-btn")
                .html('<i class="bi bi-copy"></i>')
                .attr("title", "{$l10n->get('SYS_COPY_CLIPBOARD')}")
                .css({
                    "cursor": "pointer",
                    "margin-left": "4px",
                    "padding": "0px"
                });

            $element.wrap($wrapper);
            $element.after($copyButton);

            $copyButton.on("click", function () {
                copyToClipboard($element.val()).then(function (copied) {
                    if (!copied) {
                        return;
                    }
                    $copyButton.html('<i class="bi bi-clipboard-check"></i>');
                    setTimeout(function () {
                        $copyButton.html('<i class="bi bi-copy"></i>');
                    }, 1500);
                });
            });
        });
    }

    function calendarFeedSubmit(event) {
        event.preventDefault();
        var form = $(this);
        var submitButton = form.find("button[type=submit]");
        var icon = submitButton.find("i");
        var iconClass = icon.attr("class");

        submitButton.prop("disabled", true);
        icon.attr("class", "spinner-border spinner-border-sm");

        $.ajax({
            url: form.attr("action"),
            method: "POST",
            data: new FormData(form[0]),
            processData: false,
            contentType: false
        }).done(function(data) {
            var returnData = null;
            try {
                returnData = typeof data === "object" ? data : JSON.parse(data);
            } catch (e) {
                returnData = null;
            }

            if (returnData && returnData.status === "error") {
                submitButton.prop("disabled", false);
                icon.attr("class", iconClass);
                var alertBox = form.find(".form-alert");
                if (alertBox.length === 0) {
                    alertBox = $('<div class="alert alert-danger form-alert"></div>');
                    form.prepend(alertBox);
                }
                alertBox.attr("class", "alert alert-danger form-alert");
                alertBox.html('<i class="bi bi-exclamation-circle-fill"></i> ' + (returnData.message || ""));
                alertBox.show();
                return;
            }

            var modalBody = $("#adm_modal .modal-body");
            if (modalBody.length > 0 && typeof data === "string") {
                modalBody.html(data);
            } else {
                window.location.reload();
            }
        }).fail(function(jqXHR) {
            submitButton.prop("disabled", false);
            icon.attr("class", iconClass);
            var alertBox = form.find(".form-alert");
            if (alertBox.length === 0) {
                alertBox = $('<div class="alert alert-danger form-alert"></div>');
                form.prepend(alertBox);
            }
            alertBox.html('<i class="bi bi-exclamation-circle-fill"></i> ' + getRequestFailedMessage(jqXHR));
            alertBox.show();
        });
    }

    $(document)
        .off("submit.calendarfeed", "#adm_calendar_feed_create_form, .adm-calendar-feed-revoke-form")
        .on("submit.calendarfeed", "#adm_calendar_feed_create_form, .adm-calendar-feed-revoke-form", calendarFeedSubmit);

    initCalendarFeedCopyButtons();
</script>

    {if $message}
        <div class="alert alert-success"><i class="bi bi-check-lg me-1"></i>{$message}</div>
    {/if}

    {if $newFeed}
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="bi bi-key-fill me-1"></i>{$l10n->get('SYS_CALENDAR_FEED_CREATED')}</h5>
            <p>{$l10n->get('SYS_CALENDAR_FEED_TOKEN_ONCE')}</p>

            <label class="form-label" for="adm_calendar_feed_new_token">{$l10n->get('SYS_CALENDAR_FEED_TOKEN')}</label>
            <input type="text" class="form-control copy-container mb-3" id="adm_calendar_feed_new_token" value="{$newFeed.token|escape:'html'}" readonly="readonly" />

            <label class="form-label" for="adm_calendar_feed_new_url">{$l10n->get('SYS_CALENDAR_FEED_URL')}</label>
            <input type="text" class="form-control copy-container mb-3" id="adm_calendar_feed_new_url" value="{$newFeed.url|escape:'html'}" readonly="readonly" />

            <label class="form-label" for="adm_calendar_feed_new_username">{$l10n->get('SYS_CALENDAR_FEED_USERNAME')}</label>
            <input type="text" class="form-control copy-container mb-3" id="adm_calendar_feed_new_username" value="{$loginName|escape:'html'}" readonly="readonly" />
        </div>
    {/if}

    <h4>{$l10n->get('SYS_CALENDAR_FEED_ADD')}</h4>
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['cfd_name']}
        {include 'sys-template-parts/form.select.tpl' data=$elements['cfd_cat_uuid']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['cfd_expires']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_create_feed']}
    </form>

    <hr />

    <h4>{$l10n->get('SYS_CALENDAR_FEED_EXISTING')}</h4>
    {if $feeds|@count == 0}
        <p class="text-muted">{$l10n->get('SYS_CALENDAR_FEED_NO_FEEDS')}</p>
    {else}
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>{$l10n->get('SYS_CALENDAR_FEED_NAME')}</th>
                        <th>{$l10n->get('SYS_CALENDAR')}</th>
                        <th>{$l10n->get('SYS_CREATED')}</th>
                        <th>{$l10n->get('SYS_CALENDAR_FEED_LAST_USED')}</th>
                        <th>{$l10n->get('SYS_CALENDAR_FEED_EXPIRES')}</th>
                        <th>{$l10n->get('SYS_CALENDAR_FEED_STATUS')}</th>
                        <th>{$l10n->get('SYS_CALENDAR_FEED_URL')} / {$l10n->get('SYS_CALENDAR_FEED_USERNAME')}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $feeds as $feed}
                        <tr>
                            <td>{$feed.cfd_name|escape:'html'}</td>
                            <td>
                                {if $feed.calendar_missing}
                                    <span class="text-danger">{$l10n->get('SYS_CALENDAR_FEED_CALENDAR_MISSING')}</span>
                                {elseif $feed.cat_name}
                                    {$feed.cat_name|escape:'html'}
                                {else}
                                    {$l10n->get('SYS_ALL_CALENDARS')}
                                {/if}
                            </td>
                            <td>{$feed.create_display}</td>
                            <td>
                                {if $feed.last_used_display}
                                    {$feed.last_used_display}
                                {else}
                                    {$l10n->get('SYS_CALENDAR_FEED_NEVER')}
                                {/if}
                            </td>
                            <td>
                                {if $feed.expires_display}
                                    {$feed.expires_display}
                                {else}
                                    {$l10n->get('SYS_CALENDAR_FEED_NEVER')}
                                {/if}
                            </td>
                            <td>
                                {if !$feed.cfd_active}
                                    <span class="badge bg-secondary">{$l10n->get('SYS_CALENDAR_FEED_REVOKED_STATUS')}</span>
                                {elseif $feed.calendar_missing}
                                    <span class="badge bg-warning text-dark">{$l10n->get('SYS_CALENDAR_FEED_CALENDAR_MISSING')}</span>
                                {elseif $feed.is_expired}
                                    <span class="badge bg-warning text-dark">{$l10n->get('SYS_CALENDAR_FEED_EXPIRED_STATUS')}</span>
                                {else}
                                    <span class="badge bg-success">{$l10n->get('SYS_CALENDAR_FEED_ACTIVE')}</span>
                                {/if}
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm copy-container mb-1" id="adm_calendar_feed_url_{$feed.cfd_id}" value="{$feed.url|escape:'html'}" readonly="readonly" />
                                <code>{$loginName|escape:'html'}</code>
                                {if $feed.cfd_active}
                                    <form method="post" action="{$urlRevoke}" class="mt-1 adm-calendar-feed-revoke-form">
                                        <input type="hidden" name="adm_csrf_token" value="{$csrfToken}" />
                                        <input type="hidden" name="feed_id" value="{$feed.cfd_id}" />
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{$l10n->get('SYS_CALENDAR_FEED_REVOKE_CONFIRM')|escape:'javascript'}');">
                                            <i class="bi bi-trash"></i> {$l10n->get('SYS_CALENDAR_FEED_REVOKE')}
                                        </button>
                                    </form>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {/if}
