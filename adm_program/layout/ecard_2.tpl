<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>E@card</title>
<STYLE TYPE="text/css">
 td { font-family:Comic Sans MS; font-size: 14px; font-weight: normal; color: #000000; text-decoration: none;}
</STYLE>
</head>
<body>
<table align="center" border="0" cellpadding="0" cellspacing="0">
<tbody><tr><td>
<table align="center" border="0" cellpadding="0" width="100%" cellspacing="0">
   <tbody><tr>
    <td id="card_form" colspan="2" style="border: 1px solid rgb(0, 0, 0); background:url('<%ecard_hintergrund_name%>'); padding: 10px; vertical-align:middle;" align="center">
		<table border="0" cellpadding="0" cellspacing="0">
	 <tbody><tr>
	  <td align="center" style=" vertical-align:middle;">
	  <table  border="0" cellpadding="0" cellspacing="2">
	   <tbody>
	   <tr>
	    <td align="center" width="<%ecard_image_width%>"><img src="<%ecard_image_name%>" width="<%ecard_image_width%>" height="<%ecard_image_height%>" style="border: 0px; margin: 10pt 10px 10px 10pt; padding: 4px;" alt="Ecard"></td>
	   </tr>
	   	  </tbody></table></td>
	 </tr>
	</tbody></table>
	</td>
   </tr>
  </tbody></table>
  </td></tr>
  <tr>
  <td height="10px;">
  </td>
  </tr>
  <tr><td>
  <table align="center" border="0" cellpadding="0" cellspacing="0">
   <tbody><tr>
    <td id="card_form" colspan="2" style="border: 1px solid rgb(0, 0, 0); background:url('<%ecard_hintergrund_name%>'); padding: 10px; ">
		<table border="0" cellpadding="0" cellspacing="0">
	 <tbody><tr>
	  <td height="90%" style=" vertical-align: top;">
	  <table  border="0" cellpadding="0" height="100%" cellspacing="2">
	   <tbody>
       <tr>
	    <td>Eine E@card von: <b><a href="<%g_root_path%>/adm_program/modules/mail/mail.php?usr_id=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></b></td>
	   </tr>
       <tr>
         <td height="10"></td>
       </tr>
       <tr height="90%">
       <td height="90%" style="vertical-align:middle">
       <div align="center">
          <div style="vertical-align:middle;"><%ecard_message%></div>
       </div>
       </td></tr>
	   	  </tbody></table>
       </td>
	  <td style="width: 8px;">&nbsp;</td>

	  <td style="background: rgb(0, 0, 0) none repeat scroll 0%; width: 1px;"><img src="" alt="" border="0" height="270" width="1"></td>
	  <td style="width: 8px;">&nbsp;</td>
	  <td style="vertical-align: top;">
	  <table border="0" cellpadding="1" cellspacing="2">
	   <tbody><tr>
	    <td colspan="2" style="height: 59px;"><div align="right">
		<table border="0" cellpadding="0" cellspacing="0">
		 <tbody><tr>
		  <td id="card_stamp" style="width: 101px; height: 59px;" align="right"><div align="right"><img src="<%ecard_briefmarke%>" alt="frame" border="0" height="59" width="101"></div></td>

		 </tr>
		</tbody></table></div></td>
	   </tr>
       <tr>
	    <td colspan="2" height="40px;">&nbsp;</td>
	   </tr>
	   <tr>
	    <td colspan="2" height="20px;" style="font-size:18px" >An</td>
	   </tr>
	   <tr>
	    <td style="font-size:16px">Name:</td>
	    <td><%ecard_reciepient_name%></td>
	   </tr>
	   <tr>
	    <td style="font-size:16px">E-Mail:</td>
	    <td><%ecard_reciepient_email%></td>
	   </tr>
	   <tr>
	    <td height="50px;" colspan="2">&nbsp;</td>
	   </tr>
	  </tbody></table>
      </td>
	 </tr>
	</tbody></table>
	</td>
   </tr>
  </tbody></table>
</td>
</tr>
</tbody></table>
</body>
</html>

