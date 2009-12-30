/******************************************************************************
 * Funktionen zum entfernen von Rollen im Profil
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function profileJSClass()
{
	this.formerRoleCount 			= 0;
	this.usr_id 					= 0;
	this.deleteRole_ConfirmText		= "";
	this.deleteRole_ErrorText		= "";
	this.deleteFRole_ConfirmText 	= "";
	this.deleteFRole_ErrorText 		= "";
	
	this.reloadRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/roles_ajax.php?action=0&user_id=" + this.usr_id,
			dataType: "html",
			success: function(html){
				$("#profile_roles_box_body").html(html);
			}
		});
	}
	this.reloadFormerRoleMemberships = function()
	{
		$.ajax({
			type: "GET",
			url: gRootPath + "/adm_program/modules/profile/roles_ajax.php?action=1&user_id=" + this.usr_id,
			dataType: "html",
			success: function(html){
				$("#profile_former_roles_box_body").html(html);
			}
		});
	}
	
	this.deleteRole = function(rol_id, rol_name)
	{
		var msg_result = confirm(this.deleteRole_ConfirmText.replace(/\[rol_name\]/gi,rol_name));
		if(msg_result)
		{
			// Listenelement mit Unterelemten einfuegen
			$('#profile_former_roles_box').fadeIn('slow');
	
			$.ajax({
				type: "POST",
				url: gRootPath + "/adm_program/modules/profile/profile_function.php",
				data: "mode=2&user_id=" + this.usr_id + "&rol_id=" + rol_id,
				dataType: "html",
				success: function(html){
					$("#role_" + rol_id).fadeOut("slow");
					profileJS.formerRoleCount++;
					profileJS.reloadFormerRoleMemberships();
				},
				error: function (xhr, ajaxOptions, thrownError){
					alert(this.deleteRole_ErrorText);
				}
			});
		}
	}
	
	this.deleteFormerRole = function(rol_id, rol_name) 
	{
		var msg_result = confirm(this.deleteFRole_ConfirmText.replace(/\[rol_name\]/gi,rol_name));
		if(msg_result)
		{
			$.ajax({
				type: "POST",
				url: gRootPath + "/adm_program/modules/profile/profile_function.php",
				data: "mode=3&user_id=" + this.usr_id + "&rol_id=" + rol_id,
				dataType: "html",
				success: function(html){
					$("#former_role_" + rol_id).fadeOut("slow");
					profileJS.formerRoleCount--;
					if(profileJS.formerRoleCount == 0)
					{
						$("#profile_former_roles_box").fadeOut("slow");
					}
				},
				error: function (xhr, ajaxOptions, thrownError){
					alert(this.deleteFRole_ErrorText);
				}
			});
		}
	}
}