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
                $("#adm_profile_role_memberships_current .card-body").html(responseText);
                formSubmitEvent('#adm_profile_role_memberships_current .card-body');
                /* Accordions */
                $("#adm_collapse_profile_role_memberships_current .accordion-body").html(responseText);
                formSubmitEvent('#adm_collapse_profile_role_memberships_current .accordion-body');
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
                    $("#adm_profile_role_memberships_former .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_former .card-body');
                    /* Accordions */
                    $("#adm_collapse_profile_role_memberships_former .accordion-body").html(responseText);
                    formSubmitEvent('#adm_collapse_profile_role_memberships_former .accordion-body');
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
                    $("#adm_profile_role_memberships_future .card-body").html(responseText);
                    formSubmitEvent('#adm_profile_role_memberships_future .card-body');
                    /* Accordions */
                    $("#adm_collapse_profile_role_memberships_future .accordion-body").html(responseText);
                    formSubmitEvent('#adm_collapse_profile_role_memberships_future .accordion-body');
                }
            }
        );
    };

    this.toggleDetailsOn = function (memberUuid) {
        $("#adm_membership_period_" + memberUuid).css({"visibility": "visible", "display": "block"});
    };

    this.toggleDetailsOff = function (memberUuid) {
        $("#adm_membership_period_" + memberUuid).css({"visibility": "hidden", "display": "none"});
    };
}
