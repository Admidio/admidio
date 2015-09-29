/******************************************************************************
 * Javascript functions for profile module
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function profileJSClass() {
    this.formerRoleCount         = 0;
    this.futureRoleCount         = 0;
    this.usr_id                  = 0;
    this.deleteRole_ConfirmText  = "";
    this.deleteFRole_ConfirmText = "";
    this.setBy_Text              = "";
    this.errorID                 = 0;

    this.reloadRoleMemberships = function() {
        $.ajax({
            type: "GET",
            url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=4&user_id=" + this.usr_id,
            dataType: "html",
            success: function(responseText) {
                $("#profile_roles_box_body").html(responseText);
                $(".admMemberInfo").click(function () {
                    showHideMembershipInformation($(this));
                });
                formSubmitEvent();
            }
        });
    };
    this.reloadFutureRoleMemberships = function() {
        $.ajax({
            type: "GET",
            url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=6&user_id=" + this.usr_id,
            dataType: "html",
            success: function(responseText) {
                $("#profile_future_roles_box_body").html(responseText);
                formSubmitEvent();
            }
        });
    };
    this.reloadFormerRoleMemberships = function() {
        $.ajax({
            type: "GET",
            url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=5&user_id=" + this.usr_id,
            dataType: "html",
            success: function(responseText) {
                $("#profile_former_roles_box_body").html(responseText);
                formSubmitEvent();
            }
        });
    };

    this.markLeader = function(element) {
        if(element.checked)
        {
            var roleName = getRoleName(element);
            $("#" + roleName).attr("checked", true);
        }
    };
    this.unMarkLeader = function(element) {
        if(!element.checked)
        {
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

    this.showInfo = function(name) {
        $("#profile_authorization_content:first-child").text(this.setBy_Text + ": " + name);
    };
    this.deleteShowInfo = function() {
        $("#profile_authorization_content:first-child").text(this.setBy_Text + ": ");
    };
    this.toggleDetailsOn = function(member_id) {
        $("#membership_period_" + member_id).css({"visibility": "visible","display": "block"});
    };
    this.toggleDetailsOff = function(member_id) {
        $("#membership_period_" + member_id).css({"visibility": "hidden","display": "none"});
    };
}
