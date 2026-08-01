/**
 ***********************************************************************************************
 * JavaScript functions for profile module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

function ProfileJS(gRootPath) {
    this.url                     = gRootPath + "/modules/profile/profile_function.php";
    this.labelLoading            = "Loading...";
    this.labelLoadingMemberships = "Loading role memberships...";
    this.formerRoleCount         = 0;
    this.futureRoleCount         = 0;
    this.userUuid                = "";
    this.errorID                 = 0;
    this._reloadInFlight         = { current: false, former: false, future: false };
    this._reloadPending          = { current: false, former: false, future: false };
    this._reloadTimer            = null;
    this._additionalRolesLoaded  = false;
    this._membershipSectionMap   = {
        current: {
            tab: "#adm_profile_role_memberships_current_pane_content",
            accordion: "#adm_profile_role_memberships_current_accordion_content"
        },
        former: {
            tab: "#adm_profile_role_memberships_former_pane_content",
            accordion: "#adm_profile_role_memberships_former_accordion_content"
        },
        future: {
            tab: "#adm_profile_role_memberships_future_pane_content",
            accordion: "#adm_profile_role_memberships_future_accordion_content"
        }
    };

    this._sectionExists = function (selector) {
        return $(selector).length > 0;
    };

    this._membershipDraftStorageKey = function () {
        return "adm_profile_membership_period_drafts_" + this.userUuid;
    };

    this.readMembershipDrafts = function () {
        try {
            var rawDrafts = sessionStorage.getItem(this._membershipDraftStorageKey());

            if (!rawDrafts) {
                return {};
            }

            var parsedDrafts = JSON.parse(rawDrafts);
            if (parsedDrafts && typeof parsedDrafts === "object") {
                return parsedDrafts;
            }
        } catch (ignoreDraftReadError) {
            // Ignore broken browser storage and continue without drafts.
        }

        return {};
    };

    this.writeMembershipDrafts = function (drafts) {
        try {
            sessionStorage.setItem(this._membershipDraftStorageKey(), JSON.stringify(drafts || {}));
        } catch (ignoreDraftWriteError) {
            // Ignore storage quota or unavailable storage.
        }
    };

    this.storeMembershipDraft = function (formElement, memberUuid) {
        if (!memberUuid) {
            return;
        }

        var startDateValue = formElement.find("[name='adm_membership_start_date']").val() || "";
        var endDateValue = formElement.find("[name='adm_membership_end_date']").val() || "";
        var drafts = this.readMembershipDrafts();

        drafts[memberUuid] = {
            startDate: startDateValue,
            endDate: endDateValue,
            ts: Date.now()
        };

        this.writeMembershipDrafts(drafts);
    };

    this.storeAllMembershipDrafts = function (membershipForms, excludeMemberUuid) {
        var self = this;

        membershipForms.each(function () {
            var draftFormElement = $(this);
            var draftSubmitButton = draftFormElement.find(".button-membership-period-form").first();
            var draftMemberUuid = draftSubmitButton.attr("data-admidio") || draftFormElement.attr("data-admidio") || "";

            if (!draftMemberUuid || draftMemberUuid === excludeMemberUuid) {
                return;
            }

            self.storeMembershipDraft(draftFormElement, draftMemberUuid);
        });
    };

    this.clearMembershipDraft = function (memberUuid) {
        if (!memberUuid) {
            return;
        }

        var drafts = this.readMembershipDrafts();
        if (drafts[memberUuid]) {
            delete drafts[memberUuid];
            this.writeMembershipDrafts(drafts);
        }
    };

    this.applyMembershipDrafts = function (membershipForms) {
        var drafts = this.readMembershipDrafts();
        var hasChanges = false;

        if (!drafts || typeof drafts !== "object") {
            return;
        }

        membershipForms.each(function () {
            var formElement = $(this);
            var submitButton = formElement.find(".button-membership-period-form").first();
            var memberUuid = submitButton.attr("data-admidio") || formElement.attr("data-admidio") || "";

            if (!memberUuid || !drafts[memberUuid]) {
                return;
            }

            var draft = drafts[memberUuid];
            formElement.find("[name='adm_membership_start_date']").val(draft.startDate || "");
            formElement.find("[name='adm_membership_end_date']").val(draft.endDate || "");
            delete drafts[memberUuid];
            hasChanges = true;
        });

        if (hasChanges) {
            this.writeMembershipDrafts(drafts);
        }
    };

    this.detectMembershipSection = function (formElement) {
        var container = formElement.closest(
            "#adm_profile_role_memberships_current_pane_content, "
            + "#adm_profile_role_memberships_current_accordion_content, "
            + "#adm_profile_role_memberships_former_pane_content, "
            + "#adm_profile_role_memberships_former_accordion_content, "
            + "#adm_profile_role_memberships_future_pane_content, "
            + "#adm_profile_role_memberships_future_accordion_content"
        );
        var containerId = container.attr("id") || "";

        if (containerId.indexOf("current") !== -1) {
            return "current";
        }
        if (containerId.indexOf("former") !== -1) {
            return "former";
        }
        if (containerId.indexOf("future") !== -1) {
            return "future";
        }

        return "current";
    };

    this.classifyMembershipSectionByDates = function (startDateValue, endDateValue) {
        var isoDateRegex = /^\d{4}-\d{2}-\d{2}$/;
        if (!isoDateRegex.test(startDateValue || "")) {
            return null;
        }

        var today = new Date().toISOString().slice(0, 10);
        var effectiveEndDate = isoDateRegex.test(endDateValue || "") ? endDateValue : "9999-12-31";

        if (effectiveEndDate < today) {
            return "former";
        }
        if (startDateValue > today) {
            return "future";
        }

        return "current";
    };

    this._parseIsoDate = function (dateString) {
        var match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(dateString || "");
        if (!match) {
            return null;
        }

        var year = parseInt(match[1], 10);
        var month = parseInt(match[2], 10) - 1;
        var day = parseInt(match[3], 10);
        return new Date(year, month, day, 0, 0, 0, 0);
    };

    this._padNumber = function (value, length) {
        var text = String(value);
        while (text.length < length) {
            text = "0" + text;
        }
        return text;
    };

    this._formatIsoDateForDisplay = function (dateString) {
        var parsedDate = this._parseIsoDate(dateString);
        if (!parsedDate) {
            return dateString;
        }

        var formatPattern = this.systemDateFormat || "Y-m-d";
        var formatResult = "";
        var escapeNext = false;

        for (var i = 0; i < formatPattern.length; i++) {
            var token = formatPattern.charAt(i);

            if (escapeNext) {
                formatResult += token;
                escapeNext = false;
                continue;
            }

            if (token === "\\") {
                escapeNext = true;
                continue;
            }

            if (token === "Y") {
                formatResult += String(parsedDate.getFullYear());
            } else if (token === "y") {
                formatResult += this._padNumber(parsedDate.getFullYear() % 100, 2);
            } else if (token === "m") {
                formatResult += this._padNumber(parsedDate.getMonth() + 1, 2);
            } else if (token === "n") {
                formatResult += String(parsedDate.getMonth() + 1);
            } else if (token === "d") {
                formatResult += this._padNumber(parsedDate.getDate(), 2);
            } else if (token === "j") {
                formatResult += String(parsedDate.getDate());
            } else {
                formatResult += token;
            }
        }

        return formatResult;
    };

    this._calculateMembershipDurationParts = function (startIsoDate, endIsoDate) {
        var start = this._parseIsoDate(startIsoDate);
        if (!start) {
            return null;
        }

        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
        var effectiveEnd = null;

        if (!endIsoDate || endIsoDate === "9999-12-31") {
            effectiveEnd = new Date(today.getTime());
        } else {
            effectiveEnd = this._parseIsoDate(endIsoDate);
            if (!effectiveEnd) {
                effectiveEnd = new Date(today.getTime());
            } else if (effectiveEnd.getTime() > today.getTime()) {
                effectiveEnd = new Date(today.getTime());
            }
        }

        effectiveEnd.setDate(effectiveEnd.getDate() + 1);

        var from = start;
        var to = effectiveEnd;
        if (from.getTime() > to.getTime()) {
            var temp = from;
            from = to;
            to = temp;
        }

        var years = to.getFullYear() - from.getFullYear();
        var months = to.getMonth() - from.getMonth();
        var days = to.getDate() - from.getDate();

        if (days < 0) {
            months -= 1;
            var daysInPreviousMonth = new Date(to.getFullYear(), to.getMonth(), 0).getDate();
            days += daysInPreviousMonth;
        }

        if (months < 0) {
            years -= 1;
            months += 12;
        }

        return {
            years: years,
            months: months,
            days: days
        };
    };

    this._formatMembershipDuration = function (parts) {
        if (!parts) {
            return "";
        }

        if (!this.membershipDurationExact) {
            var roundedYears = parts.years;
            return roundedYears + " " + (roundedYears === 1 ? (this.labelYear || "Year") : (this.labelYears || "Years"));
        }

        var durationParts = [];

        if (parts.years > 0) {
            durationParts.push(parts.years + " " + (parts.years === 1 ? (this.labelYear || "Year") : (this.labelYears || "Years")));
        }

        if (parts.months > 0) {
            durationParts.push(parts.months + " " + (parts.months === 1 ? (this.labelMonth || "Month") : (this.labelMonths || "Months")));
        }

        if (parts.days > 0 || (parts.years === 0 && parts.months === 0)) {
            durationParts.push(parts.days + " " + (parts.days === 1 ? (this.labelDay || "Day") : (this.labelDays || "Days")));
        }

        return durationParts.join(", ");
    };

    this.updateMembershipRowPeriod = function (memberUuid, startDateValue, endDateValue) {
        if (!memberUuid) {
            return false;
        }

        var startDate = $.trim(startDateValue || "");
        var endDate = $.trim(endDateValue || "");
        if (startDate.length === 0) {
            return false;
        }

        var startDateDisplay = this._formatIsoDateForDisplay(startDate);
        var endDateDisplay = this._formatIsoDateForDisplay(endDate);
        var periodText = startDateDisplay;
        if (endDate.length > 0 && endDate !== "9999-12-31") {
            periodText = startDateDisplay + " " + (this.labelMembershipTo || "to") + " " + endDateDisplay;
        }

        var durationParts = this._calculateMembershipDurationParts(startDate, endDate);
        var durationText = this._formatMembershipDuration(durationParts);
        var updatedAny = false;
        var self = this;

        $("li#membership_" + memberUuid + " span.me-2").each(function () {
            var periodContainer = $(this);
            var durationBadge = periodContainer.children(".badge").first().detach();

            periodContainer.text(periodText + " ");
            if (durationText.length > 0) {
                if (durationBadge.length === 0) {
                    durationBadge = $("<span class='badge bg-info ms-1'></span>");
                    durationBadge.attr("data-bs-toggle", "tooltip");
                    durationBadge.attr("title", self.labelMembershipDuration || "Membership duration");
                }
                durationBadge.text(durationText);
            }

            if (durationBadge.length > 0 && durationText.length > 0) {
                periodContainer.append(durationBadge);
            }

            updatedAny = true;
        });

        return updatedAny;
    };

    this.setAdditionalSectionVisibility = function (sectionName) {
        if (sectionName !== "former" && sectionName !== "future") {
            return;
        }

        var sectionMap = this._membershipSectionMap && this._membershipSectionMap[sectionName];
        if (!sectionMap) {
            return;
        }

        var tabSelector = sectionMap.tab;
        var accordionSelector = sectionMap.accordion;
        var tabCount = this._countMembershipRows(tabSelector);
        var accordionCount = this._countMembershipRows(accordionSelector);
        var hasRows = Math.max(tabCount, accordionCount) > 0;

        if (this._sectionExists(tabSelector)) {
            $(tabSelector).css({ display: hasRows ? "block" : "none" });
        }
        if (this._sectionExists(accordionSelector)) {
            $(accordionSelector).css({ display: hasRows ? "block" : "none" });
        }
    };

    this._compareMembershipRows = function (leftRow, rightRow) {
        var leftCat = parseInt(leftRow.attr("data-sort-cat-seq") || "0", 10);
        var rightCat = parseInt(rightRow.attr("data-sort-cat-seq") || "0", 10);

        if (leftCat !== rightCat) {
            return leftCat - rightCat;
        }

        var leftRole = leftRow.attr("data-sort-role-name") || "";
        var rightRole = rightRow.attr("data-sort-role-name") || "";
        var roleCompare = leftRole.localeCompare(rightRole);
        if (roleCompare !== 0) {
            return roleCompare;
        }

        var leftBegin = leftRow.attr("data-sort-begin") || "";
        var rightBegin = rightRow.attr("data-sort-begin") || "";
        var beginCompare = leftBegin.localeCompare(rightBegin);
        if (beginCompare !== 0) {
            return beginCompare;
        }

        var leftUuid = leftRow.attr("data-sort-member-uuid") || "";
        var rightUuid = rightRow.attr("data-sort-member-uuid") || "";
        return leftUuid.localeCompare(rightUuid);
    };

    this._insertMembershipRowSorted = function (targetList, sourceRow) {
        var inserted = false;
        var self = this;

        targetList.children("li[id^='membership_']").each(function () {
            var existingRow = $(this);

            if (self._compareMembershipRows(sourceRow, existingRow) < 0) {
                existingRow.before(sourceRow);
                inserted = true;
                return false;
            }
        });

        if (!inserted) {
            targetList.append(sourceRow);
        }
    };

    this.moveMembershipRow = function (memberUuid, sourceSection, targetSection) {
        if (!memberUuid || sourceSection === targetSection) {
            return false;
        }

        var map = this._membershipSectionMap || {};
        var sourceMap = map[sourceSection] || null;
        var targetMap = map[targetSection] || null;
        var self = this;

        if (!sourceMap || !targetMap) {
            return false;
        }

        var movedAny = false;
        ["tab", "accordion"].forEach(function (layoutKey) {
            var sourceSelector = sourceMap[layoutKey];
            var targetSelector = targetMap[layoutKey];

            if (!sourceSelector || !targetSelector || !self._sectionExists(targetSelector)) {
                return;
            }

            var sourceRow = $(sourceSelector + " li#membership_" + memberUuid).first();
            if (sourceRow.length === 0) {
                return;
            }

            var targetCardBody = $(targetSelector + " .card-body").first();
            if (targetCardBody.length === 0) {
                return;
            }

            var targetList = targetCardBody.children("ul.list-group.admidio-list-roles-assign").first();
            if (targetList.length === 0) {
                targetList = $("<ul class='list-group admidio-list-roles-assign'></ul>");
                targetCardBody.empty().append(targetList);
            }

            sourceRow.detach();
            self._insertMembershipRowSorted(targetList, sourceRow);
            movedAny = true;

            formSubmitEvent(sourceSelector + " .card-body");
            formSubmitEvent(targetSelector + " .card-body");
        });

        if (movedAny) {
            this.setAdditionalSectionVisibility(sourceSection);
            this.setAdditionalSectionVisibility(targetSection);
            this.refreshMembershipUiState();
        }

        return movedAny;
    };

    this.reloadMembershipSectionsAfterSave = function (formElement, memberUuid) {
        var sourceSection = this.detectMembershipSection(formElement);
        var startDateValue = formElement.find("[name='adm_membership_start_date']").val() || "";
        var endDateValue = formElement.find("[name='adm_membership_end_date']").val() || "";
        var targetSection = this.classifyMembershipSectionByDates(startDateValue, endDateValue) || sourceSection;

        if (sourceSection === targetSection && this.updateMembershipRowPeriod(memberUuid, startDateValue, endDateValue)) {
            this.refreshMembershipUiState();
            return;
        }

        if (sourceSection !== targetSection && this.moveMembershipRow(memberUuid, sourceSection, targetSection)) {
            this.updateMembershipRowPeriod(memberUuid, startDateValue, endDateValue);
            return;
        }

        if (sourceSection === "former") {
            this.reloadFormerRoleMemberships();
        } else if (sourceSection === "future") {
            this.reloadFutureRoleMemberships();
        } else {
            this.reloadRoleMemberships();
        }

        if (targetSection !== sourceSection) {
            if (targetSection === "former") {
                this.reloadFormerRoleMemberships();
            } else if (targetSection === "future") {
                this.reloadFutureRoleMemberships();
            } else {
                this.reloadRoleMemberships();
            }
        }
    };

    this._loadingIndicatorHtml = function () {
        return '<div class="d-flex align-items-center text-muted py-2">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status">'
            + '<span class="visually-hidden">' + this.labelLoading + '</span>'
            + '</div>'
            + '<span>' + this.labelLoadingMemberships + '</span>'
            + '</div>';
    };

    this._setMembershipSectionLoading = function (sectionSelector, isLoading) {
        if (!this._sectionExists(sectionSelector)) {
            return;
        }

        var cardBody = $(sectionSelector + " .card-body");
        if (isLoading) {
            cardBody.html(this._loadingIndicatorHtml());
        }
    };

    this._countMembershipRows = function (sectionSelector) {
        if (!this._sectionExists(sectionSelector)) {
            return 0;
        }

        // Count rows independent of current display state, otherwise hidden sections
        // never become visible again after inline moves.
        return $(sectionSelector + " .card-body > ul > li[id^='membership_']").length;
    };

    this.refreshMembershipUiState = function () {
        var formerCount = Math.max(
            this._countMembershipRows(this._membershipSectionMap.former.tab),
            this._countMembershipRows(this._membershipSectionMap.former.accordion)
        );
        var futureCount = Math.max(
            this._countMembershipRows(this._membershipSectionMap.future.tab),
            this._countMembershipRows(this._membershipSectionMap.future.accordion)
        );
        var currentCount = Math.max(
            this._countMembershipRows(this._membershipSectionMap.current.tab),
            this._countMembershipRows(this._membershipSectionMap.current.accordion)
        );

        this.formerRoleCount = formerCount;
        this.futureRoleCount = futureCount;

        // Ensure legacy/dynamic badges are removed so membership headers keep their original look.
        $(".adm-membership-count-badge").remove();
    };

    this._reloadMembershipSection = function (mode, requestUrl, tabSelector, accordionSelector) {
        if (!this._sectionExists(tabSelector) && !this._sectionExists(accordionSelector)) {
            return;
        }

        if (this._reloadInFlight[mode]) {
            this._reloadPending[mode] = true;
            return;
        }

        var self = this;
        this._reloadInFlight[mode] = true;

        self._setMembershipSectionLoading(tabSelector, true);
        self._setMembershipSectionLoading(accordionSelector, true);

        $.get({
            url: requestUrl,
            data: { _ts: Date.now() },
            cache: false,
            dataType: "html"
        }).done(function (responseText) {
            if (self._sectionExists(tabSelector)) {
                $(tabSelector + " .card-body").html(responseText);
                formSubmitEvent(tabSelector + " .card-body");
            }

            if (self._sectionExists(accordionSelector)) {
                $(accordionSelector + " .card-body").html(responseText);
                formSubmitEvent(accordionSelector + " .card-body");
            }

            self.refreshMembershipUiState();
        }).fail(function () {
            var errorHtml = '<div class="text-danger py-2">Failed to load role memberships. Please retry.</div>';
            if (self._sectionExists(tabSelector)) {
                $(tabSelector + " .card-body").html(errorHtml);
            }
            if (self._sectionExists(accordionSelector)) {
                $(accordionSelector + " .card-body").html(errorHtml);
            }
        }).always(function () {
            self._reloadInFlight[mode] = false;

            if (self._reloadPending[mode]) {
                self._reloadPending[mode] = false;
                self._reloadMembershipSection(mode, requestUrl, tabSelector, accordionSelector);
            }
        });
    };

    this.reloadRoleMemberships = function () {
        this._reloadMembershipSection(
            "current",
            this.url + "?mode=reload_current_memberships&user_uuid=" + this.userUuid,
            "#adm_profile_role_memberships_current_pane_content",
            "#adm_profile_role_memberships_current_accordion_content"
        );
    };

    this.reloadFormerRoleMemberships = function () {
        this._reloadMembershipSection(
            "former",
            this.url + "?mode=reload_former_memberships&user_uuid=" + this.userUuid,
            "#adm_profile_role_memberships_former_pane_content",
            "#adm_profile_role_memberships_former_accordion_content"
        );
    };

    this.reloadFutureRoleMemberships = function () {
        this._reloadMembershipSection(
            "future",
            this.url + "?mode=reload_future_memberships&user_uuid=" + this.userUuid,
            "#adm_profile_role_memberships_future_pane_content",
            "#adm_profile_role_memberships_future_accordion_content"
        );
    };

    this.reloadAdditionalRoleMemberships = function () {
        this.reloadFormerRoleMemberships();
        this.reloadFutureRoleMemberships();
    };

    this.scheduleRoleMembershipReloads = function () {
        var self = this;

        if (this._reloadTimer !== null) {
            clearTimeout(this._reloadTimer);
        }

        this._reloadTimer = setTimeout(function () {
            self.reloadRoleMemberships();
            self.reloadAdditionalRoleMemberships();
            self._reloadTimer = null;
        }, 200);
    };

    this.initializeDeferredRoleMemberships = function () {
        var self = this;
        var loadAdditionalMembershipsOnce = function () {
            if (self._additionalRolesLoaded) {
                return;
            }

            self._additionalRolesLoaded = true;
            self.reloadAdditionalRoleMemberships();
        };

        $("#adm_profile_role_memberships_tab").on("shown.bs.tab", loadAdditionalMembershipsOnce);
        $("#adm_profile_role_memberships_accordion").on("shown.bs.collapse", loadAdditionalMembershipsOnce);

        if ($("#adm_profile_role_memberships_tab").hasClass("active")
            || $("#adm_profile_role_memberships_accordion").hasClass("show")) {
            loadAdditionalMembershipsOnce();
        }
    };

    this.toggleDetailsOn = function (memberUuid) {
        /* Tabs */
        // find the element in different containers (current, former, future)
        membershipPeriodElement = $(
            "#adm_profile_role_memberships_current_pane_content, " +
            "#adm_profile_role_memberships_former_pane_content, " +
            "#adm_profile_role_memberships_future_pane_content"
        ).find("#adm_membership_period_" + memberUuid).first();
        membershipPeriodElement.css({"visibility": "visible", "display": "block"});
        // find the parent element with href toggleDetailsOn('memberUuid') and change it to toggleDetailsOff('memberUuid')
        toggleElement = membershipPeriodElement.parent().find("a[href=\"javascript:profileJS.toggleDetailsOn('" + memberUuid + "')\"]");
        toggleElement.attr("href", "javascript:profileJS.toggleDetailsOff('" + memberUuid + "')");

        /* Accordions */
        membershipPeriodElement = $(
            "#adm_profile_role_memberships_current_accordion_content, " +
            "#adm_profile_role_memberships_former_accordion_content, " +
            "#adm_profile_role_memberships_future_accordion_content"
        ).find("#adm_membership_period_" + memberUuid).first();
        membershipPeriodElement.css({"visibility": "visible", "display": "block"});
        // find the parent element with href toggleDetailsOn('memberUuid') and change it to toggleDetailsOff('memberUuid')
        toggleElement = membershipPeriodElement.parent().find("a[href=\"javascript:profileJS.toggleDetailsOn('" + memberUuid + "')\"]");
        toggleElement.attr("href", "javascript:profileJS.toggleDetailsOff('" + memberUuid + "')");
    };

    this.toggleDetailsOff = function (memberUuid) {
        /* Tabs */
        // find the element in different containers (current, former, future)
        membershipPeriodElement = $(
            "#adm_profile_role_memberships_current_pane_content, " +
            "#adm_profile_role_memberships_former_pane_content, " +
            "#adm_profile_role_memberships_future_pane_content"
        ).find("#adm_membership_period_" + memberUuid).first();
        membershipPeriodElement.css({"visibility": "hidden", "display": "none"});
        // find the parent element with href toggleDetailsOff('memberUuid') and change it to toggleDetailsOn('memberUuid')
        toggleElement = membershipPeriodElement.parent().find("a[href=\"javascript:profileJS.toggleDetailsOff('" + memberUuid + "')\"]");
        toggleElement.attr("href", "javascript:profileJS.toggleDetailsOn('" + memberUuid + "')");

        /* Accordions */
        membershipPeriodElement = $(
            "#adm_profile_role_memberships_current_accordion_content, " +
            "#adm_profile_role_memberships_former_accordion_content, " +
            "#adm_profile_role_memberships_future_accordion_content"
        ).find("#adm_membership_period_" + memberUuid).first();
        membershipPeriodElement.css({"visibility": "hidden", "display": "none"});
        // find the parent element with href toggleDetailsOff('memberUuid') and change it to toggleDetailsOn('memberUuid')
        toggleElement = membershipPeriodElement.parent().find("a[href=\"javascript:profileJS.toggleDetailsOff('" + memberUuid + "')\"]");
        toggleElement.attr("href", "javascript:profileJS.toggleDetailsOn('" + memberUuid + "')");
    };
}
