/******************************************************************************
 * Ecard Javascript functions
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
	this.sendDoneURL			= "";
	this.templates				= new Array();
	this.ccSaveDataArray		= new Array();
	this.submitOptions      = new Array();
	
	this.init = function()
	{
		$(document).ready(function() {
			$(".ecardPhoto").colorbox({photo:true});

			$("#btn_ecard_preview").click(function(event){
				event.preventDefault();
				$("#" + ecardJS.ecardformid + " input[id='submit_action']").val("preview");
                var test = CKEDITOR.instances.ecard_message.getData();
				$("#" + ecardJS.ecardformid + " textarea[name='ecard_message']").text( CKEDITOR.instances.ecard_message.getData() );	

				$.ajax({ // create an AJAX call...
					data: $("#" + ecardJS.ecardformid).serialize(), // get the form data
					type: 'POST', // GET or POST
					url: 'ecard_preview.php', // the file to call
					success: function(response) { // on success..
						$.colorbox({width:"70%",height:"70%",iframe:true,fastIframe:false,onComplete:function() {		
								var cBoxLContent = $("#cboxLoadedContent");
								var iFrame = cBoxLContent.find("iframe");
								iFrame.contents().find('html').html(response);	

								var foundHTML = iFrame.contents().find('html');
								var height = foundHTML.height() + 5;
								var width = foundHTML.width();
								var cBoxHeight = cBoxLContent.height();
								var cBoxWidth = cBoxLContent.width();
								iFrame.height(height > cBoxHeight ? height : cBoxHeight );
								iFrame.width(width > cBoxWidth ? cBoxWidth : width );			
						}});
					}
				});

				return false; // cancel original event to prevent form submitting
			});

		});
	}
}