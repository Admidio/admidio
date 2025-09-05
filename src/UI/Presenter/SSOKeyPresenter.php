<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Service\KeyService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new MenuPresenter('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOKeyPresenter extends PagePresenter
{
    /**
     * @var string uuid of the cryptographic key.
     */
    protected string $keyUUID = '';

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $keyUUID UUID of the cryptographic key in the database.
     * @throws Exception
     */
    public function __construct(string $keyUUID = '')
    {
        $this->keyUUID = $keyUUID;
        parent::__construct($keyUUID);
    }

    /**
     * Create the data for the edit form of a cryptographic key.
     * @param string $mode Mode of the form (view, new, regenerate).
     * @throws Exception
     */
    public function createEditForm(string $mode = "view"): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gSettingsManager, $gCurrentOrganization;

        // For each edit field with class copy-container, add a copy button to copy the content of the textarea to the clipboard
        $this->addJavascript("
            $(\"textarea.copy-container, input.copy-container\").each(function () {
                let \$input = \$(this);

                // Wrap the input/textarea inside a div (if not already wrapped)
                if (!\$input.parent().hasClass(\"copy-wrapper\")) {
                    \$input.wrap('<div class=\"copy-wrapper\"></div>');
                }
                let \$container = \$input.parent();

                // Create the copy button (transparent overlay)
                let \$copyButton = \$(\"<div>\")
                    .addClass(\"copy-btn\")
                    .html('<i class=\"bi bi-copy\"></i>')
                    .attr(\"title\", \"" . $gL10n->get('SYS_COPY_CLIPBOARD') . "\");

                // Append button inside the same parent as the input/textarea
                \$container.append(\$copyButton);

                // Copy to clipboard functionality
                \$copyButton.on(\"click\", function (event) {
                    event.preventDefault(); // Prevent any form interactions
                    let text = \$input.val(); // Get input/textarea content

                    navigator.clipboard.writeText(text).then(() => {
                        // Show feedback tooltip
                        \$copyButton.attr(\"title\", \"" . $gL10n->get('SYS_COPIED_CLIPBOARD') . "\").tooltip(\"show\");

                        // Reset tooltip after 1.5 seconds
                        setTimeout(() => {
                            \$copyButton.attr(\"title\", \"" . $gL10n->get('SYS_COPY_CLIPBOARD') . "\");
                        }, 1500);
                    });
                });
            });", true);
        $this->addHtml("
        <style>
            .copy-wrapper {
                position: relative;
                display: inline-block;
                width: 100%;
            }

            .copy-container {
                width: 100%;
                padding-right: 40px;
                resize: none;
            }

            .copy-btn {
                position: absolute;
                top: 50%;
                right: 8px;
                transform: translateY(-50%);
                background: transparent;
                cursor: pointer;
                font-size: 18px;
                color: rgba(0, 0, 0, 0.4);
                padding: 5px;
            }

            .copy-btn:hover {
                color: black;
            }
        </style>");

        // create SAML client object
        $key = new Key($gDb);
        if (!empty($this->keyUUID)) {
            $key->readDataByUuid($this->keyUUID);
        }

        $haveKey = !$key->isNewRecord();
        if ($haveKey) {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_SSO_KEY'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_SSO_KEY'))));
        }
        $this->setHtmlID('admidio-saml-client-edit');

        ChangelogService::displayHistoryButton($this, 'sso-key', 'sso_keys', !empty($this->keyUUID), array('uuid' => $this->keyUUID));

        // show form
        $form = new FormPresenter(
            'adm_ssh_key_edit_form',
            'modules/sso_key.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php', array('uuid' => $this->keyUUID, 'mode' => 'save')),
            $this
        );

        $form->addInput(
            'key_name',
            $gL10n->get('SYS_NAME'),
            $key->getValue('key_name'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_KEY_NAME_DESC'))
        );

        $algos = ["RSA" => "RSA (default 2048)", "RSA-2048" => "RSA-2048", "RSA-3072" => "RSA-3072", "RSA-4096" => "RSA-4096"/*, "RSA-8192" => "RSA-8192", "ECDSA" => "ECDSA", "ECDSA-256" => "ECDSA-256", "ECDSA-384" => "ECDSA-384", "ECDSA-521" => "ECDSA-521"*/];
        $form->addSelectBox(
            'key_algorithm',
            $gL10n->get('SYS_SSO_KEY_ALGORITHM'),
            $algos,
            array('property' => $haveKey?FormPresenter::FIELD_DISABLED:FormPresenter::FIELD_REQUIRED, 'defaultValue' => $key->getValue('key_algorithm')?:'RSA', 'helpTextId' => $gL10n->get('SYS_SSO_KEY_ALGORITHM_DESC'))
        );
        $form->addMultilineTextInput(
            'key_public',
            $gL10n->get('SYS_SSO_KEY_PUBLIC'),
            $key->getValue('key_public'),
            4,
            array('class'=>'copy-container', 'property' => $haveKey?FormPresenter::FIELD_DISABLED:FormPresenter::FIELD_HIDDEN)
        );
        $form->addCustomContent(
            'key_private',
            $gL10n->get('SYS_SSO_KEY_PRIVATE'),
            $gL10n->get('SYS_SSO_KEY_PRIVATE_NOT_SHOWN'),
            array('property' => $haveKey?FormPresenter::FIELD_READONLY:FormPresenter::FIELD_HIDDEN)
        );

        $form->addMultilineTextInput(
            'key_certificate',
            $gL10n->get('SYS_SSO_KEY_CERTIFICATE'),
            $key->getValue('key_certificate'),
            4,
            array('property' => $haveKey?FormPresenter::FIELD_DISABLED:FormPresenter::FIELD_HIDDEN, 'class'=>'copy-container')
        );
        $form->addCheckbox(
            'key_is_active',
            $gL10n->get('SYS_SSO_KEY_ACTIVE'),
            $key->isNewRecord() ? true : $key->getValue('key_is_active'),
            array('helpTextId' => $gL10n->get('SYS_SSO_KEY_ACTIVE_DESC'))
        );


        /* *********************************************
         * Certificate information
         * *********************************************/
        $haveCert = false;
        $cert = $key->getValue('key_certificate');
        if (!empty($cert)) {
            $cert = openssl_x509_read($key->getValue('key_certificate'));
            if ($cert !== false) {
                $cert = openssl_x509_parse($cert);
            }
        }
        $haveCert = !empty($cert) && ($cert !== false);
        $dateFormat = $gSettingsManager->getString('system_date');
        if (!$haveCert) {
            $expirationTS = new \DateTime();
            $expirationTS->modify('+2 years');

            $certData = array(
                'countryName' => '',
                'stateOrProvinceName' => '',
                'localityName' => '',
                'organizationName' => $gCurrentOrganization->getValue('org_longname'),
                'organizationalUnitName' => '',
                'commonName' => ADMIDIO_URL,
                'email' => $gCurrentOrganization->getValue('org_email_administrator'),
                'validTo' => $expirationTS->format($dateFormat)
            );
        } else {
            $expirationTS = $key->getValue('key_expires_at', $dateFormat);
            $certData = array(
                'countryName' => $cert['subject']['C'],
                'stateOrProvinceName' => $cert['subject']['ST'],
                'localityName' => $cert['subject']['L'],
                'organizationName' => $cert['subject']['O'],
                'organizationalUnitName' => $cert['subject']['OU'],
                'commonName' => $cert['subject']['CN'],
                'email' => $cert['subject']['emailAddress'],
                'validTo' => $expirationTS
            );
        }
        $certProp = $haveCert?FormPresenter::FIELD_DISABLED:0;
        $certPropReq = $haveCert?FormPresenter::FIELD_DISABLED:FormPresenter::FIELD_REQUIRED;

        $form->addInput(
            'cert_country',
            $gL10n->get('SYS_COUNTRY'),
            $certData['countryName'],
            array('maxLength' => 2, 'class' => 'certdata', 'property' => $certPropReq));
        $form->addInput(
            'cert_state',
            $gL10n->get('SYS_SSO_CERT_STATE'),
            $certData['stateOrProvinceName'],
            array('maxLength' => 128, 'class' => 'certdata', 'property' => $certPropReq));
        $form->addInput(
            'cert_locality',
            $gL10n->get('SYS_CITY'),
            $certData['localityName'],
            array('maxLength' => 128, 'class' => 'certdata', 'property' => $certPropReq));
        $form->addInput(
            'cert_org',
            $gL10n->get('SYS_ORGANIZATION'),
            $certData['organizationName'],
            array('maxLength' => 128, 'class' => 'certdata', 'property' => $certPropReq));
        $form->addInput(
            'cert_orgunit',
            $gL10n->get('SYS_SSO_CERT_ORGANIZATION_UNIT'),
            $certData['organizationalUnitName'],
            array('maxLength' => 128, 'class' => 'certdata', 'property' => $certPropReq));
        $form->addInput(
            'cert_common_name',
            $gL10n->get('SYS_SSO_CERT_COMMON_NAME'),
            $certData['commonName'],
            array('class'=>'copy-container certdata', 'property' => $certPropReq, 'maxLength' => 128));
        $form->addInput(
            'cert_admin_email',
            $gL10n->get('SYS_EMAIL_ADMINISTRATOR'),
            $certData['email'],
            array('maxLength' => 200, 'class' => 'certdata', 'property' => $certProp));

        $form->addInput(
            'key_expires_at',
            $gL10n->get('SYS_SSO_KEY_EXPIRES'),
            $certData['validTo'],
            array('type' => 'date', 'class' => 'certdata', 'property' => $certProp)
        );


        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3'));

        $this->smarty->assign('nameUserCreated', $key->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $key->getValue('key_timestamp_create'));
        $this->smarty->assign('nameLastUserEdited', $key->getNameOfLastEditingUser());
        $this->smarty->assign('timestampLastUserEdited', $key->getValue('key_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    public function createExportPasswordForm() {
        global $gL10n, $gCurrentSession;

        // show form
        $form = new FormPresenter(
            'adm_password_form',
            'modules/sso_key.password.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php', array('uuid' => $this->keyUUID, 'mode' => 'export')),
            null,
            array('showRequiredFields' => false)
        );
        $form->addInput(
            'key_password',
            $gL10n->get('SYS_PASSWORD'),
            '',
            array(
                'type' => 'password',
                // 'property' => FormPresenter::FIELD_REQUIRED,
                'minLength' => 0,
                'passwordStrength' => false,
                'helpTextId' => 'SYS_SSO_EXPORT_PASSWORD_DESC'
            )
        );
        $form->addSubmitButton(
            'downloadButton',
            $gL10n->get('SYS_SSO_KEY_EXPORT'),
            array('icon' => 'bi-box-arrow-down')
        );

        $smarty = $this->createSmartyObject();
        $form->addToSmarty($smarty);
        $gCurrentSession->addFormObject($form);
        echo $smarty->fetch('modules/sso_key.password.tpl');
    }


    /**
     * Create the list of SAML and OIDC clients to show to the user.
     * @throws Exception|\Smarty\Exception
     */
    public function createList(): void
    {
        global $gCurrentSession, $gL10n, $gDb, $gCurrentUser;

        $this->setHtmlID('adm_sso_keys_configuration');
        $this->setHeadline($gL10n->get('SYS_SSO_KEY_ADMIN'));


        // link to preferences
        $this->addPageFunctionsMenuItem(
            'menu_item_sso_preferences',
            $gL10n->get('SYS_SETTINGS'),
            ADMIDIO_URL . FOLDER_MODULES . '/preferences.php?panel=sso',
            'bi-gear-fill'
        );

        // link to add new key
        $this->addPageFunctionsMenuItem(
            'menu_item_sso_new_client_saml',
            $gL10n->get('SYS_SSO_KEY_ADD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php', array('mode' => 'edit')),
            'bi-plus-circle-fill'
        );

        ChangelogService::displayHistoryButton($this, 'sso-keys', array('sso_keys'));


        $table = new DataTables($this, 'adm_sso_keys_table');

        $columnHeading = array(
            $gL10n->get('SYS_NAME'),
            $gL10n->get('SYS_SSO_KEY_ALGORITHM'),
            $gL10n->get('SYS_SSO_KEY_EXPIRES'),
            $gL10n->get('SYS_SSO_KEY_ACTIVE'),
            ''
        );

        $table->setMessageIfNoRowsFound('SYS_SSO_NO_KEYS_FOUND');

        // $table->disableDatatablesColumnsSort(array(3, 6));
        $table->setColumnsNotHideResponsive(array(5));
        // special settings for the table

        $columnValues = array();
        $keyService = new KeyService($gDb);
        foreach ($keyService->getKeysData() as $keyData) {
            $templateKey = array();
            $urlEdit = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php', array('mode' => 'edit', 'uuid' => $keyData['key_uuid']));
            $templateKey[] = '<a href="' . $urlEdit . '">' . $keyData['key_name'] . '</a>';
            $templateKey[] = $keyData['key_algorithm'];
            $templateKey[] = $keyData['key_expires_at'];
            $templateKey[] = $keyData['key_is_active'] ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO');

            $actions = '';
            // Actions: Certificate, Export, Edit, Re-Generate, Delete
            $actions = array(
                array('mode' => 'edit',        'icon' => 'bi-pencil-square',  'title' => $gL10n->get('SYS_SSO_KEY_EDIT')),
                array('mode' => 'certificate', 'icon' => 'bi-shield-lock',    'title' => $gL10n->get('SYS_SSO_KEY_CERTIFICATE')),
                array('mode' => 'export_password',      'icon' => 'bi-box-arrow-down', 'title' => $gL10n->get('SYS_SSO_KEY_EXPORT'), 'popup' => true),
                array('mode' => 'delete',      'icon' => 'bi-trash',          'title' => $gL10n->get('SYS_SSO_KEY_DELETE'), 'message' => $gL10n->get('SYS_WANT_DELETE_ENTRY', array($keyData['key_name'])), 'buttons' => 'yes-no')
            );

            $actions = array_map(function($action) use ($keyData) {
                global $gCurrentSession, $gCurrentUser;
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/keys.php', array('mode' => $action['mode'], 'uuid' => $keyData['key_uuid']));
                $classes = '';
                $href = $url;
                $attributes = '';

                if (!empty($action['message'])) {
                    $classes = 'admidio-messagebox';
                    $href = "javascript:void(0)";
                    if (!empty($action['buttons'])) {
                        $attributes .= " data-buttons=\"" . $action['buttons'] . "\"";
                    }
                    if (!empty($action['message'])) {
                        $attributes .= " data-message=\"" . $action['message'] . "\"";
                    }
                    $attributes .= " data-href=\"callUrlHideElement('adm_sso_key_" . $keyData['key_uuid'] . "', '$url', '" . $gCurrentSession->getCsrfToken() . '\')"';
                }
                if (!empty($action['popup'])) {
                    $classes = 'openPopup';
                    $href = "javascript:void(0)";
                    $attributes .= " data-href=\"$url\"";
                }

                return '<a class="admidio-icon-link '.$classes.'" href="' . $href . '" ' . $attributes . '>' .
                    '<i class="bi ' . $action['icon'] . '" data-bs-toggle="tooltip" title="' . $action['title'] . '"></i></a>';
            }, $actions);

            $templateKey[] = implode(' ', $actions);

            $columnValues[] = array('id' => 'adm_sso_key_' . $keyData['key_uuid'], 'data' => $templateKey);
        }


        $table->createJavascript(count($columnValues), count($columnHeading));

        $this->assignSmartyVariable('headers', $columnHeading);
        $this->assignSmartyVariable('rows', $columnValues);
        // add table to the page
        $this->addHtmlByTemplate('modules/sso_keys.list.tpl');
    }
}
