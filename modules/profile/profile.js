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

    this._sectionExists = function (selector) {
        return $(selector).length > 0;
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
