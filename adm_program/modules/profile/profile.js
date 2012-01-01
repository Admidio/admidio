/******************************************************************************
 * Profil Javascript Funktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function profileJSClass()
{
	this.formerRoleCount 			= 0;
	this.usr_id 					= 0;
	this.deleteRole_ConfirmText		= "";
	this.deleteFRole_ConfirmText 	= "";
	this.changeRoleDates_ErrorText	= "";
	this.setBy_Text					= "";
	this.errorID					= 0;
	
	this.init = function()
	{
		$("a[rel='colorboxContent']").colorbox({rel:'nofollow'});
		$("a[rel='colorboxRoles']").colorbox({rel:'nofollow',onComplete:function(){profileJS.jQueryAjaxLoadRolesAppend()}});
		$("a[rel='colorboxPWContent']").colorbox({rel:'nofollow',onComplete:function(){profileJS.jQueryAjaxLoadPWAppend()}});
        $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',scrolling:false,onComplete:function(){$("#admButtonNo").focus();}});
	}
	this.reloadRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/roles_ajax.php?action=0&usr_id=" + this.usr_id,
			dataType: "html",
			success: function(responseText, statusText){
				$("#profile_roles_box_body").html(responseText);
                $("a[rel='lnkPopupWindow']").colorbox({rel:'nofollow',onComplete:function(){$("#admButtonNo").focus();}});
			}
		});
	}
	this.reloadFormerRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/roles_ajax.php?action=1&usr_id=" + this.usr_id,
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
		$("#anzeige:first-child").text(this.setBy_Text + ": " + name);
	}
	this.deleteShowInfo = function()
	{
		$("#anzeige:first-child").text(this.setBy_Text + ": ");
	}
	this.toggleDetailsOn = function(role_id)
	{
		$("#mem_rol_" + role_id).css({"visibility":"visible","display":"block"});
	}
	this.toggleDetailsOff = function(role_id)
	{
		$("#mem_rol_" + role_id).css({"visibility":"hidden","display":"none"});
	}
	this.changeRoleDates = function(role_id)
	{
		$.ajax({
				type: "GET",
				url: gRootPath + "/adm_program/modules/profile/roles_ajax.php?action=2&usr_id="+this.usr_id+"&mode=1&rol_id="+role_id+"&rol_begin="+document.getElementById("admRoleStart"+role_id).value+"&rol_end="+document.getElementById("admRoleEnd"+role_id).value,
				dataType: "html",
				success: function(responseText, statusText){
					if(responseText.match(/<SAVED\/>/gi))
					{
						responseText = responseText.replace(/<SAVED\/>/gi,"");
						$("#mem_rol_" + role_id).text(responseText);
						setTimeout('$("#mem_rol_" + role_id).fadeOut("slow")',500);
						setTimeout('profileJS.reloadRoleMemberships();',500);
						setTimeout('profileJS.reloadFormerRoleMemberships();',500);
					}
					else
					{
						profileJS.errorID++;
						$("#mem_rol_" + role_id).append('<div id="errorAccured'+profileJS.errorID+'" style="border:1px solid red; padding:5px; margin:2px 0px 2px 0px; text-align:left;">'+ responseText +'</div>');
						setTimeout('$("#errorAccured'+profileJS.errorID+'").fadeOut("slow")',4250);
					}
				},
				error: function (xhr, ajaxOptions, thrownError){
					alert(this.changeRoleDates_ErrorText);
					jQueryAlert("ROL_CHANGE_ROLE_DATES_ERROR");
				}
			});
	}
	this.jQueryAjaxLoadRolesAppend = function()
	{
		$("#cboxLoadedContent").append('\n<div id="colorBox_resultInfo"></div>');

		$("#rolesForm").ajaxForm({ 
			target:        '#colorBox_resultInfo',  							 // target element(s) to be updated with server response 
			beforeSubmit:  function(formData, jqForm, options){		 // pre-submit callback 
				$("#colorBox_resultInfo").css({ "display":"block" });
				return true; 
			},  													
			success:       function(responseText, statusText){		 // post-submit callback
				$.fn.colorbox.resize();
				if(responseText.match(/<SAVED\/>/gi))
				{
						profileJS.reloadRoleMemberships();
						profileJS.reloadFormerRoleMemberships();
						setTimeout("$.fn.colorbox.close()",1000);	
				}
			}	 
		});
	}
	this.jQueryAjaxLoadPWAppend = function()
	{
		$("#cboxLoadedContent").append('\n<div id="colorBox_resultInfo"></div>');
		$("#passwordForm").ajaxForm({ 
			target:        '#colorBox_resultInfo',  							 // target element(s) to be updated with server response 
			beforeSubmit:  function(formData, jqForm, options){		 // pre-submit callback 
				$("#colorBox_resultInfo").css({ "display":"block" });
				return true; 
			},  													
			success:       function(responseText, statusText){		 // post-submit callback
				$.fn.colorbox.resize();
				if(responseText.match(/<SAVED\/>/gi))
				{
						setTimeout("$.fn.colorbox.close()",1000);	
				}
			}	 
		});
	}
}