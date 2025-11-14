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
    this.formerRoleCount         = 0;
    this.futureRoleCount         = 0;
    this.userUuid                = "";
    this.errorID                 = 0;

    this.reloadRoleMemberships = function () {
        $.get({
            url: this.url + "?mode=reload_current_memberships&user_uuid=" + this.userUuid,
            dataType: "html",
            success: function(responseText) {
                /* Tabs */
                $("#adm_profile_role_memberships_current_pane_content .card-body").html(responseText);
                formSubmitEvent('#adm_profile_role_memberships_current_pane_content .card-body');
                /* Accordions */
                $("#adm_profile_role_memberships_current_accordion_content .card-body").html(responseText);
                formSubmitEvent('#adm_profile_role_memberships_current_accordion_content .card-body');
            }
        });
    };
    this.reloadFormerRoleMemberships = function () {
        $.get(
            {
                url: this.url + "?mode=reload_former_memberships&user_uuid=" + this.userUuid,
                dataType: "html",
                success: function(responseText) {
                    /* Tabs */
                    $("#adm_profile_role_memberships_former_pane_content .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_former_pane_content .card-body');
                    /* Accordions */
                    $("#adm_profile_role_memberships_former_accordion_content .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_former_accordion_content .card-body');
                }
            }
        );
    };
    this.reloadFutureRoleMemberships = function () {
        $.get(
            {
                url: this.url + "?mode=reload_future_memberships&user_uuid=" + this.userUuid,
                dataType: "html",
                success: function(responseText) {
                    /* Tabs */
                    $("#adm_profile_role_memberships_future_pane_content .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_future_pane_content .card-body');
                    /* Accordions */
                    $("#adm_profile_role_memberships_future_accordion_content .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_future_accordion_content .card-body');
                }
            }
        );
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
