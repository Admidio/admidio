<?php
/**
 ***********************************************************************************************
 * Form for sending ecards
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * pho_id:      Id of photo album whose image you want to send
 * photo_nr:    Number of the photo of the chosen album
 * usr_id:      (optional) Id of the user who should receive the ecard
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/ecard_function.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id',    'int', array('requireValue' => true));
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id',    'int');
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr',  'int', array('requireValue' => true));
$showPage   = admFuncVariableIsValid($_GET, 'show_page', 'int', array('defaultValue' => 1));

// Initialisierung lokaler Variablen
$funcClass = new FunctionClass($gL10n);
$templates = $funcClass->getFileNames(THEME_PATH. '/ecard_templates/');
$template  = THEME_PATH. '/ecard_templates/';
$headline  = $gL10n->get('ECA_GREETING_CARD_EDIT');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('enable_ecard_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// URL auf Navigationstack ablegen
$gNavigation->addUrl(CURRENT_URL, $headline);

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_id') === $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photoAlbum = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photoAlbum->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] = $photoAlbum;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && (int) $photoAlbum->getValue('pho_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '')
{
    // der eingeloggte Benutzer hat in seinem Profil keine gueltige Mailadresse hinterlegt,
    // die als Absender genutzt werden kann...
    $gMessage->show($gL10n->get('SYS_CURRENT_USER_NO_EMAIL', array('<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php">', '</a>')));
    // => EXIT
}

if ($getUserId > 0)
{
    // usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $getUserId);

    // darf auf die User-Id zugegriffen werden
    if((!$gCurrentUser->editUsers() && !isMember($user->getValue('usr_id'))) || strlen($user->getValue('usr_id')) === 0)
    {
        $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
        // => EXIT
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'))));
        // => EXIT
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
    $template   = $gSettingsManager->getString('ecard_template');
    $recipients = null;
    $message    = '';
}

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/lightbox/dist/ekko-lightbox.min.js');

$page->addJavascript('
    $(document).delegate("*[data-toggle=\"lightbox\"]", "click", function(event) {
        event.preventDefault();
        $(this).ekkoLightbox();
    });

    $("#admidio_modal").on("show.bs.modal", function() {
        $(this).find(".modal-dialog").css({width: "800px"});
    });

    $("#btn_ecard_preview").click(function(event) {
        event.preventDefault();
        $("#ecard_form input[id=\'submit_action\']").val("preview");
        $("#ecard_form textarea[name=\'ecard_message\']").text(CKEDITOR.instances.ecard_message.getData());

        $.post({ // create an AJAX call...
            data: $("#ecard_form").serialize(), // get the form data
            url: "ecard_preview.php", // the file to call
            success: function(response) { // on success..
                $(".modal-content").html(response);
                $("#admidio_modal").modal();
            }
        });

        return false;
    });',
    true
);

// add back link to module menu
$ecardMenu = $page->getMenu();
$ecardMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of announcements
    $ecardMenu->addItem(
        'menu_item_preferences', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php', array('show_option' => 'ecards')),
        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right'
    );
}

// show form
$form = new HtmlForm('ecard_form', 'ecard_send.php', $page);
$form->addInput('submit_action', '', '', array('type' => 'hidden'));
$form->addInput('photo_id', '', $getPhotoId, array('type' => 'hidden'));
$form->addInput('photo_nr', '', $getPhotoNr, array('type' => 'hidden'));

$form->openGroupBox('gb_layout', $gL10n->get('ECA_LAYOUT'));
$form->addCustomContent($gL10n->get('SYS_PHOTO'), '
    <a data-toggle="lightbox" data-type="image" data-title="'.$gL10n->get('SYS_PREVIEW').'"
        href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $getPhotoId, 'photo_nr' => $getPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height'))).'"><img
        src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $getPhotoId, 'photo_nr' => $getPhotoNr, 'max_width' => $gSettingsManager->getInt('ecard_thumbs_scale'), 'max_height' => $gSettingsManager->getInt('ecard_thumbs_scale'))).'"
        class="imageFrame" alt="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'"  title="'.$gL10n->get('ECA_VIEW_PICTURE_FULL_SIZED').'" />
    </a>');
$templates = admFuncGetDirectoryEntries(THEME_PATH.'/ecard_templates');
if (!is_array($templates))
{
    $gMessage->show($gL10n->get('ECA_TEMPLATE_FOLDER_OPEN'));
    // => EXIT
}
foreach($templates as &$templateName)
{
    $templateName = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
}
unset($templateName);
$form->addSelectBox(
    'ecard_template', $gL10n->get('ECA_TEMPLATE'), $templates,
    array('defaultValue' => $template, 'property' => HtmlForm::FIELD_REQUIRED, 'showContextDependentFirstEntry' => false)
);
$form->closeGroupBox();
$form->openGroupBox('gb_contact_details', $gL10n->get('SYS_CONTACT_DETAILS'));

// create list with all possible recipients
$list = array();

// list all roles where login users could send mails to
$arrayMailRoles = $gCurrentUser->getAllMailRoles();

$sql = 'SELECT rol_id, rol_name
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_id IN ('.Database::getQmForValues($arrayMailRoles).')
           AND cat_name_intern <> \'EVENTS\'
      ORDER BY rol_name';
$statement = $gDb->queryPrepared($sql, $arrayMailRoles);

while($row = $statement->fetch())
{
    $list[] = array('groupID: '.$row['rol_id'], $row['rol_name'], $gL10n->get('SYS_ROLES'));
}

// select all users
$arrayRoles = array_merge($arrayMailRoles, $gCurrentUser->getAllVisibleRoles());
$arrayUniqueRoles = array_unique($arrayRoles);

$sql = 'SELECT usr_id, first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email
          FROM '.TBL_MEMBERS.'
    INNER JOIN '.TBL_USERS.'
            ON usr_id = mem_usr_id
    INNER JOIN '.TBL_USER_DATA.' AS email
            ON email.usd_usr_id = usr_id
           AND LENGTH(email.usd_value) > 0
    INNER JOIN '.TBL_USER_FIELDS.' AS field
            ON field.usf_id = email.usd_usf_id
           AND field.usf_type = \'EMAIL\'
     LEFT JOIN '.TBL_USER_DATA.' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
     LEFT JOIN '.TBL_USER_DATA.' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
         WHERE usr_valid  = 1
           AND mem_begin <= ? -- DATE_NOW
           AND mem_end    > ? -- DATE_NOW
           AND mem_rol_id IN ('.implode(',', $arrayUniqueRoles).')
      GROUP BY usr_id, first_name.usd_value, last_name.usd_value, email.usd_value
      ORDER BY last_name, first_name';
$queryParams = array(
    $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
    $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
    DATE_NOW,
    DATE_NOW
);
$statement = $gDb->queryPrepared($sql, $queryParams);

while ($row = $statement->fetch())
{
    $list[] = array($row['usr_id'], $row['last_name']. ', '.$row['first_name']. ' ('.$row['email'].')', $gL10n->get('SYS_MEMBERS'));
}

$form->addSelectBox(
    'ecard_recipients', $gL10n->get('SYS_TO'), $list,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $recipients, 'multiselect' => true)
);
$form->addLine();
$form->addInput(
    'name_from', $gL10n->get('MAI_YOUR_NAME'), $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME'),
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_DISABLED)
);
$form->addInput(
    'mail_from', $gL10n->get('MAI_YOUR_EMAIL'), $gCurrentUser->getValue('EMAIL'),
    array('maxLength' => 50, 'property' => HtmlForm::FIELD_DISABLED)
);
$form->closeGroupBox();
$form->openGroupBox('gb_message', $gL10n->get('SYS_MESSAGE'), 'admidio-panel-editor');
$form->addEditor(
    'ecard_message', '', $message,
    array('property' => HtmlForm::FIELD_REQUIRED, 'toolbar' => 'AdmidioGuestbook')
);
$form->closeGroupBox();
$form->openButtonGroup();
$form->addButton('btn_ecard_preview', $gL10n->get('SYS_PREVIEW'), array('icon' => THEME_URL. '/icons/eye.png'));
$form->addSubmitButton('btn_ecard_submit', $gL10n->get('SYS_SEND'), array('icon' => THEME_URL. '/icons/email.png'));
$form->closeButtonGroup();

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
