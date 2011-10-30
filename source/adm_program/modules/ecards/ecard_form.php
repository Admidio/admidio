<?php
/******************************************************************************
 * Grußkarte Form
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id:      id des Albums dessen Bilder angezeigt werden sollen
 * photo_nr:    Name des Bildes ohne(.jpg) spaeter -> (admidio/adm_my_files/photos/<* Album *>/$_GET['photo'].jpg)
 * usr_id:      Die Benutzer id an dem die Grußkarte gesendet werden soll
 *
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('ecard_function.php');

if ($gPreferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', null, true);

// Initialisierung lokaler Variablen
$funcClass 	 = new FunctionClass($gL10n);
$font_sizes  = array ('9','10','11','12','13','14','15','16','17','18','20','22','24','30');
$font_colors = $funcClass->getElementsFromFile('../../system/schriftfarben.txt');
$fonts       = $funcClass->getElementsFromFile('../../system/schriftarten.txt');
$templates   = $funcClass->getfilenames(THEME_SERVER_PATH. '/ecard_templates/');
$template    = THEME_SERVER_PATH. '/ecard_templates/';

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $gDb;
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photo_album = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photo_album->readData($getPhotoId);
    }

    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && $photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}  

if ($gValidLogin && strlen($gCurrentUser->getValue('EMAIL')) == 0)
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">', '</a>'));
}

if ($getUserId > 0)
{
    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $getUserId);

    // darf auf die User-Id zugegriffen werden
    if((  $gCurrentUser->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0 )
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
}

// ruf die Funktion auf die alle Post und Get Variablen parsed
$funcClass->getVars();

/*********************HTML_TEIL*******************************/

$javascript = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/modules/ecards/ecard.js" ></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/form.js" ></script>
    <script type="text/javascript">
    <!--
            var ecardJS = new ecardJSClass();
            ecardJS.max_recipients 			= '.$gPreferences['ecard_cc_recipients'].';
            ecardJS.max_ecardTextLength		= '.$gPreferences['ecard_text_length'].';
            ecardJS.ecardSend_Text			= \''.$gL10n->get("ECA_GREETING_CARD_SEND").'\';
            ecardJS.currentURL 				= \''.CURRENT_URL.'\';
            ecardJS.errMsg_Start_Text 		= \''.str_replace("<br />",'\n',$gL10n->get("ECA_INPUT_INCORRECT")).'\n\n\';
            ecardJS.nameOfSender_Text 		= \''.$gL10n->get("ECA_NAME_OF_SENDER").'\';
            ecardJS.emailOfSender_Text		= \''.$gL10n->get("ECA_EMAIL_OF_SENDER").'\';
            ecardJS.nameOfRecipient_Text	= \''.$gL10n->get("ECA_NAME_OF_RECIPIENT", $var1="[VAR1]").'\';
            ecardJS.emailOfRecipient_Text	= \''.$gL10n->get("ECA_EMAIL_OF_RECIPIENT", $var1="[VAR1]").'\';
            ecardJS.message_Text			= \''.$gL10n->get("ECA_THE_MESSAGE").'\';
            ecardJS.recipient_Text			= \''.$gL10n->get("SYS_RECIPIENT").'\';
            ecardJS.recipientName_Text		= \''.$gL10n->get("ECA_RECIPIENT_NAME").'\';
            ecardJS.recipientEmail_Text		= \''.$gL10n->get("ECA_RECIPIENT_EMAIL").'\';
            ecardJS.errMsg_End_Text			= \''.str_replace("<br />",'\n',$gL10n->get("ECA_FILL_INPUTS")).'\';
            ecardJS.ecardPreview_Text		= \''.$gL10n->get("ECA_GREETING_CARD_PREVIEW").'\';
            ecardJS.emailLookInvalid_Text	= \''.$gL10n->get("ECA_EMAIL_LOOKS_INVALID").'\';
            ecardJS.contentIsLoading_Text	= \''.$gL10n->get("ECA_CONTENT_LOADING").'\';
            ecardJS.ajaxExecution_ErrorText = \''.str_replace("<br />",'\n',$gL10n->get("SYS_AJAX_REQUEST_ERROR", $var1="[ERROR]")).'\';
            ecardJS.moreRecipients_Text		= \''.$gL10n->get("ECA_MORE_RECIPIENTS").'\';
            ecardJS.noMoreRecipients_Text	= \''.$gL10n->get("ECA_NO_MORE_RECIPIENTS").'\';
            ecardJS.blendInSettings_Text	= \''.$gL10n->get("ECA_BLEND_IN_SETTINGS").'\';
            ecardJS.blendOutSettings_Text	= \''.$gL10n->get("ECA_BLEND_OUT_SETTINGS").'\';
            ecardJS.internalRecipient_Text	= \''.$gL10n->get("ECA_INTERNAL_RECIPIENT").'\';
            ecardJS.messageTooLong			= \''.$gL10n->get("ECA_MESSAGE_TOO_LONG",$var1="[MAX]").'\';
            ecardJS.loading_Text			= \''.$gL10n->get("SYS_LOADING").'\';
            ecardJS.send_Text				= \''.$gL10n->get("SYS_SEND").'\';
            
            $(document).ready(function() {
                $("a[rel=\'colorboxImage\']").colorbox({photo:true});
                ecardJS.getMenu();
            });
    -->
    </script>';

if ($gPreferences['enable_bbcode'] == 1)
{
    $javascript .= getBBcodeJS('Nachricht');
}
// Html-Kopf ausgeben
$gLayout['title'] = $gL10n->get("ECA_GREETING_CARD_EDIT");
$gLayout['header'] = $javascript;

require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<div class="formLayout">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
        <noscript>
            <div style="text-align: center;">
                <div style="background-image: url(\''.THEME_PATH.'/images/error.png\');
                            background-repeat: no-repeat;
                            background-position: 5px 5px;
                            border:1px solid #ccc;
                            padding:5px;
                            background-color: #FFFFE0;
                            padding-left: 28px;
                            text-align:left;">
                 '.$gL10n->get("ECA_NEED_JAVASCRIPT").'
                 </div>
            </div>
        </noscript>

        <a rel="colorboxImage" href="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'"><img 
            src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'].'" 
            class="imageFrame" alt="'.$gL10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'"  title="'.$gL10n->get("ECA_VIEW_PICTURE_FULL_SIZED").'" />
        </a>

      <form id="ecard_form" action="javascript:ecardJS.makePreview();" method="post">
        <input type="hidden" name="ecard[image_name]" value="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$gPreferences['ecard_view_width'].'&amp;max_height='.$gPreferences['ecard_view_height'].'" />
        <input type="hidden" name="ecard[image_serverPath]" value="'.SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id').'/'.$getPhotoNr.'.jpg" />
        <input type="hidden" name="submit_action" value="" />
        <ul class="formFieldList">
        <li>
            <hr />
        </li>
        <li>
            <dl>
                <dt>
                    <label>'.$gL10n->get("SYS_TO").':</label>
                    ';
                    if($gPreferences['enable_ecard_cc_recipients'])
                    {
                        echo '<div id="getmoreRecipient" style="padding-top:20px; height:1px;">
                        <a href="javascript:ecardJS.showHideMoreRecipient(\'moreRecipient\',\'getmoreRecipient\');">'.$gL10n->get("ECA_MORE_RECIPIENTS").'</a>
                        </div>';
                    }
                   echo'
                </dt>
                <dd id="Menue" style="height:49px; width:370px;">';
                    if ($getUserId > 0)
                    {
                        // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
                        echo '<div id="extern">
                                <input type="text" readonly="readonly" name="ecard[name_recipient]" style="display: none;" value="'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'">
                                <input type="text" disabled="disabled" style="margin-bottom:3px; width: 200px;" maxlength="50" value="'.$user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME').'"><span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                                <input type="text" readonly="readonly" name="ecard[email_recipient]" style="display: none;" value="'.$user->getValue('EMAIL').'">
                                <input type="text" disabled="disabled" style="width: 90%;" maxlength="50" value="'.$user->getValue('EMAIL').'"><span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                             </div>';

                    }
                    else
                    {
                       echo '<div id="externSwitch" style="float:right; padding-left:5px; position:relative;"></div>
                             <div id="basedropdownmenu" style="display:block; padding-bottom:3px;"></div>
                             <div id="dropdownmenu" style="display:block;"></div>
                             <div id="extern">
                                <input type="hidden" name="ecard[email_recipient]" value="" />
                                <input type="hidden" name="ecard[name_recipient]"  value="" />
                             </div>
                             <div id="wrong" style="width:300px;background-image: url(\''.THEME_PATH.'/icons/error.png\'); background-repeat: no-repeat;background-position: 5px 5px;margin-top:5px; border:1px solid #ccc;padding:5px;background-color: #FFFFE0; padding-left: 28px;display:none;"></div>';
                    }
                    echo '
                </dd>
            </dl>
        </li>
        <li>
            <div id="moreRecipient" style="display:none;">
            <hr />
                <dl>
                    <dt>'.$gL10n->get("ECA_MORE_RECIPIENTS").':</dt>
                    <dd>
                        <table summary="TableccContainer" border="0" >
                            <tr>
                                <td style="width:150px; text-align: left;">'.$gL10n->get("SYS_NAME").'</td>
                                <td style="width:200px; padding-left:14px; text-align: left;">'.$gL10n->get("SYS_EMAIL").'</td>
                            </tr>
                        </table>
                        <div id="ccrecipientContainer" style="width:490px; border:0px; text-align: left;"></div>
                        <table summary="TableCCRecipientSettings" border="0">
                                <tr>
                                    <td style="text-align: left;"><span class="iconTextLink"><a href="javascript:ecardJS.addRecipient()"><img src="'. THEME_PATH.'/icons/add.png" alt="'.$gL10n->get("SYS_ADD_RECIPIENTS").'" /></a><a href="javascript:ecardJS.addRecipient()">'.$gL10n->get("SYS_ADD_RECIPIENTS").'</a></span></td>
                                </tr>
                        </table>
                    </dd>
                </dl>
            </div>
        </li>
        <li>
            <hr />
        </li>
        <li>
            <dl>
                <dt><label>'.$gL10n->get('SYS_SENDER').':</label></dt>
                <dd><input type="text" disabled="disabled" maxlength="50" style="width: 90%;" value="'.$gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME').'" /></dd>
            </dl>
        </li>
         <li>
            <dl>
                <dt><label>'.$gL10n->get('SYS_EMAIL').':</label></dt>
                <dd><input type="text" disabled="disabled" maxlength="50" style="width: 90%;"  value="'.$gCurrentUser->getValue('EMAIL').'" /></dd>
            </dl>
        </li>
        <li>
            <hr />
        </li>'; 
        if ($gPreferences['enable_bbcode'] == 1)
        {
            printBBcodeIcons();
        }                
        echo '
        <li>
            <dl>
                <dt>
                    <label>'.$gL10n->get("SYS_MESSAGE").':</label>';
                    if($gPreferences['enable_ecard_text_length'])
                    {
                        echo '<div style="width:125px; padding:5px 0px 5px 35px; background-image: url(\''.THEME_PATH.'/icons/warning.png\'); background-repeat: no-repeat;background-position: 5px 5px;border:1px solid #ccc; margin:70px 0px 28px 0px;  background-color: #FFFFE0;">'.$gL10n->get("ECA_STILL_XCHARS_AVAILABLE",$var1="&nbsp;<div id=\"counter\" style=\"border:0px; display:inline;\"><b>".$gPreferences['ecard_text_length']."</b></div>&nbsp;").'</div>';
                    }
                    echo '<div id="getmoreSettings" style="';
                    if($gPreferences['enable_ecard_text_length'])
                    {
                        echo 'padding-top:28px;';
                    }
                    else
                    {
                        echo 'padding-top:155px;';
                    }
                    echo '  height:1px;">
                        <a href="javascript:ecardJS.showHideMoreSettings(\'moreSettings\',\'getmoreSettings\');">'.$gL10n->get("ECA_BLEND_IN_SETTINGS").'</a>
                    </div>
                </dt>
                <dd>
                    <textarea id="Nachricht" style="width: 90%; height: 180px; overflow:auto; font:'.$gPreferences['ecard_text_size'].'px '.$gPreferences['ecard_text_font'].'; color:'.$gPreferences['ecard_text_color'].'; wrap:virtual;" rows="10" cols="45" name="ecard[message]"';
                    if($gPreferences['enable_ecard_text_length'])
                    {
                    echo' onfocus="javascript:ecardJS.countMax();" onclick="javascript:ecardJS.countMax();" onchange="javascript:ecardJS.countMax();" onkeydown="javascript:ecardJS.countMax();" onkeyup="javascript:ecardJS.countMax();" onkeypress="javascript:ecardJS.countMax();"';
                    }
                    echo' >';
                    if (! empty($ecard["message"]))
                    {
                        echo ''.$ecard["message"].'';
                    }
               echo'</textarea>
                    <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
               </dd>
            </dl>
        </li>
        <li>
            <div id="moreSettings" style="display:none;">
            <hr />
            <dl>
                <dt>
                    <label>'.$gL10n->get("SYS_SETTINGS").':</label>
                </dt>
                <dd>';
                    $first_value_array = array();
                    echo'<table cellpadding="5" cellspacing="0" summary="Einstellungen" style="width:100%;"  border="0px">
                        <tr>
                          <td>'.$gL10n->get("ECA_TEMPLATE").':</td>
                          <td>'.$gL10n->get("SYS_FONT").':</td>
                          <td>'.$gL10n->get("SYS_FONT_SIZE").':</td>
                        </tr>
                        <tr>
                            <td>';
                                array_push($first_value_array,array($funcClass->getMenueSettings($templates,"ecard[template_name]",$gPreferences['ecard_template'],"120","false"),"ecard[template_name]"));
                            echo '</td>
                            <td>';
                                array_push($first_value_array,array($funcClass->getMenueSettings($fonts,"ecard[schriftart_name]",$gPreferences['ecard_text_font'],"120","true"),"ecard[schriftart_name]"));
                            echo '</td>
                            <td>';
                                array_push($first_value_array,array($funcClass->getMenueSettings($font_sizes,"ecard[schrift_size]",$gPreferences['ecard_text_size'],"50","false"),"ecard[schrift_size]"));
                            echo  '</td>
                        </tr>
                        <tr>
                          <td>'.$gL10n->get("SYS_FONT_COLOR").':</td>
                          <td style="padding-left:40px;">'.$gL10n->get("SYS_FONT_STYLE").':</td>
                          <td></td>
                        </tr>
                        <tr>
                            <td>';
                                array_push($first_value_array,array($funcClass->getColorSettings($font_colors,"ecard[schrift_farbe]","8",$gPreferences['ecard_text_color']),"ecard[schrift_farbe]"));
                            echo '</td>
                            <td colspan="2" style="padding-left:40px;">
                                <b>'.$gL10n->get("SYS_BOLD").': </b><input name="Bold" value="bold" onclick="javascript: ecardJS.getSetting(\'ecard[schrift_style_bold]\',this.value);" type="checkbox" />
                                <i>'.$gL10n->get("SYS_ITALIC").': </i><input name="Italic" value="italic" onclick="javascript: ecardJS.getSetting(\'ecard[schrift_style_italic]\',this.value);" type="checkbox" />
                            </td>
                        </tr>
                    </table>';
                    $funcClass->getFirstSettings($first_value_array);
                    echo '<input type="hidden" name="ecard[schrift_style_bold]" value="" />
                    <input type="hidden" name="ecard[schrift_style_italic]" value="" />
                </dd>
            </dl>
            </div>
        </li>
    </ul>
    <hr />
    <div class="formSubmit">
        <button id="btnPreview" onclick="javascript:ecardJS.makePreview();" type="button"><img 
            src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get("SYS_PREVIEW").'" />&nbsp;'.$gL10n->get("SYS_PREVIEW").'</button>&nbsp;&nbsp;&nbsp;&nbsp;
        <button id="ecardSubmit" onclick="javascript:ecardJS.sendEcard();" type="button"><img 
            src="'. THEME_PATH. '/icons/email.png" alt="'.$gL10n->get("SYS_SEND").'" />&nbsp;'.$gL10n->get("SYS_SEND").'</button>
    </div>
</form></div></div>';
/************************Buttons********************************/
//Uebersicht
if($photo_album->getValue('pho_id') > 0)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img
                src="'.THEME_PATH.'/icons/back.png" alt="'.$gL10n->get("SYS_BACK").'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get("SYS_BACK").'</a>
            </span>
        </li>
    </ul>';
}

/***************************Seitenende***************************/
require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>