<?php
/**
 ***********************************************************************************************
 * Form for sending e-cards
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid: UUID of photo album whose image you want to send
 * photo_nr:   Number of the photo of the chosen album
 * user_uuid:  (optional) UUID of the user who should receive the ecard
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\ValueObject\ECard;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // check if the photo module is enabled and eCard is enabled
    if (!$gSettingsManager->getBool('photo_ecard_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid', array('requireValue' => true));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));
    $showPage = admFuncVariableIsValid($_GET, 'show_page', 'int', array('defaultValue' => 1));

    // Initialisierung lokaler Variablen
    $funcClass = new ECard($gL10n);
    $templates = $funcClass->getFileNames(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates');
    $headline = $gL10n->get('SYS_SEND_GREETING_CARD');

    // Drop URL on navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // Create photo album object or read from session
    if (isset($_SESSION['photo_album']) && (int)$_SESSION['photo_album']->getValue('pho_uuid') === $getPhotoUuid) {
        $photoAlbum =& $_SESSION['photo_album'];
    } else {
        $photoAlbum = new Album($gDb);
        $photoAlbum->readDataByUuid($getPhotoUuid);

        $_SESSION['photo_album'] = $photoAlbum;
    }

    // check if user has right to view the album
    if (!$photoAlbum->isVisible()) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if ($gValidLogin && $gCurrentUser->getValue('EMAIL') === '') {
        // the logged-in user has no valid mail address stored in his profile, which can be used as sender
        throw new Exception('SYS_CURRENT_USER_NO_EMAIL', array('<a href="' . ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php">', '</a>'));
    }

    if ($getUserUuid !== '') {
        // UUID was set than read contact data of this user
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);

        // check if the current user has the right communicate with that member
        if ((!$gCurrentUser->isAdministratorUsers() && !isMember((int)$user->getValue('usr_id'))) || strlen($user->getValue('usr_id')) === 0) {
            throw new Exception('SYS_USER_ID_NOT_FOUND');
        }

        // check if the member has a valid email address
        if (!StringUtils::strValidCharacters($user->getValue('EMAIL'), 'email')) {
            throw new Exception('SYS_USER_NO_EMAIL', array($user->getValue('FIRST_NAME') . ' ecards.php' . $user->getValue('LAST_NAME')));
        }
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('adm_ecards', $headline);

    $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/css/lightbox.css');
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/lightbox2/js/lightbox.js');

    $page->addJavascript('
        $("#adm_button_ecard_preview").click(function(event) {
            event.preventDefault();
            $("#adm_ecard_send_form input[id=\'submit_action\']").val("preview");
            $("#adm_ecard_send_form textarea[name=\'ecard_message\']").text(editor.getData());

            $.post({ // create an AJAX call...
                data: $("#adm_ecard_send_form").serialize(), // get the form data
                url: "ecard_preview.php", // the file to call
                success: function(response) { // on success..
                    $(".modal-dialog").attr("class", "modal-dialog modal-lg");
                    $(".modal-content").html(response);
                    var myModal = new bootstrap.Modal($("#adm_modal"), {});
                    myModal.show();
                }
            });

            return false;
        });',
        true
    );

    // show form
    $form = new FormPresenter(
        'adm_ecard_send_form',
        'modules/photos.ecard.send.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/photos/ecard_send.php',
        $page
    );
    $form->addInput('submit_action', '', '', array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('photo_uuid', '', $getPhotoUuid, array('property' => FormPresenter::FIELD_HIDDEN));
    $form->addInput('photo_nr', '', $getPhotoNr, array('property' => FormPresenter::FIELD_HIDDEN));

    $templates = array_keys(FileSystemUtils::getDirectoryContent(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates', false, false, array(FileSystemUtils::CONTENT_TYPE_FILE)));

    if (count($templates) === 0) {
        throw new Exception('SYS_TEMPLATE_FOLDER_OPEN');
    }
    // create new array without file extension in visual value
    $newTemplateArray = array();
    foreach ($templates as $templateName) {
        $newTemplateArray[$templateName] = ucfirst(preg_replace('/[_-]/', ' ', str_replace('.tpl', '', $templateName)));
    }
    unset($templateName);
    $form->addSelectBox(
        'ecard_template',
        $gL10n->get('SYS_TEMPLATE'),
        $newTemplateArray,
        array(
            'defaultValue' => $gSettingsManager->getString('photo_ecard_template'),
            'property' => FormPresenter::FIELD_REQUIRED,
            'showContextDependentFirstEntry' => false
        )
    );

    // create list with all possible recipients
    $list = array();

    // list all roles where login users could send mails to
    $sql = 'SELECT rol_uuid, rol_name
          FROM ' . TBL_ROLES . '
    INNER JOIN ' . TBL_CATEGORIES . '
            ON cat_id = rol_cat_id
         WHERE rol_uuid IN (' . Database::getQmForValues($gCurrentUser->getRolesWriteMails()) . ')
           AND cat_name_intern <> \'EVENTS\'
      ORDER BY rol_name';
    $statement = $gDb->queryPrepared($sql, $gCurrentUser->getRolesWriteMails());

    while ($row = $statement->fetch()) {
        $list[] = array('groupID: ' . $row['rol_uuid'], $row['rol_name'], $gL10n->get('SYS_ROLES'));
    }

    // select all users
    $arrayRoles = array_merge($gCurrentUser->getRolesWriteMails(), $gCurrentUser->getRolesViewMemberships());
    $arrayUniqueRoles = array_unique($arrayRoles);

    $sql = 'SELECT DISTINCT usr_uuid, first_name.usd_value AS first_name, last_name.usd_value AS last_name
          FROM ' . TBL_MEMBERS . '
    INNER JOIN ' . TBL_ROLES . '
            ON rol_id = mem_rol_id
    INNER JOIN ' . TBL_USERS . '
            ON usr_id = mem_usr_id
     LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
     LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
         WHERE usr_valid  = true
           AND mem_begin <= ? -- DATE_NOW
           AND mem_end    > ? -- DATE_NOW
           AND rol_uuid IN (' . Database::getQmForValues($arrayUniqueRoles) . ')
      GROUP BY usr_id, first_name.usd_value, last_name.usd_value
      ORDER BY last_name, first_name';
    $queryParams = array_merge(
        array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            DATE_NOW,
            DATE_NOW),
        $arrayUniqueRoles
    );

    $statement = $gDb->queryPrepared($sql, $queryParams);

    while ($row = $statement->fetch()) {
        $list[] = array($row['usr_uuid'], $row['last_name'] . ', ' . $row['first_name'], $gL10n->get('SYS_CONTACTS'));
    }

    $form->addSelectBox(
        'ecard_recipients',
        $gL10n->get('SYS_TO'),
        $list,
        array('property' => FormPresenter::FIELD_REQUIRED, 'multiselect' => true)
    );
    $form->addInput(
        'name_from',
        $gL10n->get('SYS_YOUR_NAME'),
        $gCurrentUser->getValue('FIRST_NAME') . ' ecards.php' . $gCurrentUser->getValue('LAST_NAME'),
        array('maxLength' => 50, 'property' => FormPresenter::FIELD_DISABLED)
    );
    $form->addInput(
        'mail_from',
        $gL10n->get('SYS_YOUR_EMAIL'),
        $gCurrentUser->getValue('EMAIL'),
        array('type' => 'email', 'maxLength' => 50, 'property' => FormPresenter::FIELD_DISABLED)
    );
    $form->addEditor(
        'ecard_message',
        '',
        '',
        array('property' => FormPresenter::FIELD_REQUIRED, 'toolbar' => 'AdmidioComments')
    );
    $form->addButton('adm_button_ecard_preview', $gL10n->get('SYS_PREVIEW'), array('icon' => 'bi-eye-fill'));
    $form->addSubmitButton('adm_button_ecard_submit', $gL10n->get('SYS_SEND'), array('icon' => 'bi-envelope-fill'));

    $page->assignSmartyVariable('photoPreviewUrl',
        SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/photos/photo_show.php',
            array(
                'photo_uuid' => $getPhotoUuid,
                'photo_nr' => $getPhotoNr,
                'max_width' => $gSettingsManager->getInt('photo_show_width'),
                'max_height' => $gSettingsManager->getInt('photo_show_height')
            )
        )
    );
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $page->show();
} catch (Throwable $e) {
    $gMessage->show($e->getMessage());
}
