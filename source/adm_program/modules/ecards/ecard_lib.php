<?php
/*#################################################################
# IBPS E-C@ard    Version 1.01               					  
# Copyright 2002 IBPS Friedrichs     info@ibps-friedrichs.de    
##################################################################
# Filename: ecard_lib.php                                        
# Letzte Änderung: 28.01.2003                                   				  
# Sprachversion: deutsch (andere noch nicht verfügbar)          				 
##################################################################*/

include('htmlMimeMail.php');

function getPostGetVars() {
  global $HTTP_POST_VARS,$HTTP_GET_VARS;
  foreach ($HTTP_POST_VARS as $key => $value) {
    global $$key;
    $$key = $value;
  }
  foreach ($HTTP_GET_VARS as $key => $value) {
    global $$key;
    $$key = $value;
  }
}

  function get_ecard_template($template_name,$tmpl_folder) {
    $error = false;
    $file_data = "";
    $fpread = @fopen($tmpl_folder.$template_name, 'r');
    if (!$fpread) {
      $error = true;
    } else {
        while(! feof($fpread) ) {
          $file_data .= fgets($fpread, 4096);
        }
        fclose($fpread);
      }
    return array($error,$file_data);
  }

   function parse_ecard_template($ecard,$ecard_data,$root_path,$usr_id,$propotional_width,$propotional_height) 
   {   
	if(trim($ecard["name_recepient"]) == "")
	{
	  $ecard["name_recepient"] = "< Empf&auml;nger Name >";
	}
	if( $ecard["email_recepient"] == "")
	{
	  $ecard["email_recepient"] = "< Empf&auml;nger E-Mail >";
	}
	if($ecard["message"] == "")
	{
	  $ecard["message"] = "< Deine Nachricht >";
	}
	$ecard_data = preg_replace ("/<%g_root_path%>/", $root_path, $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_sender_id%>/", $usr_id, $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_sender_email%>/", $ecard["email_sender"], $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_sender_name%>/", $ecard["name_sender"], $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_image_width%>/", $propotional_width, $ecard_data);
	$ecard_data = preg_replace ("/<%ecard_image_height%>/", $propotional_height, $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_image_name%>/", $ecard["image_name"], $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_message%>/", preg_replace ("/\r?\n/", "<BR>\n", htmlspecialchars($ecard["message"])), $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_reciepient_email%>/", $ecard["email_recepient"], $ecard_data);
    $ecard_data = preg_replace ("/<%ecard_reciepient_name%>/", $ecard["name_recepient"], $ecard_data);
    return $ecard_data;
  }

  function sendEcard($ecard,$ecard_HTML_data,$ecard_PLAIN_data) {
    $ecard_mail = new htmlMimeMail();
    $ecard_image = $ecard_mail->getFile($ecard["image_name"]);
    if (preg_match_all("/(<IMG.*SRC=\")(.*)(\".*>)/Uim", $ecard_HTML_data, $matchArray)) {
      for ($i=0; $i<count($matchArray[0]); ++$i) {
        $ecard_image = $ecard_mail->getFile($matchArray[2][$i]);
      }
    }
    $ecard_mail->setHtml($ecard_HTML_data, $ecard_PLAIN_data,'./');
    $ecard_mail->setFrom($ecard["name_sender"].'<'.$ecard["email_sender"].'>');
	$ecard_mail->setSubject('E-C@rd von '.$ecard["name_sender"]);
    $ecard_mail->setReturnPath($ecard["email_sender"]);
	$result = $ecard_mail->send(array($ecard["email_recepient"]));
    return $result;
  }
  
  function check_email($email) {
    if (preg_match ("/(@.*@)|(\.\.)|(@\.)|(\.@)|(^\.)/", $email) || !preg_match ("/^.+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/", $email)) {
      $mail_ok = false;
    } else {
        $mail_ok = true;
      }
    return $mail_ok;
  }  # End of - sub check_email -
?>