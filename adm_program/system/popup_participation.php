<?php
/**
 ***********************************************************************************************
 * Particpation modal window for events
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * $getDateId - ID of the date
 * $getUserId - ID of the User whose participation detail shall be set or changed
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

// Initialize and check the parameters
$getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'int');
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'int', array('defaultValue' => $gCurrentUser->getValue('usr_id')));

// Initialize local variables
$disableAdditionalGuests    = 4;
$disableComments            = 4;
$disableStatusAttend        = '';
$disableStatusTentative     = '';
$editUserStatus             = false;

// Get the fingerprint of calling user. If is not the user itself check the requesting user whether it has the permission to edit the states
if ($gCurrentUser->getValue('usr_id') !== $getUserId)
{
    if ($gCurrentUser->isAdministrator() || $gCurrentUser->isLeaderOfRole($getDateId))
    {
        $editUserStatus = true;
    }
}
// Get the date object
$date = new TableDate($gDb, $getDateId);

// If extended options for participation are allowed then show in form
if ((int) $date->getValue('dat_allow_comments') === 1 || (int) $date->getValue('dat_additional_guests') === 1)
{
    if ((int) $date->getValue('dat_allow_comments') === 1)
    {
        $disableComments = '';
    }
    if ((int) $date->getValue('dat_additional_guests') === 1)
    {
        $disableAdditionalGuests = '';
    }
}

// Output form
header('Content-type: text/html; charset=utf-8');

$participationForm = new HtmlForm('participate_form_'. $getDateId, 'dates_function.php?dat_id=' . $getDateId. '&amp;mode=', null, array('type' => 'default', 'method' => 'post', 'setFocus' => false));
$participationForm->addHtml('<div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">' .$gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION') . '</h4>
                                    <p>' .$date->getValue('dat_headline'). ': ' .$date->getValue('dat_begin') . ' - ' .$date->getValue('dat_end'). '</p>
                            </div><div class="modal-body">');
$participationForm->addMultilineTextInput('dat_comment', $gL10n->get('SYS_COMMENT'), $date->getValue('comment'), 6, array('class' => 'form-control', 'maxLength' => 1000, 'property' => $disableComments));
$participationForm->addInput('additonal_guests', $gL10n->get('LST_SEAT_AMOUNT'), $date->getValue('additional_guests'), array('class' => 'form-control', 'type' => 'number', 'property' => $disableAdditionalGuests));
$participationForm->addHtml('</div><div class="modal-footer">');
$participationForm->openButtonGroup();
$participationForm->addButton('btn_attend_' . $getDateId, $gL10n->get('DAT_ATTEND'), array('icon' => THEME_URL.'/icons/ok.png', 'class' => $disableStatusAttend));
$participationForm->addButton('btn_tentative_' . $getDateId, $gL10n->get('DAT_USER_TENTATIVE'), array('icon' => THEME_URL.'/icons/help_violett.png', 'class' => $disableStatusTentative));
$participationForm->addButton('btn_refuse_' . $getDateId, $gL10n->get('DAT_CANCEL'), array('icon' => THEME_URL.'/icons/no.png'));
$participationForm->closeButtonGroup();
$participationForm->addHtml('</div></div>');
$participationForm->show(true);
