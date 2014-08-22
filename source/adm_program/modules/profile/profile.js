/******************************************************************************
 * Javascript functions for profile module
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function profileJSClass()
{
	this.formerRoleCount 			= 0;
	this.futureRoleCount 			= 0;
	this.usr_id 					= 0;
	this.deleteRole_ConfirmText		= "";
	this.deleteFRole_ConfirmText 	= "";
	this.setBy_Text					= "";
	this.errorID					= 0;
	
	this.init = function()
	{
		$("a[rel='colorboxContent']").colorbox({rel:'nofollow'});
		$("#edit_roles_link").colorbox({rel:'nofollow'});
		$("#menu_item_password a").colorbox({width:'50%',rel:'nofollow',onComplete:function(){$("#password_form:first *:input[type!=hidden]:first").focus();}});
		$("#password_link").colorbox({width:'50%',rel:'nofollow',onComplete:function(){$("#password_form:first *:input[type!=hidden]:first").focus();}});
        $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',scrolling:false,onComplete:function(){$("#admButtonNo").focus();}});
	}
	this.reloadRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=4&user_id=" + this.usr_id,
			dataType: "html",
			success: function(responseText, statusText){
				$("#profile_roles_box_body").html(responseText);
                $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',onComplete:function(){$("#admButtonNo").focus();}});
    			$(".admMemberInfo").click(function () { showHideMembershipInformation($(this)) });
			}
		});
	}
	this.reloadFutureRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=6&user_id=" + this.usr_id,
			dataType: "html",
			success: function(responseText, statusText){
				$("#profile_future_roles_box_body").html(responseText);
                $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',onComplete:function(){$("#admButtonNo").focus();}});
			}
		});
	}
	this.reloadFormerRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/profile_function.php?mode=5&user_id=" + this.usr_id,
			dataType: "html",
			success: function(responseText, statusText){
				$("#profile_former_roles_box_body").html(responseText);
                $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',onComplete:function(){$("#admButtonNo").focus();}});				               
			}
		});
	}
	
	this.markLeader = function(element)
	{
		if(element.checked == true)
		{
			var name   = element.name;
			var pos_number = name.search("-") + 1;
			var number = name.substr(pos_number, name.length - pos_number);
			var role_name = "role-" + number;
			$("#" + role_name).attr("checked",true);
		}
	}

	this.unMarkLeader = function(element)
	{
		if(element.checked == false)
		{
			var name   = element.name;
			var pos_number = name.search("-") + 1;
			var number = name.substr(pos_number, name.length - pos_number);
			var role_name = "leader-" + number;
			$("#" + role_name).attr("checked",false);
		}
	}
	this.showInfo = function(name)
	{
		$("#profile_authorization_content:first-child").text(this.setBy_Text + ": " + name);
	}
	this.deleteShowInfo = function()
	{
		$("#profile_authorization_content:first-child").text(this.setBy_Text + ": ");
	}
	this.toggleDetailsOn = function(member_id)
	{
		$("#membership_period_" + member_id).css({"visibility":"visible","display":"block"});
	}
	this.toggleDetailsOff = function(member_id)
	{
		$("#membership_period_" + member_id).css({"visibility":"hidden","display":"none"});
	}
}