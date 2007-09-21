<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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
<table align="center" border="0" cellpadding="0" cellspacing="0" summary="Grukarte">
<tbody><tr><td>
<table align="center" border="0" cellpadding="0" width="100%" cellspacing="0" summary="Grukarte Bild">
   <tbody><tr>
    <td id="card_form_image" colspan="2" style="border: 1px solid rgb(0, 0, 0); background:url('<%ecard_hintergrund_name%>'); padding: 10px; vertical-align:middle;" align="center">
		<table border="0" cellpadding="0" cellspacing="0" summary="Bild">
	 <tbody><tr>
	  <td align="center" style=" vertical-align:middle;">
	  <table  border="0" cellpadding="0" cellspacing="2" summary="Bild">
	   <tbody>
	   <tr>
	    <td align="center" width="<%ecard_image_width%>"><img src="<%ecard_image_name%>" width="<%ecard_image_width%>" height="<%ecard_image_height%>" style="border: 0px; margin: 10pt 10px 10px 10pt; padding: 4px;" alt="Grußkarte" /></td>
	   </tr>
	   	  </tbody></table></td>
	 </tr>
	</tbody></table>
	</td>
   </tr>
  </tbody></table>
  </td></tr>
  <tr>
    <td style="height:10px;"></td>
  </tr>
  <tr><td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" summary="Grukarte Text" style="min-height:300px;">
   <tbody><tr>
    <td id="card_form" colspan="2" style="border: 1px solid rgb(0, 0, 0); padding: 10px; ">
		<table border="0" cellpadding="0" cellspacing="0" summary="Grukarte">
	 <tbody><tr>
	  <td style="vertical-align: top; height:90%; ">
	  <table  border="0" cellpadding="0" style="height:270px; min-height:400px; width:400px; " cellspacing="2" summary="Sender">
	   <tbody>
       <tr>
	    <td>Eine Gru&szlig;karte von: <b><a href="<%g_root_path%>/adm_program/modules/mail/mail.php?usr_id=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></b></td>
	   </tr>
       <tr>
         <td style="height:10px;"></td>
       </tr>
       <tr style="height:90%;">
       <td style="vertical-align:middle; height:90%">
       <div align="center" id="Message">
          <div style="vertical-align:middle;"><%ecard_message%></div>
       </div>
       </td></tr>
	   	  </tbody></table>
       </td>
	  <td style="width: 8px; vertical-align:bottom; min-height:300px;">
	       <img src="<%g_root_path%>/adm_program/images/ecards/ownertext.png" width="30px" style="border:0px;" />
	  </td>	  
	  <td style="background: rgb(0, 0, 0); width:1px;"><br/></td>
	  <td style="width: 8px;">&nbsp;</td>
	  <td style="vertical-align: top;height:100%;" >
	   <table  border="0" cellpadding="0" cellspacing="2" style="height:100%" summary="Sender Data">
           <tbody>
               <tr style="vertical-align:top; margin:5px 5px 5px 5px;">
                <td>
                      <div align="right">
                        <img src="<%g_root_path%>/adm_program/images/ecards/stamp.png" alt="Briefmarke"  style="border:0px;" height="129" width="150" />
                      </div>
                </td>
               </tr>
               <tr>
               <td style="height:5px;"></td>
               </tr>
               <tr style="height:90%; vertical-align:middle;">
                   <td style="height:90%; vertical-align:middle; padding:5px; width:300px;">
                    <table summary="Sender Data" cellpadding="0" cellspacing="0" rules="none" border="0" width="300px">
                         <tr>
                             <td><b>An:</b></td>
                             <td style="padding-left:5px;"><%ecard_reciepient_name%></td>
                         </tr>
                         <tr style="height:1px;">
                         	<td><hr color="black" size="1px" /></td>
                         	<td><hr color="black" size="1px" /></td>
                         </tr>
                         <tr>
                             <td><b>E-Mail:</b></td>
                             <td  style="padding-left:5px;"><%ecard_reciepient_email%></td>
                         </tr>
                         <tr style="height:1px;">
                         	<td><hr color="black" size="1px" /></td>
                         	<td><hr color="black" size="1px" /></td>
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
</tr>
</tbody></table>
</body>
</html>

