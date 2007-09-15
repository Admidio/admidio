<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Gru&szlig;karte</title>
<style type="text/css">
*{
font-family:<%ecard_font%>; 
}
#Message{  
color: <%ecard_font_color%>;
font:<%ecard_font%>;
font-weight:<%ecard_font_bold%>;
font-style:<%ecard_font_italic%>;
font-size:<%ecard_font_size%>px;
}
</style>
</head>
<body>
  <table align="center" border="0" cellpadding="0" cellspacing="0">
   <tbody><tr>
    <td id="card_form" colspan="2" style="border: 1px solid rgb(0, 0, 0); background:url('<%ecard_hintergrund_name%>'); padding: 10px; ">
		<table border="0" cellpadding="0" cellspacing="0">
	 <tbody><tr>
	  <td style=" vertical-align: top;">
	  <table  border="0" cellpadding="0" cellspacing="2">
	   <tbody>
       <tr>
	    <td>Eine Gru&szlig;karte von: <b><a href="<%g_root_path%>/adm_program/modules/mail/mail.php?usr_id=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></b></td>
	   </tr>
	   <tr>
	    <td align="center"><img src="<%ecard_image_name%>" width="<%ecard_image_width%>" height="<%ecard_image_height%>" style="border: 0px; margin: 10pt 10px 10px 10pt; padding: 4px;" alt="Ecard"></td>
	   </tr>
       <tr>
	    <td align="center"><div style="vertical-align:middle; margin-left:10px; margin-right:10px;" id="Message"><%ecard_message%></div></td>
	   </tr>
	   	  </tbody></table></td>
	  <td style="width: 8px;">&nbsp;</td>

	  <td style="background: rgb(0, 0, 0) none repeat scroll 0%; width: 1px;"><img src="" alt="" border="0" height="270" width="1"></td>
	  <td style="width: 8px;">&nbsp;</td>
	  <td style="vertical-align: top; height:100%;">
	  <table  border="0" cellpadding="0" cellspacing="2" style="height:100%" summary="Sender Data">
           <tbody>
               <tr style="vertical-align:top; margin:5px 5px 5px 5px;">
                <td>
                      <div align="right">
                        <img src="<%g_root_path%>/adm_program/images/ecards/standard.gif" alt="Briefmarke"  border="0" height="59" width="101" />
                      </div>
                </td>
               </tr>
               <tr>
               <td style="height:5px;"></td>
               </tr>
               <tr style="height:90%;">
                   <td style="height:90%; vertical-align:middle; padding:5px; background-color:#FFFFFF; z-index:500; border:0px; filter:alpha(opacity=90);-moz-opacity:.90; opacity:.90;">
                     <table summary="Sender Data">
                         <tr>
                             <td><b>An:</b></td>
                             <td><%ecard_reciepient_name%></td>
                         </tr>
                         <tr>
                             <td><b>E-Mail:</b></td>
                             <td><%ecard_reciepient_email%></td>
                         </tr>
                     </table>
                   </td>
               </tr>
           </tbody>
       </table>
      </td>
	 </tr>
	</tbody></table>
	</td>
   </tr>
  </tbody></table>
</body>
</html>

