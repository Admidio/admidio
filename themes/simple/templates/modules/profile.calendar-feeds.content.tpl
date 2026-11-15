<script type="text/javascript">
    function copyCalendarFeedValue(elementId) {
        var input = document.getElementById(elementId);
        if (!input) {
            return;
        }
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value);
        } else {
            document.execCommand("copy");
        }
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
</script>

    {if $message}
        <div class="alert alert-success"><i class="bi bi-check-lg me-1"></i>{$message}</div>
    {/if}

    {if $newFeed}
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="bi bi-key-fill me-1"></i>{$l10n->get('SYS_CALENDAR_FEED_CREATED')}</h5>
            <p>{$l10n->get('SYS_CALENDAR_FEED_TOKEN_ONCE')}</p>

            <label class="form-label" for="adm_calendar_feed_new_token">{$l10n->get('SYS_CALENDAR_FEED_TOKEN')}</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="adm_calendar_feed_new_token" value="{$newFeed.token|escape:'html'}" readonly="readonly" />
                <button class="btn btn-secondary" type="button" onclick="copyCalendarFeedValue('adm_calendar_feed_new_token')">
                    <i class="bi bi-clipboard"></i> {$l10n->get('SYS_COPY')}
                </button>
            </div>

            <label class="form-label" for="adm_calendar_feed_new_url">{$l10n->get('SYS_CALENDAR_FEED_URL')}</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="adm_calendar_feed_new_url" value="{$newFeed.url|escape:'html'}" readonly="readonly" />
                <button class="btn btn-secondary" type="button" onclick="copyCalendarFeedValue('adm_calendar_feed_new_url')">
                    <i class="bi bi-clipboard"></i> {$l10n->get('SYS_COPY')}
                </button>
            </div>

            <label class="form-label" for="adm_calendar_feed_new_username">{$l10n->get('SYS_CALENDAR_FEED_USERNAME')}</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="adm_calendar_feed_new_username" value="{$loginName|escape:'html'}" readonly="readonly" />
                <button class="btn btn-secondary" type="button" onclick="copyCalendarFeedValue('adm_calendar_feed_new_username')">
                    <i class="bi bi-clipboard"></i> {$l10n->get('SYS_COPY')}
                </button>
            </div>
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
                        <th>{$l10n->get('SYS_CALENDAR_FEED_CREATED')}</th>
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
                                {if $feed.cat_name}
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
                                {elseif $feed.is_expired}
                                    <span class="badge bg-warning text-dark">{$l10n->get('SYS_CALENDAR_FEED_EXPIRED_STATUS')}</span>
                                {else}
                                    <span class="badge bg-success">{$l10n->get('SYS_CALENDAR_FEED_ACTIVE')}</span>
                                {/if}
                            </td>
                            <td>
                                <div class="input-group input-group-sm mb-1">
                                    <input type="text" class="form-control" id="adm_calendar_feed_url_{$feed.cfd_id}" value="{$feed.url|escape:'html'}" readonly="readonly" />
                                    <button class="btn btn-secondary" type="button" onclick="copyCalendarFeedValue('adm_calendar_feed_url_{$feed.cfd_id}')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
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
