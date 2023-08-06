<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><%ecard_greeting_card_string%></title>
    <style type="text/css">
        * { font-family: <%ecard_font%>; }
    </style>
</head>
<body>
    <div align="center" style="margin-top: 30px;">
        <%ecard_greeting_card_from%>: <strong><a href="<%g_root_path%>/adm_program/modules/messages/messages_write.php?user_uuid=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></strong>
    </div>
    <hr style="border: 1px solid #000000; width: 80%;"/>
    <div align="center">
        <img src="<%ecard_image_name%>" style="border: 1px solid #DDDDDD; padding: 4px; margin: 10pt 10px 10px 10pt;" alt="<%ecard_greeting_card_string%>"/>
    </div>
    <hr style="border: 1px solid #000000; width: 80%;"/>
    <div align="center" style="margin-top: 20px;">
        <div id="Message" style="vertical-align: middle;"><%ecard_message%></div>
    </div>
</body>
</html>
