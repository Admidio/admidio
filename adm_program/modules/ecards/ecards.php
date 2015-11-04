<?php
/******************************************************************************
 * Form for sending ecards
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id:      Id of photo album whose image you want to send
 * photo_nr:    Number of the photo of the choosen album
 * usr_id:      (optional) Id of the user who should receive the ecard
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('ecard_function.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', array('requireValue' => true));
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id', 'numeric');
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', array('requireValue' => true));
$showPage    = admFuncVariableIsValid($_GET, 'show_page', 'numeric', array('defaultValue' => 1));

// Initialisierung lokaler Variablen
$funcClass     = new FunctionClass($gL10n);
$templates   = $funcClass->getFileNames(THEME_SERVER_PATH. '/ecard_templates/');
$template    = THEME_SERVER_PATH. '/ecard_templates/';
$headline    = $gL10n->get('ECA_GREETING_CARD_EDIT');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//URL auf Navigationstack ablegen
$gNavigation->addUrl(CURRENT_URL, $headline);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->setDatabase($gDb);
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photo_album = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photo_album->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] = $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && $photo_album->getValue('pho_org_id') != $gCurrentOrganization->getValue('org_id'))
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
    if(($gCurrentUser->editUsers() == false
       && isMember($user->getValue('usr_id')) == false)
    || strlen($user->getValue('usr_id')) == 0)
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
}

if(isset($_SESSION['ecard_request']))
{
    // if user is returned to this form after he submit it,
    // then try to restore all values that he has entered before
    $template   = $_SESSION['ecard_request']['ecard_template'];
    $recipients = $_SESSION['ecard_request']['ecard_recipients'];
    $message    = $_SESSION['ecard_request']['ecard_message'];
}
else
{
    $template   = $gPreferences['ecard_template'];
    $recipients = null;
    $message    = '';
}

// create html page object
$page = new HtmlPage($headline);

$page->addJavascriptFile($g_root_path.'/adm_program/libs/lightbox/ekko-lightbox.min.js');

$page->addJavascript('
    $(document).delegate("*[data-toggle=\"lightbox\"]", "click", function(event) { event.preventDefault(); $(this).ekkoLightbox(); });

    $("#admidio_modal").on("show.bs.modal", function () {
        $(this).find(".modal-dialog").css({width: "800px"});
    });

    $("#btn_ecard_preview").click(function(event){
        event.preventDefault();
        $("#ecard_form input[id=\'submit_action\']").val("preview");
        $("#ecard_form textarea[name=\'ecard_message\']").text( CKEDITOR.instances.ecard_message.getData() );

        $.ajax({ // create an AJAX call...
            data: $("#ecard_form").serialize(), // get the form data
            type: "POST", // GET or POST
            url: "ecard_preview.php", // the file to call
            success: function(response) { // on success..
                $(".modal-content").html(response);
                $("#admidio_modal").modal();
            }
        });

        return false;
    }); ', true);

// add back link to module menu
$ecardMenu = $page->getMenu();
$ecardMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

if($gCurrentUser->isWebmaster())
{
    // show link to system preferences of announcements
    $ecardMenu->addItem('menu_item_preferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=ecards',
                                $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

// show form
$form = new HtmlForm('ecard_form', 'ecard_send.php', $page);
$form->addInput('submit_action', null, '', array('type' => 'hidden'));
$form->addInput('photo_id', null, $getPhotoId, array('type' => 'hidden'));
$form->addInput('photo_nr', null, $getPhotoNr, array('type' => 'hidden'));

$form->openGroupBox('gb_layout', $gL10n->get('ECA_LAYOUT'));
    $form->addCustomContent($gL10n->get('SYS_PHOTO'), '
        <a data-toggle="lightbox" data-type="image" data-title="'.$gL10n->get('SYS_PREVIEW').'"
            href="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'"><img
            src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['ecard_thumbs_scale'].'&amp;max_height='.$gPreferences['ecard_thumbs_scale'].'"
            class="imageFrame" alt="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'"  title="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'" />
        </a>');
    $templates = admFuncGetDirectoryEntries(THEME_SERVER_PATH.'/ecard_templates');
    foreach($templates as $key => $templateName)
    {
        $templates[$key] = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
    }
    $form->addSelectBox('ecard_template', $gL10n->get('ECA_TEMPLATE'), $templates, array('defaultValue' => $template, 'property' => FIELD_REQUIRED, 'showContextDependentFirstEntry' => false));
$form->closeGroupBox();
$form->openGroupBox('gb_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

    // create list with all possible recipients

    // list all roles where login users could send mails to
    $arrayMailRoles = $gCurrentUser->getAllMailRoles();

    $sql = 'SELECT rol_id, rol_name
              FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE rol_id IN ('.implode(',', $arrayMailRoles).')
               AND rol_cat_id = cat_id
               AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
             ORDER BY rol_name ';

    $result = $gDb->query($sql);
    while($row = $gDb->fetch_array($result))
    {
        $list[] = array('groupID: '.$row['rol_id'], $row['rol_name'], $gL10n->get('SYS_ROLES'));
    }

    // select all users
    $arrayRoles = array_merge($arrayMailRoles, $gCurrentUser->getAllVisibleRoles());
    $arrayUniqueRoles = array_unique($arrayRoles);

    $sql   = 'SELECT usr_id, first_name.usd_value as first_name, last_name.usd_value as last_name,
                     email.usd_value as email
                FROM '. TBL_MEMBERS. ', '. TBL_USERS. '
                JOIN '. TBL_USER_DATA. ' as email
                  ON email.usd_usr_id = usr_id
                 AND LENGTH(email.usd_value) > 0
                JOIN '.TBL_USER_FIELDS.' as field
                  ON field.usf_id = email.usd_usf_id
                 AND field.usf_type = \'EMAIL\'
                LEFT JOIN '. TBL_USER_DATA. ' as last_name
                  ON last_name.usd_usr_id = usr_id
                 AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                LEFT JOIN '. TBL_USER_DATA. ' as first_name
                  ON first_name.usd_usr_id = usr_id
                 AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
               WHERE mem_usr_id  = usr_id
                 AND mem_rol_id IN ('.implode(',', $arrayUniqueRoles).')
                 AND mem_begin <= \''.DATE_NOW.'\'
                 AND mem_end    > \''.DATE_NOW.'\'
                 AND usr_valid   = 1
            GROUP BY usr_id, first_name.usd_value, last_name.usd_value, email.usd_value
            ORDER BY first_name, last_name';
    $result = $gDb->query($sql);

    while ($row = $gDb->fetch_array($result))
    {
        $list[] = array($row['usr_id'], $row['first_name'].' '.$row['last_name']. ' ('.$row['email'].')', $gL10n->get('SYS_MEMBERS'));
    }

    $form->addSelectBox('ecard_recipients', $gL10n->get('SYS_TO'), $list, array('property' => FIELD_REQUIRED,
                        'defaultValue' => $recipients, 'multiselect' => true));
    $form->addLine();
    $form->addInput('name_from', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'), array('maxLength' => 50, 'property' => FIELD_DISABLED));
    $form->addInput('mail_from', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'), array('maxLength' => 50, 'property' => FIELD_DISABLED));
$form->closeGroupBox();
$form->openGroupBox('gb_message', $gL10n->get('SYS_MESSAGE'), 'admidio-panel-editor');
    $form->addEditor('ecard_message', null, $message, array('property' => FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook'));
$form->closeGroupBox();
$form->openButtonGroup();
    $form->addButton('btn_ecard_preview', $gL10n->get('SYS_PREVIEW'), array('icon' => THEME_PATH. '/icons/eye.png'));
    $form->addSubmitButton('btn_ecard_submit', $gL10n->get('SYS_SEND'), array('icon' => THEME_PATH. '/icons/email.png'));
$form->closeButtonGroup();

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
