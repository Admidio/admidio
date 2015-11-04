<?php
/******************************************************************************
 * Preview of ecard
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

// preview will be called before form is send, so there are now POST parameters available
// then show nothing. Second call is with POST parameters then show preview
require_once('../../system/common.php');
require_once('ecard_function.php');

//$gMessage->showTextOnly(true);
$gMessage->showThemeBody(false);
$gMessage->showInModaleWindow();

if(strlen($_POST['ecard_template']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ECA_TEMPLATE')));
}

// Initialize and check the parameters
$ecardMessage     = '';
$postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
$postPhotoId      = admFuncVariableIsValid($_POST, 'photo_id', 'numeric', array('requireValue' => true));
$postPhotoNr      = admFuncVariableIsValid($_POST, 'photo_nr', 'numeric', array('requireValue' => true));
$nameRecipient    = admFuncVariableIsValid($_POST, 'name_recipient', 'string');
$emailRecipient   = admFuncVariableIsValid($_POST, 'email_recipient', 'string');

if(isset($_POST['ecard_message']))
{
    $ecardMessage = $_POST['ecard_message'];
}

$imageUrl = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$postPhotoId.'&photo_nr='.$postPhotoNr.'&max_width='.$gPreferences['ecard_thumbs_scale'].'&max_height='.$gPreferences['ecard_thumbs_scale'];

$funcClass = new FunctionClass($gL10n);

// read content of template file
$ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName, THEME_SERVER_PATH. '/ecard_templates/');

if($ecardDataToParse === '')
{
    $gMessage->show($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));
}

echo '
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h4>
</div>
<div class="modal-body" id="preview_content">';

    // show output of parsed template
    echo $funcClass->parseEcardTemplate($imageUrl, $ecardMessage, $ecardDataToParse, $nameRecipient, $emailRecipient);

echo '</div>';
