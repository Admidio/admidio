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
    <table align="center" border="0" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
                <td>
                    <table align="center" border="0" cellpadding="0" width="100%" cellspacing="0">
                        <tbody>
                            <tr>
                                <td id="card_form_image" colspan="2" style="border: 1px solid #000000; padding: 10px; vertical-align: middle;" align="center">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tbody>
                                            <tr>
                                                <td align="center" style=" vertical-align: middle;">
                                                    <table border="0" cellpadding="0" cellspacing="2">
                                                        <tbody>
                                                            <tr>
                                                                <td align="center"><img src="<%ecard_image_name%>" style="border: 0; margin: 10pt 10px 10px 10pt; padding: 4px;" alt="<%ecard_greeting_card_string%>"/></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="height: 10px;"></td>
            </tr>
            <tr>
                <td>
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="min-height: 300px;">
                        <tbody>
                            <tr>
                                <td id="card_form" colspan="2" style="border: 1px solid #000000; padding: 10px;">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tbody>
                                            <tr>
                                                <td style="vertical-align: top; height: 90%;">
                                                    <table border="0" cellpadding="0" style="height: 270px; min-height: 400px; width: 400px;" cellspacing="2">
                                                        <tbody>
                                                            <tr>
                                                                <td><%ecard_greeting_card_from%>: <strong><a href="<%g_root_path%>/adm_program/modules/messages/messages_write.php?user_uuid=<%ecard_sender_id%>" target="_blank"><%ecard_sender_name%></a></strong></td>
                                                            </tr>
                                                            <tr>
                                                                <td style="height: 10px;"></td>
                                                            </tr>
                                                            <tr style="height: 90%;">
                                                                <td style="vertical-align: middle; height: 90%;">
                                                                    <div align="center" id="Message">
                                                                        <div style="vertical-align: middle;"><%ecard_message%></div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <td style="width: 8px; vertical-align: bottom; min-height: 300px;">
                                                    <a href="https://www.admidio.org"><img src="<%theme_root_path%>/images/ownertext.png" width="30px" style="border: 0;" alt="Admidio"/></a>
                                                </td>
                                                <td style="background: #000000; width: 1px;"><br/></td>
                                                <td style="width: 8px;">&nbsp;</td>
                                                <td style="vertical-align: top; height: 100%;">
                                                    <table border="0" cellpadding="0" cellspacing="2" style="height: 100%;">
                                                        <tbody>
                                                            <tr style="vertical-align: top; margin: 5px;">
                                                                <td>
                                                                    <div align="right">
                                                                        <img src="<%theme_root_path%>/images/stamp.png" alt="stamp" style="border: 0;" height="129" width="150"/>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="height: 5px;"></td>
                                                            </tr>
                                                            <tr style="height: 90%; vertical-align: middle;">
                                                                <td style="height: 90%; vertical-align: middle; padding: 5px; width: 300px;">
                                                                    <table cellpadding="0" cellspacing="0" rules="none" border="0" width="300px">
                                                                        <tr>
                                                                            <td><strong><%ecard_to_string%>:</strong></td>
                                                                            <td style="padding-left: 5px;"><%ecard_reciepient_name%></td>
                                                                        </tr>
                                                                        <tr style="height: 1px;">
                                                                            <td><hr style="border: 1px solid #000000;"/></td>
                                                                            <td><hr style="border: 1px solid #000000;"/></td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td><strong><%ecard_email_string%>:</strong></td>
                                                                            <td style="padding-left: 5px;"><%ecard_reciepient_email%></td>
                                                                        </tr>
                                                                        <tr style="height: 1px;">
                                                                            <td><hr style="border: 1px solid #000000;"/></td>
                                                                            <td><hr style="border: 1px solid #000000;"/></td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>

