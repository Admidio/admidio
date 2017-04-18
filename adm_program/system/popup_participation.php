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

// Get the date object
$date = new TableDate($gDb, $getDateId);

// Read participants
if($getDateId > 0)
{
    $participants = new Participants($gDb, $date->getValue('dat_rol_id'));
    $participantsArray   = $participants->getParticipantsArray($date->getValue('dat_rol_id'));
}

// Get the fingerprint of calling user. If is not the user itself check the requesting user whether it has the permission to edit the states
if ($gCurrentUser->getValue('usr_id') !== $getUserId)
{
    if ($gCurrentUser->isAdministrator() || $gCurrentUser->isLeaderOfRole($date->getValue('dat_rol_id')))
    {
        $editUserStatus = true;
    }
}

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

$member = new TableMembers($gDb);
$member->readDataByColumns(array('mem_rol_id' => $date->getValue('dat_rol_id'), 'mem_usr_id' => $getUserId));

// Write header with charset utf8
header('Content-type: text/html; charset=utf-8');

// Add javascript
echo'<script>
        $("button[id^=btn_attend_]").click(function() {
                    // Select current form and action attribute
                    var submit_ParticipationForm = $(this).get(0).form;
                    var form_action = $(submit_ParticipationForm).attr("action");

                    // add value 3 to mode attribute in link for participation
                    $(submit_ParticipationForm).attr("action", form_action + 3);
                    submit_ParticipationForm.submit();
                });

                $("button[id^=btn_tentative_]").click(function() {
                    var submit_ParticipationForm = $(this).get(0).form;
                    var form_action = $(submit_ParticipationForm).attr("action");

                    $(submit_ParticipationForm).attr("action", form_action + 7);
                    submit_ParticipationForm.submit();
                });

                $("button[id^=btn_refuse_]").click(function() {
                    var submit_ParticipationForm = $(this).get(0).form;
                    var form_action = $(submit_ParticipationForm).attr("action");

                    $(submit_ParticipationForm).attr("action", form_action + 4);
                    submit_ParticipationForm.submit();
                });
</script>';

// Define form
$participationForm = new HtmlForm('participate_form_'. $getDateId, ''.ADMIDIO_URL.'/adm_program/modules/dates/dates_function.php?dat_id=' . $getDateId. '&amp;usr_id=' .$getUserId . '&amp;mode=', null, array('type' => 'default', 'method' => 'post', 'setFocus' => false));
$participationForm->addHtml('<div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">' .$gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION') . '</h4>
                                    <p>' .$date->getValue('dat_headline'). ': ' .$date->getValue('dat_begin') . ' - ' .$date->getValue('dat_end'). '</p>
                            </div><div class="modal-body">');
$participationForm->addMultilineTextInput('dat_comment', $gL10n->get('SYS_COMMENT'), $member->getValue('mem_comment'), 6, array('class' => 'form-control', 'maxLength' => 1000, 'property' => $disableComments));
$participationForm->addInput('additonal_guests', $gL10n->get('LST_SEAT_AMOUNT'), $member->getValue('mem_count_guests'), array('class' => 'form-control', 'type' => 'number', 'property' => $disableAdditionalGuests));
$participationForm->addHtml('</div><div class="modal-footer">');
$participationForm->openButtonGroup();
$participationForm->addButton('btn_attend_' . $getDateId, $gL10n->get('DAT_ATTEND'), array('icon' => THEME_URL.'/icons/ok.png', 'class' => $disableStatusAttend));
$participationForm->addButton('btn_tentative_' . $getDateId, $gL10n->get('DAT_USER_TENTATIVE'), array('icon' => THEME_URL.'/icons/help_violett.png', 'class' => $disableStatusTentative));
$participationForm->addButton('btn_refuse_' . $getDateId, $gL10n->get('DAT_CANCEL'), array('icon' => THEME_URL.'/icons/no.png'));
$participationForm->closeButtonGroup();
$participationForm->addHtml('</div></div>');
// Outut form
$participationForm->show(true);
