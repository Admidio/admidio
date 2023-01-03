/**
 ***********************************************************************************************
 * Javascript functions for profile module
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

function ProfileJS(gRootPath) {
    this.url                     = gRootPath + "/adm_program/modules/profile/profile_function.php";
    this.formerRoleCount         = 0;
    this.futureRoleCount         = 0;
    this.userUuid                = "";
    this.errorID                 = 0;

    this.reloadRoleMemberships = function () {
        $.get({
            url: this.url + "?mode=4&user_uuid=" + this.userUuid,
            dataType: "html",
            success: function(responseText) {
                $("#profile_roles_box_body").html(responseText);
                $(".admMemberInfo").click(function() {
                    showHideMembershipInformation($(this));
                });
                formSubmitEvent('#profile_roles_box_body');
            }
        });
    };
    this.reloadFormerRoleMemberships = function () {
        $.get(
            {
                url: this.url + "?mode=5&user_uuid=" + this.userUuid,
                dataType: "html",
                success: function(responseText) {
                    $("#profile_former_roles_box_body").html(responseText);
                    formSubmitEvent('#profile_former_roles_box_body');
                }
            }
        );
    };
    this.reloadFutureRoleMemberships = function () {
        $.get(
            {
                url: this.url + "?mode=6&user_uuid=" + this.userUuid,
                dataType: "html",
                success: function(responseText) {
                    $("#profile_future_roles_box_body").html(responseText);
                    formSubmitEvent('#profile_future_roles_box_body');
                }
            }
        );
    };

    this.markLeader = function (element) {
        if (element.checked) {
            var roleName = getRoleName(element);
            $("#" + roleName).attr("checked", true);
        }
    };
    this.unMarkLeader = function (element) {
        if (!element.checked) {
            var roleName = getRoleName(element);
            $("#" + roleName).attr("checked", false);
        }
    };
    function getRoleName(element) {
        var name = element.name;
        var posNumber = name.search("-") + 1;
        var number = name.substr(posNumber, name.length - posNumber);
        return "leader-" + number;
    }

    this.toggleDetailsOn = function (memberUuid) {
        $("#membership_period_" + memberUuid).css({"visibility": "visible", "display": "block"});
    };

    this.toggleDetailsOff = function (memberUuid) {
        $("#membership_period_" + memberUuid).css({"visibility": "hidden", "display": "none"});
    };
}
