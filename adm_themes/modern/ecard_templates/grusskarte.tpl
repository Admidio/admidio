<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>Grußkarte</title>
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
    <div align="center" style="margin-top:30px;">
        Eine Gru&szlig;karte von: <b><a href="<%g_root_path%>/adm_program/modules/mail/mail.php?usr_id=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></b>
    </div>
    <hr width="80%" color="#000000" size="1">
    <div align="center">
        <img src="<%ecard_image_name%>" width="<%ecard_image_width%>" height="<%ecard_image_height%>" style="border: 1px solid rgb(221, 221, 221); padding: 4px; margin: 10pt 10px 10px 10pt;" alt="Grußkarte">
    </div>
    <hr width="80%" color="#000000" size="1">
    <div align="center" style="margin-top:20px;">
        <div id="Message" style="vertical-align:middle;"><%ecard_message%></div>
    </div>
</body>
</html>
