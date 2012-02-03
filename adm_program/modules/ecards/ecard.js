/******************************************************************************
 * Grußkarte Javascript Funktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
function ecardJSClass()
{
	this.baseDropDiv_id			= "basedropdownmenu";
	this.dropDiv_id				= "dropdownmenu";
	this.externDiv_id			= "extern";
	this.switchDiv_id			= "externSwitch";
	this.counterDiv_id			= "counter";
	this.menueDiv_id			= "Menue";
	this.wrongDiv_id			= "wrong";
	this.moreRecipientDiv_id	= "moreRecipient";
	this.getmoreRecipientDiv_id = "getmoreRecipient";
	this.ccrecipientConDiv_id	= "ccrecipientContainer";
	this.max_recipients			= 5;
	this.now_recipients			= 0;
	this.ecardformid			= "ecard_form";
	this.recipient_Text			= "";
	this.nameOfRecipient_Text	= "";
	this.emailOfRecipient_Text	= "";
	this.recipientName_Text		= "";
	this.recipientEmail_Text	= "";
	this.message_Text			= "";
	this.emailLookInvalid_Text	= "";
	this.contentIsLoading_Text	= "";
	this.moreRecipients_Text	= "";
	this.noMoreRecipients_Text	= "";
	this.blendInSettings_Text	= "";
	this.blendOutSettings_Text	= "";
	this.internalRecipient_Text	= "";
	this.messageTooLong			= "";
	this.loading_Text			= "";
	this.send_Text				= "";
	this.template_Text			= "";
	this.templates				= new Array();

	this.ccSaveDataArray		= new Array();
	
	this.init = function()
	{
		$(document).ready(function() {
			$("a[rel=\'colorboxImage\']").colorbox({photo:true});
			ecardJS.getMenu();
		});
	}

	this.popup_win = function(theURL,winName,winOptions)
	{
		 win = window.open(theURL,winName,winOptions);
		 win.focus();
	}
	
	this.validateForm = function()
	{
		var error         = false;
		var error_message = '';
	
		if ($("#" + this.ecardformid + " input[name='ecard[name_recipient]']").val() == "" || $("#" + this.ecardformid+ "input[name='ecard[name_recipient]']").val() == "< "+ this.recipientName_Text +" >")
		{
			error = true;
			error_message += "- " + this.nameOfRecipient_Text.replace('[VAR1]'," ") + "\n";
		}
		if ($("#" + this.ecardformid + " input[name='ecard[email_recipient]']").val() == "" || this.emailValidation( $("#" + this.ecardformid + " input[name='ecard[email_recipient]']").val() ) == false)
		{
			error = true;
			error_message += "- " + this.emailOfRecipient_Text.replace('[VAR1]'," ") + "\n";
		}
		if (CKEDITOR.instances.admEcardMessage.getData() == '')
		{
			error = true;
			error_message += "- " + this.message_Text + "\n";
		}
		for(var i=1; i <= this.now_recipients; i++)
		{
			var namedoc		= $("#" + this.ecardformid + " input[name='ecard[name_ccrecipient_"+[i]+"]']");
			var emaildoc	= $("#" + this.ecardformid + " input[name='ecard[email_ccrecipient_"+[i]+"]']");
			var message		= "";
			var goterror	= false;
			if(namedoc)
			{
				if(namedoc.val() == "")
				{
					message += " - "+ this.nameOfRecipient_Text.replace('[VAR1]'," "+ i +". CC - ") +"\n";
					error = true;
					goterror = true;
				}
			}
			if(emaildoc)
			{
				if(emaildoc.val() == "" || !this.emailValidation(emaildoc.val()))
				{
					message += " - "+ this.emailOfRecipient_Text.replace('[VAR1]'," "+ i +". CC - ") +"\n";
					error = true;
					goterror = true;
				}
			}
			if(goterror && i==1)
			{
				error_message += "\nCC - "+ this.message_Text +"\n_________________________________\n\n" + message;
			}
			else if(goterror)
			{
				error_message += "_________________________________\n\n" + message;
			}
		}
		if (error)
		{
			jQueryAlert("ECA_FILL_INPUTS", error_message);
			return false;  // Formular wird nicht abgeschickt.
		}
		else
		{
			return true;  // Formular wird abgeschickt.
		}
		return false;
	} // Ende function validateForm()
	
	this.emailValidation = function(str)
	{
		var zeichen=Array("<",">")
		var at="@"
		var dot="."
		var lat=str.indexOf(at)
		var lstr=str.length
		var ldot=str.indexOf(dot)
	
		for(var i=0;i<zeichen.length;i++)
		{
			if (str.indexOf(zeichen[i])!=-1){
			return false
			}
		}
	
		if (str.indexOf(at)==-1){
		return false
		}
	
		if (str.indexOf(at)==-1 || str.indexOf(at)==0 || str.indexOf(at)==lstr){
		return false
		}
	
		if (str.indexOf(dot)==-1 || str.indexOf(dot)==0 || str.indexOf(dot)==lstr){
		return false
		}
	
		if (str.indexOf(at,(lat+1))!=-1){
		return false
		}
	
		if (str.substring(lat-1,lat)==dot || str.substring(lat+1,lat+2)==dot){
		return false
		}
	
		if (str.indexOf(dot,(lat+2))==-1){
		return false
		}
	
		if (str.indexOf(" ")!=-1){
		return false
		}
	
		return true
	}
	
	this.makePreview = function()
	{
		$.fn.colorbox.init();
		$("#" + this.ecardformid + " input[name='ecard[submit_action]']").attr("value","preview");
		$("#" + this.ecardformid).attr("action","ecard_preview.php");
		$.fn.colorbox({href:"ecard_preview.php",width:"70%",height:"70%",iframe:true,fastIframe:false,onComplete:function(){
								$("#" + ecardJS.ecardformid).attr("target",$("#cboxLoadedContent iframe").attr("name"));
								$("#" + ecardJS.ecardformid).submit();}});		
	}
	
	this.sendEcard = function()
	{
		if (this.validateForm())
		{
			$("#" + this.ecardformid + " input[name='ecard[submit_action]']").attr("value","send");
			this.setJQuerySubmitOptions();
			jQuery.fn.SubmitEcard();
		}
		else
		{
			$("#" + this.ecardformid).attr("onsubmit","");
			$("#" + this.ecardformid + " input[name='ecard[submit_action]']").attr("value","");
		}
	}
	
	this.blendout = function(id)
	{
		if($("#" + id).val() == "< "+ this.recipientName_Text +" >" || $("#" + id).val() == "< "+ this.recipientEmail_Text +" >")
		{
			$("#" + id).val("");
		}
	}
	
	this.blendin = function(id,type)
	{
		if($("#" + id).val() == "" && type == 1)
		{
			$("#" + id).attr("value","< "+ this.recipientName_Text +" >");
		}
		else if($("#" + id).val() == "" && type == 2)
		{
			$("#" + id).attr("value","< "+ this.recipientEmail_Text +" >");
			$("#" + id).css("color","black");
			$("#" + this.menueDiv_id).css("height","49px");
			$("#" + this.wrongDiv_id).css("display","none");
			$("#" + this.wrongDiv_id).html("");
		}
		else if($("#" + id).val() != "" && $("#" + id).val() != "< "+ this.recipientEmail_Text +" >"&& type == 2)
		{
			if( !this.emailValidation($("#" + id).val()) )
			{
				$("#" + id).css("color","red");
				$("#" + this.menueDiv_id).css("height","75px");
				$("#" + this.wrongDiv_id).css("display","block");
				$("#" + this.wrongDiv_id).html(this.emailLookInvalid_Text);
				
			}
			else
			{
				$("#" + id).css("color","black");
				$("#" + this.menueDiv_id).css("height","49px");
				$("#" + this.wrongDiv_id).css("display","none");
				$("#" + this.wrongDiv_id).html("");
			}
		}
	}
	
	this.setJQuerySubmitOptions = function()
	{
		if($("#" + ecardJS.ecardformid + " input[name='ecard[submit_action]']").attr("value") == "send")
		{
			var options = { 
				target:        '#cboxLoadedContent',  							 // target element(s) to be updated with server response
				url: gRootPath + '/adm_program/modules/ecards/ecard_send.php',
				beforeSubmit: function(formData, jqForm, options) { 
					$("#ecardSubmit").html('<img src="'+ gThemePath +'/icons/email.png" alt="' + ecardJS.send_Text + '" />&nbsp;<img src="'+ gThemePath + '/icons/loader.gif" alt="' + this.loading_Text + '" />');
				},
				success:       function(responseText, statusText){		 // post-submit callback
					$.fn.colorbox({html:responseText});
					$("#ecardSubmit").html('<img src="'+ gThemePath+ '/icons/email.png" alt="' + ecardJS.send_Text + '" />&nbsp;' + ecardJS.send_Text);
				}	 
			}; 
			jQuery.fn.SubmitEcard = function(){
				$.fn.colorbox.init();
				$("#" + ecardJS.ecardformid + " textarea[name='admEcardMessage']").text( CKEDITOR.instances.admEcardMessage.getData() );
				if($("#" + ecardJS.ecardformid + " input[name='ecard[submit_action]']").attr("value") == "send")
					$("#" + ecardJS.ecardformid).ajaxSubmit(options);
			};
		}
		$("#" + ecardJS.ecardformid).attr("action","ecardJS.makePreview();");
	}

	this.makeAjaxRequest = function(url,divId,funcSuccess)
	{
		$("#" + divId).html(this.contentIsLoading_Text);
		$.ajax({
			type: "GET",
			url: url,
			dataType: "html",
			success: function(responseText, statusText){
				$("#" + divId).html(responseText);
				if(funcSuccess != null)
					funcSuccess();
			},
			error: function (xhr, ajaxOptions, thrownError){
				jQueryAlert("SYS_AJAX_REQUEST_ERROR", "\n\tResponse text: "+xhr.responseText+"\n\tAjax options: "+ajaxOptions+"\n\tTrown error: "+thrownError);
				
			}
		});
	}
	
	this.getMenu = function()
	{
		this.makeAjaxRequest(gRootPath + "/adm_program/modules/ecards/ecard_drawdropmenue.php?mode=1" ,this.baseDropDiv_id,function(){$("a[rel='colorboxHelp']").colorbox({preloading:false,photo:false,speed:300,rel:'nofollow'});});
	}
	
	this.getMenuRecepientName = function()
	{
		if($("#" + this.ecardformid + " #rol_id").val() != "externMail")
		{
			this.makeAjaxRequest(gRootPath + '/adm_program/modules/ecards/ecard_drawdropmenue.php?rol_id='+ $("#" + this.ecardformid + " #rol_id").val() , 'dropdownmenu');
		}
		else
		{
			this.getExtern()
		}
	}
	
	this.getMenuRecepientNameEmail = function(usr_id)
	{
		if(usr_id != "bw")
		{
			this.makeAjaxRequest(gRootPath + '/adm_program/modules/ecards/ecard_drawdropmenue.php?usr_id='+ usr_id + '&rol_id='+ $("#" + this.ecardformid + " #rol_id").val(), this.externDiv_id );
		}
		else
		{
			$("#" + this.externDiv_id).html('<input type="hidden" name="ecard[email_recipient]" value="" \/><input type="hidden" name="ecard[name_recipient]"  value="" \/>');
		}
	}
	
	this.saveData = function()
	{
		for(var i=0; i <= this.now_recipients; i++)
		{
			if($("#" + this.ecardformid + " input[name='ecard[email_ccrecipient_"+[i]+"]']").val() != "undefined")
			{
				this.ccSaveDataArray[i]    = new Array();
				this.ccSaveDataArray[i][0] = $("#" + this.ecardformid + " input[name='ecard[email_ccrecipient_"+[i]+"]']").val();
				this.ccSaveDataArray[i][1] = $("#" + this.ecardformid + " input[name='ecard[name_ccrecipient_"+[i]+"]']").val();
			}
		}
	}
	
	this.restoreSavedData = function()
	{
		if(this.ccSaveDataArray)
		{
			for (var i = 0; i < this.ccSaveDataArray.length; i++)
			{
				if($("#" + this.ecardformid + " input[name='ecard[email_ccrecipient_"+[i]+"]']").val() != "undefind")
				{
					$("#" + this.ecardformid + " input[name='ecard[email_ccrecipient_"+[i]+"]']").val(this.ccSaveDataArray[i][0]);
					$("#" + this.ecardformid + " input[name='ecard[name_ccrecipient_"+[i]+"]']").val(this.ccSaveDataArray[i][1]);
				}
			}
		}
	}
	
	this.addRecipient = function()
	{
		if (this.now_recipients < this.max_recipients && this.now_recipients >= 0)
		{
			this.saveData();
			
			this.now_recipients++;
			var data    = '<div id="'+ [this.now_recipients] +'">';
			data += '<table id="table_'+ [this.now_recipients] +'" border="0" summary="data'+ [this.now_recipients] +'">';
			data += '<tr>';
			data += '<td style="width:150px;"><input name="ecard[name_ccrecipient_'+ [this.now_recipients] +']" size="15" maxlength="50" style="width: 150px;" value="" type="text" /><\/td>';
			data += '<td style="width:200px; padding-left:10px;"><input name="ecard[email_ccrecipient_'+ [this.now_recipients] +']" size="15" maxlength="50" style="width: 200px;" value="" type="text" /><\/td><td><span class="iconTextLink"><a href="javascript:ecardJS.delRecipient('+ [this.now_recipients] +');"><img src="'+ gThemePath +'/icons/delete.png" alt="Inhalt löschen" \/><\/a><\/span><\/td>';
			data += '<\/tr><\/table>';
			data += '<\/div>';
			$("#" + this.ccrecipientConDiv_id).append(data);
			
			this.restoreSavedData();
		}
		else
			this.now_recipients = this.max_recipients;
	}
	
	this.delRecipient = function(id)
	{
		var olddiv = "";
		if(typeof(id) == "number")
		{
			olddiv = id;
		}
		else
		{
			olddiv = this.now_recipients;
		}
		$("#" + olddiv).remove();
		this.now_recipients--;
		if (this.now_recipients < 0)
		{
			this.now_recipients = 0;
		}
		if (this.now_recipients == 0)
		{
			if($("#" + this.getmoreRecipientDiv_id +" a").html() == this.noMoreRecipients_Text)
			{
				this.showHideMoreRecipient(this.moreRecipientDiv_id,this.getmoreRecipientDiv_id);
			}
			$("#" + this.moreRecipientDiv_id).css("display","none");
			$("#" + this.getmoreRecipientDiv_id +" a").html(this.moreRecipients_Text);
		}
	}
	
	this.delAllRecipients = function()
	{
		this.now_recipients = 0;
		$("#" + this.ccrecipientConDiv_id).empty();
	}

	this.showHideMoreRecipient = function(divLayer,divMenu)
	{
		if($("#" + divLayer).css("display") == "none")
		{
			$("#" + divLayer).show("slow");
			$("#" + divMenu +" a").html(this.noMoreRecipients_Text);
			this.addRecipient();
		}
		else
		{
			$("#" + divLayer).hide("slow");
			$("#" + divMenu +" a").html(this.moreRecipients_Text);
			this.delAllRecipients();
		}
	}
	
	this.showHideMoreSettings = function(divLayerSetting,divMenuSetting)
	{
		if($("#" + divLayerSetting).css("display") == "none")
		{
			$("#" + divLayerSetting).show("slow");
			$("#" + divMenuSetting +" a").html(this.blendOutSettings_Text);
		}
		else
		{
			$("#" + divLayerSetting).hide("slow");
			$("#" + divMenuSetting +" a").html(this.blendInSettings_Text);
		}
	}
	
	this.getExtern = function()
	{
		if($("#" + this.baseDropDiv_id).css("display") == "none")
		{
			$("#" + this.baseDropDiv_id).empty();
			$("#" + this.dropDiv_id).empty();
			$("#" + this.switchDiv_id).empty();
			$("#" + this.baseDropDiv_id).css("display","block");
			$("#" + this.dropDiv_id).css("display","block");
			$("#" + this.externDiv_id).css("display","none");
			$("#" + this.externDiv_id).html('<input type="hidden" name="ecard[email_recipient]" value="< '+ this.recipientEmail_Text +' >" /><input type="hidden" name="ecard[name_recipient]"  value="< '+ this.recipientName +' >" />');
			this.getMenu();
		}
		else if($("#" + this.baseDropDiv_id).css("display") == "block")
		{
			$("#" + this.baseDropDiv_id).empty();
			$("#" + this.dropDiv_id).empty();
			$("#" + this.baseDropDiv_id).css("display","none");
			$("#" + this.dropDiv_id).css("display","none");
			$("#" + this.externDiv_id).css("display","block");
			$("#" + this.switchDiv_id).append('<a href="javascript:ecardJS.getExtern();">'+ this.internalRecipient_Text +'<\/a>');
			this.makeAjaxRequest(gRootPath + '/adm_program/modules/ecards/ecard_drawdropmenue.php?extern=1', this.externDiv_id );
		}
	
		$("#" + this.wrongDiv_id).css("display","none");
		$("#" + this.wrongDiv_id).empty();
		$("#" + this.menueDiv_id).css("height","49px");
	}
}