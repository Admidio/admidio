<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\SSO\Entity\SSOClient;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Service\SAMLService;
use Admidio\SSO\Service\OIDCService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Roles\Entity\RolesRights;

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
class SSOClientPresenter extends PagePresenter
{
    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $clientId Id of the SAML or OIDC client.
     * @throws Exception
     */
    public function __construct(string $objectUUID = '')
    {
        parent::__construct($objectUUID);
    }

    protected function createSSOEditFormJS(array $available, array $config = [], $type = "fieldsmap") {
        global $gL10n;

        $jsInit = '';
        $js = '$("#' . $type . '_tbody").sortable();';
        
        $jsInit .= '
            function createArray_' . $type . '() {
                var entries = new Array(); ';

        // create an array for all columns with the necessary data
        foreach ($available as $key => $value) {
            $jsInit .= '
                    entries[' . $key . '] 			= new Object();
                    entries[' . $key . ']["id"]   	= "' . $available[$key][0] . '";
                    entries[' . $key . ']["data"]   	= "' . $available[$key][1] . '";
                    entries[' . $key . ']["cat_name"] = "' . $available[$key][2] . '";
                    ';
        }
        $jsInit .= '
            return entries;
        }
        ';

        $jsInit .= '
            var arr_' . $type . ' = createArray_' . $type . '();
            var fieldNumberIntern_' . $type . '  = 0;
        
            // Function adds a new row for assigning columns to the list
            function addColumn_' . $type . '(ssoField = "", admidioField = "")
            {
                var category = "";
                var table = document.getElementById("' . $type . '_tbody");
                var newTableRow = table.insertRow(fieldNumberIntern_' . $type . ');
                newTableRow.setAttribute("id", "row" + (fieldNumberIntern_' . $type . '))

                // New column for selecting the field
                var newCellAdmidio = newTableRow.insertCell(-1);
                htmlAdmidioValues = "<select class=\"form-control admidio-field-select\"  size=\"1\" id=\"' . $type . '_admidio" + fieldNumberIntern_' . $type . ' + "\" class=\"List_' . $type . '\" name=\"' . $type . '_Admidio[]\">";
                for(var counter = 0; counter < arr_' . $type . '.length; counter++) {
                    if(category !=  arr_' . $type . '[counter]["cat_name"]) {
                        if(category.length > 0) {
                            htmlAdmidioValues += "</optgroup>";
                        }
                        htmlAdmidioValues += "<optgroup label=\"" +  arr_' . $type . '[counter]["cat_name"] + "\">";
                        category =  arr_' . $type . '[counter]["cat_name"];
                    }
        
                    var selected = "";
                    if( arr_' . $type . '[counter]["id"] == admidioField){
                        selected = " selected=\"selected\" ";
                    }
                     htmlAdmidioValues += "<option value=\"" +  arr_' . $type . '[counter]["id"] + "\" " + selected + ">" +  arr_' . $type . '[counter]["data"] + "</option>";
                }
                if(category.length > 0) {
                    htmlAdmidioValues += "</optgroup>";
                }
                htmlAdmidioValues += "</select>";
                newCellAdmidio.innerHTML = htmlAdmidioValues;
        
                // New column for selecting the mapped name
                var newCellSAML = newTableRow.insertCell(-1);
                htmlMappedName = "<input type=\"text\" class=\"form-control sso-field-input\" id=\"' . $type . '_sso" + fieldNumberIntern_' . $type . ' + "\" name=\"' . $type . '_sso[]\" value=\"" + ssoField + "\" size=\"30\" maxlength=\"250\">";
                newCellSAML.innerHTML = htmlMappedName;

                var newCellButtons = newTableRow.insertCell(-1);
                newCellButtons.style.paddingLeft = "0";
                newCellButtons.style.paddingRight = "0";
                htmlMoveButtons = /*"<a class=\"admidio-icon-link admidio-move-row-up\" style=\"padding-left: 0pt; padding-right: 0pt;\">" +
                        "        <i class=\"bi bi-arrow-up-circle-fill\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_MOVE_UP') . '\"></i></a>" + 
                        "    <a class=\"admidio-icon-link admidio-move-row-down\" style=\"padding-left: 0pt; padding-right: 0pt;\">" + 
                        "        <i class=\"bi bi-arrow-down-circle-fill\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_MOVE_DOWN') . '\"></i></a>" + */
                        "    <a class=\"admidio-icon-link admidio-move-row\" style=\"padding-left: 0pt; padding-right: 0pt;\">" + 
                        "        <i class=\"bi bi-arrows-move handle\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_MOVE_VAR') . '\"></i></a>" +
                        "    <a class=\"admidio-icon-link admidio-delete\" style=\"padding-left: 0pt; padding-right: 0pt;\">" + 
                        "        <i class=\"bi bi-trash\" data-bs-toggle=\"tooltip\" title=\"' . $gL10n->get('SYS_DELETE') . '\"></i></a>";
                newCellButtons.innerHTML = htmlMoveButtons;

                $(newTableRow).fadeIn("slow");
                fieldNumberIntern_' . $type . '++;
            }
            ';

        // Add a row for each configured field / role 
        $js .= '';
        foreach ($config as $ssoField => $admidioField) {
            $js .= '
            addColumn_' . $type . '("' . $ssoField . '", "' . $admidioField . '");';
        }

        return array('js' => $js, 'jsInit' => $jsInit);
    }

    protected function getAvailableRoles(): array {
        global $gDb, $gL10n;
        // Access restrictions by role/group are handled through role rights
        $sqlRoles = 'SELECT rol_id, rol_name, org_shortname, cat_name
                       FROM ' . TBL_ROLES . '
                 INNER JOIN ' . TBL_CATEGORIES . '
                         ON cat_id = rol_cat_id
                 INNER JOIN ' . TBL_ORGANIZATIONS . '
                         ON org_id = cat_org_id
                      WHERE rol_valid  = true
                        AND rol_system = false
                        AND cat_name_intern <> \'EVENTS\'
                   ORDER BY cat_name, rol_name';
        $allRolesStatement = $gDb->queryPrepared($sqlRoles);
        $allRolesSet = array();
        while ($rowViewRoles = $allRolesStatement->fetch()) {
            // Each role is now added to this array
            $allRolesSet[] = array(
                $rowViewRoles['rol_id'], // ID 
                $rowViewRoles['rol_name'] . ' (' . $rowViewRoles['org_shortname'] . ')', // Value
                $rowViewRoles['cat_name'] // Group
            );
            // Leader has the role ID with negative sign!
            $allRolesSet[] = array(
                -$rowViewRoles['rol_id'], // ID 
                $rowViewRoles['rol_name'] . ' (' . $rowViewRoles['org_shortname'] . ') - ' . $gL10n->get('SYS_LEADER'), // Value
                $rowViewRoles['cat_name'] // Group
            );
        }
        return $allRolesSet;
    }

    /**
     * Create the data for the edit form of a SAML client.
     * @throws Exception
     */
    public function createSAMLEditForm(): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gProfileFields, $gCurrentUser;

        // create SAML client object
        $client = new SAMLClient($gDb);
        if ($this->objectUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_SSO_CLIENT_SAML'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_SSO_CLIENT_SAML'))));
        }
        $this->setHtmlID('admidio-saml-client-edit');
        
        $roleAccessSet = array();
        if ($this->objectUUID !== '') {
            $client->readDataByUUID($this->objectUUID);
        }

        $allRolesSet = $this->getAvailableRoles();

        ChangelogService::displayHistoryButton($this, 'saml-client', 'saml_clients', !empty($this->objectUUID), array('uuid' => $this->objectUUID));

        // show form
        $form = new FormPresenter(
            'adm_saml_client_edit_form',
            'modules/saml_client.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('uuid' => $this->objectUUID, 'mode' => 'save_saml')),
            $this
        );

        $SAMLService = new SAMLService($gDb, $gCurrentUser);
        $form->addCustomContent(
            'sso_saml_sso_staticsettings',
            $gL10n->get('SYS_SSO_STATIC_SETTINGS'),
            '',
            array('data' => $SAMLService->getStaticSettings())
        );

        $form->addCheckbox(
            'smc_enabled',
            $gL10n->get('SYS_ENABLED'),
            $client->getValue('smc_enabled'),
            array()
        );
        $form->addInput(
            'smc_client_name',
            $gL10n->get('SYS_SSO_CLIENT_NAME'),
            $client->getValue('smc_client_name'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_CLIENT_NAME_DESC'))
        );
        $form->addInput(
            'smc_client_id',
            $gL10n->get('SYS_SSO_CLIENT_ID'),
            $client->getValue('smc_client_id'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_CLIENT_ID_DESC'))
        );
        $form->addInput(
            'smc_metadata_url',
            $gL10n->get('SYS_SSO_METADATA_URL'),
            $client->getValue('smc_metadata_url'),
            array('type' => 'url', 'maxLength' => 2000, 'helpTextId' => $gL10n->get('SYS_SSO_METADATA_URL_DESC'))
        );
        $form->addInput(
            'smc_acs_url',
            $gL10n->get(textId: 'SYS_SSO_ACS_URL'),
            $client->getValue('smc_acs_url'),
            array('type' => 'url', 'maxLength' => 2000, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_ACS_URL_DESC'))
        );
        $form->addInput(
            'smc_slo_url',
            $gL10n->get(textId: 'SYS_SSO_SLO_URL'),
            $client->getValue('smc_slo_url'),
            array('type' => 'url', 'maxLength' => 2000, 'helpTextId' => $gL10n->get('SYS_SSO_SLO_URL_DESC'))
        );



        // TAB: Signatures and Encryption

        $form->addMultilineTextInput(
            'smc_x509_certificate',
            $gL10n->get('SYS_SSO_X509_CERTIFICATE'),
            $client->getValue('smc_x509_certificate'),
            6,
            array('maxLength' => 6000, 'helpTextId' => $gL10n->get('SYS_SSO_X509_CERTIFICATE_DESC'))
        );

        $form->addCheckbox(
            'smc_require_auth_signed',
            $gL10n->get('SYS_SSO_REQUIRE_AUTHN_SIGNED'),
            $client->getValue('smc_require_auth_signed'),
            array()
        );
        $form->addCheckbox(
            'smc_sign_assertions',
            $gL10n->get('SYS_SSO_SIGN_ASSERTIONS'),
            $client->getValue('smc_sign_assertions'),
            array()
        );
        $form->addCheckbox(
            'smc_encrypt_assertions',
            $gL10n->get('SYS_SSO_ENCRYPT_ASSERTIONS'),
            $client->getValue('smc_encrypt_assertions'),
            array()
        );
        $form->addCheckbox(
            'smc_validate_signatures',
            $gL10n->get('SYS_SSO_VALIDATE_SIGNATURES'),
            $client->getValue('smc_validate_signatures'),
            array()
        );

        $form->addInput(
            'smc_assertion_lifetime',
            $gL10n->get('SYS_SSO_SAML_ASSERTION_LIFETIME'),
            $client->getValue('smc_assertion_lifetime') ?? '600',
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_SSO_SAML_ASSERTION_LIFETIME_DESC')
        );
        
        $form->addInput(
            'smc_allowed_clock_skew',
            $gL10n->get('SYS_SSO_SAML_ALLOWED_CLOCK_SKEW'),
            $client->getValue('smc_allowed_clock_skew') ?? '0',
            array('type' => 'number', 'minNumber' => 0, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_SSO_SAML_ALLOWED_CLOCK_SKEW_DESC')
        );



        $useridFields = [
            ['usr_id', $gL10n->get('SYS_SSO_USERID_ID') . ' - usr_id', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['usr_uuid',  $gL10n->get('SYS_SSO_USERID_UUID') . ' - usr_uuid', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['usr_login_name', $gL10n->get('SYS_SSO_USERID_LOGIN') . ' - usr_login_name', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['EMAIL', $gL10n->get('SYS_EMAIL') . ' - EMAIL', $gL10n->get('SYS_SSO_USERID_FIELDS')],
        ];
        $form->addSelectBox(
            'smc_userid_field',
            $gL10n->get('SYS_SSO_USERID_FIELD'),
            $useridFields,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => $client->getValue('smc_userid_field'),
                'multiselect' => false,
                'helpTextId' => 'SYS_SSO_USERID_FIELD_DESC'
                )
            );


        $userFields = $useridFields;
        $userFields[] = ['fullname', $gL10n->get('SYS_NAME') . ' - fullname', $gL10n->get('SYS_BASIC_DATA')];
        foreach ($gProfileFields->getProfileFields() as $field) {
            if ($field->getValue('usf_hidden') == 0) {
                $fieldId = $field->getValue('usf_name_intern');
                $fieldValue = addslashes($field->getValue('usf_name')) . ' - ' . strtolower($fieldId);
                $fieldCat = $gL10n->translateIfTranslationStrId($field->getValue('cat_name'));
                $userFields[] =  [$fieldId, $fieldValue, $fieldCat];
            }
        }
        $userFields[] = ['roles', $gL10n->get('SYS_ROLES') . ' - roles', $gL10n->get('SYS_ROLES')];

        $js = $this->createSSOEditFormJS( $userFields, $client->getFieldMapping(), "fieldsmap");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#fieldsmap_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("fieldsmap_Admidio", '', '');
        $form->addCustomContent("fieldsmap_sso", '', '');

        $form->addCheckbox(
            'sso_fields_all_other',
            $gL10n->get('SYS_SSO_ATTRIBUTES_ALLOTHER'),
            $client->getFieldMappingCatchall(),
            array('helpTextId' => '')
        );

        
        $js = $this->createSSOEditFormJS($allRolesSet, $client->getRoleMapping(), "rolesmap");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#rolesmap_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("rolesmap_Admidio", '', '');
        $form->addCustomContent("rolesmap_sso", '', '');

        $form->addCheckbox(
            'sso_roles_all_other',
            $gL10n->get('SYS_SSO_ROLES_ALLOTHER'),
            $client->getRoleMappingCatchall(),
            array('helpTextId' => '')
        );

        // Add JS code for the move UP/DOWN "buttons":
        $this->addJavascript('
                $(document).on("click", ".admidio-move-row-up", function(){
                    let row = $(this).closest("tr");
                    let prevRow = row.prev("tr");
                    if (prevRow.length) {
                        row.insertBefore(prevRow);
                    }
                });
                $(document).on("click", ".admidio-move-row-down", function(){
                    let row = $(this).closest("tr");
                    let nextRow = row.next();
                    if (nextRow.length) {
                        row.insertAfter(nextRow);
                    }
                });
                $(document).on("click", ".admidio-delete", function(){
                    let row = $(this).closest("tr").fadeOut(300, function() {
                        $(this).remove();
                    });
                });
                ', true);

        // Add JS code to set the saml field input to the Admidio field name if no name was set yet


        $form->addSelectBox(
            'sso_roles_access',
            $gL10n->get('SYS_SSO_ROLES'),
            array_filter($allRolesSet, function($role) { return $role[0] > 0; } ),
            array(
                'property' => FormPresenter::FIELD_DEFAULT,
                'defaultValue' => $client->getAccessRolesIds(),
                'multiselect' => true,
                'helpTextId' => 'SYS_SSO_ROLES_DESC'
            )
        );
    
        
        $form->addSubmitButton(
            'adm_button_save', 
            $gL10n->get('SYS_SAVE'), 
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3'));


    /*******************************************
     * Button to load metadata from the URL
     */
    $form->addButton('adm_button_metadata_setup', $gL10n->get('SYS_SSO_LOAD_METADATA'), array('icon' => 'bi-gear-fill', 'class' => 'btn btn-primary'));
    $this->addJavascript('
    $("#adm_button_metadata_setup").click(function () {
        const metadataUrl = $("#smc_metadata_url").val().trim();
        if (!metadataUrl) { alert("Please enter a metadata URL."); return;}

        // First try to load the metadata directly from the client. If we run into CORS error (loading from a different server 
        // than the one hosting Admidio is often not permitted), we use the admidio server\'s CORS proxy script.
        $.get(metadataUrl)
            .done(function (metadataXml) {
                handleClientMetadataXML(metadataXml);
            })
            .fail(function () {
                // Loading directly from the client failed, try using the CORS proxy script in admidio\'s source tree
                const currentDir = window.location.pathname.substring(0, window.location.pathname.lastIndexOf(\'/\'));
                const proxyUrl = `${window.location.origin}${currentDir}/fetch_metadata.php?url=${encodeURIComponent(metadataUrl)}`;
                $.get(proxyUrl)
                    .done(function (metadataXml) {
                        handleClientMetadataXML(metadataXml);
                    })
                    .fail(function () {
                        alert("Error loading metadata. Please check the URL and try again.");
                    });
            });
    });

    function handleClientMetadataXML(metadataXml) {
        let xmlDoc;
        // If response is already an XML Document, use it directly
        if (metadataXml instanceof Document) {
            xmlDoc = metadataXml;
        } else if (typeof metadataXml === "string") {
            // If response is a string, attempt to parse it as XML
            xmlDoc = $.parseXML(metadataXml);
        } else {
            alert("Unexpected response format.");
            return false;
        }
        const $xml = $(xmlDoc);

        // Use native JavaScript methods to handle XML namespaces
        const entityDescriptor = xmlDoc.querySelector("EntityDescriptor");
        const entityId = entityDescriptor ? entityDescriptor.getAttribute("entityID") : "";

        // Extract Assertion Consumer Service (ACS) URL
        const acsElement = xmlDoc.querySelector("AssertionConsumerService");
        const acsUrl = acsElement ? acsElement.getAttribute("Location") : "";

        const sloElement = xmlDoc.querySelector("SingleLogoutService");
        const sloUrl = sloElement ? sloElement.getAttribute("Location") : "";
        
        // Extract X.509 Certificate
        const x509Element = xmlDoc.querySelector("KeyDescriptor[use=\'signing\'] X509Certificate");
        const x509Cert = x509Element ? x509Element.textContent.trim() : "";

        // signing flags
        // XPath-Abfrage zum Finden des SPSSODescriptor-Elements
        //const spDescriptor = xmlDoc.evaluate("//md:SPSSODescriptor", xmlDoc, nsResolver, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
        const spDescriptor = xmlDoc.querySelector("SPSSODescriptor");

        if (spDescriptor) {
            // Werte der Attribute auslesen
            const authnRequestsSigned = spDescriptor.getAttribute("AuthnRequestsSigned") === "true";
            const wantAssertionsSigned = spDescriptor.getAttribute("WantAssertionsSigned") === "true";

            // Checkboxen anhand der Werte setzen
            document.getElementById("smc_require_auth_signed").checked = authnRequestsSigned;
            document.getElementById("smc_sign_assertions").checked = wantAssertionsSigned;
        }
        
        // Populate input fields
        if (entityId !="") {
            $("#smc_client_id").val(entityId);
        }
        if (acsUrl !="") {
            $("#smc_acs_url").val(acsUrl);
        }
        if (sloUrl !="") {
            $("#smc_slo_url").val(sloUrl);
        }
        if (x509Cert !="") {
            $("#smc_x509_certificate").val(formatCertificate(x509Cert));
        }
    }
    // Helper function to format X.509 certificate with proper line breaks
    function formatCertificate(cert) {
        if (!cert) return "";
        return `-----BEGIN CERTIFICATE-----\n${cert.match(/.{1,64}/g).join("\n")}\n-----END CERTIFICATE-----`;
    }
        ', true);

        $this->smarty->assign('nameUserCreated', $client->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $client->getValue('smc_timestamp_create'));
        $this->smarty->assign('nameLastUserEdited', $client->getNameOfLastEditingUser());
        $this->smarty->assign('timestampLastUserEdited', $client->getValue('smc_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }



    /**
     * Create the data for the edit form of an OIDC client.
     * @throws Exception
     */
    public function createOIDCEditForm(): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gProfileFields, $gCurrentUser;

        // create OIDC client object
        $client = new OIDCClient($gDb);
        if ($this->objectUUID !== '') {
            $this->setHeadline($gL10n->get('SYS_EDIT_VAR', array($gL10n->get('SYS_SSO_CLIENT_OIDC'))));
        } else {
            $this->setHeadline($gL10n->get('SYS_CREATE_VAR', array($gL10n->get('SYS_SSO_CLIENT_OIDC'))));
        }
        $this->setHtmlID('admidio-oidc-client-edit');
        
        $allRolesSet = $this->getAvailableRoles();
        if ($this->objectUUID !== '') {
            $client->readDataByUUID($this->objectUUID);
        }

        ChangelogService::displayHistoryButton($this, 'oidc-client', 'oidc_clients', !empty($this->objectUUID), array('uuid' => $this->objectUUID));

        // show form
        $form = new FormPresenter(
            'adm_oidc_client_edit_form',
            'modules/oidc_client.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('uuid' => $this->objectUUID, 'mode' => 'save_oidc')),
            $this
        );

        $OIDCService = new OIDCService($gDb, $gCurrentUser);
        $form->addCustomContent(
            'sso_oidc_sso_staticsettings',
            $gL10n->get('SYS_SSO_STATIC_SETTINGS'),
            '',
            array('data' => $OIDCService->getStaticSettings())
        );

        $form->addCheckbox(
            'ocl_enabled',
            $gL10n->get('SYS_ENABLED'),
            $client->getValue('ocl_enabled'),
            array()
        );
        $form->addInput(
            'ocl_client_name',
            $gL10n->get('SYS_SSO_CLIENT_NAME'),
            $client->getValue('ocl_client_name'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_CLIENT_NAME_DESC'))
        );
        $form->addInput(
            'ocl_client_id',
            $gL10n->get('SYS_SSO_CLIENT_ID'),
            $client->getValue('ocl_client_id'),
            array('maxLength' => 250, 'property' => FormPresenter::FIELD_REQUIRED, 'helpTextId' => $gL10n->get('SYS_SSO_CLIENT_ID_DESC'))
        );

        // TODO: Hide client secret from user -> Only allow changing, but not copying!
        $noClientSecretYet = ($client->isNewRecord() || empty($client->getValue('ocl_client_secret')));
        $cancelRecreateButton = '<button id="cancel_recreate_client_secret" name="cancel_recreate_client_secret" type="button" class="btn focus-ring btn-secondary" style="padding: 0px 4px; flex-shrink: 0;">' . 
                '<i class="bi bi-x-circle" data-bs-toggle="tooltip" title=""></i>' . 
                $gL10n->get('SYS_CANCEL') . 
            '</button>';
        $clientSecretContent = '<div style="display: flex; align-items: center; gap: 8px;" id="client_secret_not_shown">' . 
                '<span style="flex: 1" id="client_passwd_label"><em>' . 
                    $gL10n->get('SYS_SSO_CLIENT_SECRET_HIDDE') . 
                    '</em></span>' . 
                '<button id="recreate_client_secret" name="recreate_client_secret" type="button" class="btn focus-ring btn-secondary" style="padding: 0px 4px; flex-shrink: 0;">' . 
                    '<i class="bi bi-arrow-clockwise" data-bs-toggle="tooltip" title=""></i>' . 
                    $gL10n->get('SYS_SSO_CLIENT_SECRET_RECREATE') . 
                '</button>' . 
            '</div>' . 
            '<div style="display:flex; align-itens: center; gap: 8px;" id="client_secret_shown">' . 
            '<input id="new_ocl_client_secret" name="new_ocl_client_secret" class="form-control focus-ring hidden copy-container" type="text" value="" maxlength="250" style="flex: 1" >' .
                ($noClientSecretYet ? '' : $cancelRecreateButton) . 
            '</div>';
        $this->addJavascript('
            function generateClientSecret(length = 32) {
                const array = new Uint8Array(length);
                window.crypto.getRandomValues(array);
                return btoa(String.fromCharCode(...array)).slice(0, length);
            }
            $("#recreate_client_secret").on("click", function (e) {
                e.preventDefault();
                $("#new_ocl_client_secret").prop("disabled", false).val(generateClientSecret(32));
                $("#client_secret_shown").show();
                $("#client_secret_not_shown").hide();
            });

            $("#cancel_recreate_client_secret").on("click", function (e) {
                e.preventDefault();
                $("#new_ocl_client_secret").val("").prop("disabled", true);
                $("#client_secret_shown").hide();
                $("#client_secret_not_shown").show();
            });
            ' . ($noClientSecretYet ? '$("#recreate_client_secret").click();' : '$("#cancel_recreate_client_secret").click();') . '
            ', true);

        $form->addCustomContent(
            'ocl_client_secret',
            $gL10n->get('SYS_SSO_CLIENT_SECRET'),
            $clientSecretContent,
            array('helpTextId' => $gL10n->get('SYS_SSO_CLIENT_SECRET_DESC'), 'class'=> '')
        );
        $form->addInput(
            'new_ocl_client_secret',
            $gL10n->get('SYS_SSO_CLIENT_SECRET'),
            '',
            array('maxLength' => 250, 'helpTextId' => $gL10n->get('SYS_SSO_CLIENT_SECRET_DESC'))
        );
        $form->addInput(
            'ocl_redirect_uri',
            $gL10n->get('SYS_SSO_REDIRECT_URI'),
            $client->getValue('ocl_redirect_uri'),
            array('type' => 'url', 'maxLength' => 2000, 'helpTextId' => $gL10n->get('SYS_SSO_REDIRECT_URI_DESC'))
        );
        // TODO: Grant Types, Scopes



        // TAB: 
    
        $useridFields = [
            ['usr_id', $gL10n->get('SYS_SSO_USERID_ID') . ' - usr_id', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['usr_uuid',  $gL10n->get('SYS_SSO_USERID_UUID') . ' - usr_uuid', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['usr_login_name', $gL10n->get('SYS_SSO_USERID_LOGIN') . ' - usr_login_name', $gL10n->get('SYS_SSO_USERID_FIELDS')],
            ['EMAIL', $gL10n->get('SYS_EMAIL') . ' - EMAIL', $gL10n->get('SYS_SSO_USERID_FIELDS')],
        ];
        $form->addSelectBox(
            'ocl_userid_field',
            $gL10n->get('SYS_SSO_USERID_FIELD'),
            $useridFields,
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'defaultValue' => $client->getValue('ocl_userid_field'),
                'multiselect' => false,
                'helpTextId' => 'SYS_SSO_USERID_FIELD_DESC'
            )
        );
        // Make sure the 'openid' scope is always selected (required by the OIDC standard)
        $scopes = ['profile', 'email', 'address', 'phone', 'groups', 'custom'];
        $dbvalue = $client->getValue('ocl_scope');
        $defaultValue = explode(' ', $client->getValue('ocl_scope'));
        $defaultValue = preg_split('/[,;\s]+/', trim($client->getValue('ocl_scope')));
        $form->addSelectBox(
            'ocl_scope',
            $gL10n->get('SYS_SSO_CLIENT_SCOPES'),
            array_combine($scopes, $scopes),
            array(
                'property' => FormPresenter::FIELD_DEFAULT,
                'defaultValue' => array_merge(explode(' ', $client->getValue('ocl_scope'))),
                'multiselect' => true,
                'helpTextId' => 'SYS_SSO_CLIENT_SCOPES_DESC'
            )
        );


        $userFields = $useridFields;
        $userFields[] = ['fullname', $gL10n->get('SYS_NAME') . ' - fullname', $gL10n->get('SYS_BASIC_DATA')];
        foreach ($gProfileFields->getProfileFields() as $field) {
            if ($field->getValue('usf_hidden') == 0) {
                $fieldId = $field->getValue('usf_name_intern');
                $fieldValue = addslashes($field->getValue('usf_name')) . ' - ' . strtolower($fieldId);
                $fieldCat = $gL10n->translateIfTranslationStrId($field->getValue('cat_name'));
                $userFields[] =  [$fieldId, $fieldValue, $fieldCat];
            }
        }
        $userFields[] = ['roles', $gL10n->get('SYS_ROLES') . ' - roles', $gL10n->get('SYS_ROLES')];

        $js = $this->createSSOEditFormJS( $userFields, $client->getFieldMapping(), "fieldsmap");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#fieldsmap_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("fieldsmap_Admidio", '', '');
        $form->addCustomContent("fieldsmap_sso", '', '');

        $form->addCheckbox(
            'sso_fields_no_other',
            $gL10n->get('SYS_SSO_ATTRIBUTES_NOOTHER'),
            $client->getFieldMappingCatchall(),
            array('helpTextId' => '')
        );

        
        $js = $this->createSSOEditFormJS($allRolesSet, $client->getRoleMapping(), "rolesmap");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#rolesmap_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("rolesmap_Admidio", '', '');
        $form->addCustomContent("rolesmap_sso", '', '');

        $form->addCheckbox(
            'sso_roles_all_other',
            $gL10n->get('SYS_SSO_ROLES_ALLOTHER'),
            $client->getRoleMappingCatchall(),
            array('helpTextId' => '')
        );

        // Add JS code for the move UP/DOWN "buttons":
        $this->addJavascript('
                $(document).on("click", ".admidio-move-row-up", function(){
                    let row = $(this).closest("tr");
                    let prevRow = row.prev("tr");
                    if (prevRow.length) {
                        row.insertBefore(prevRow);
                    }
                });
                $(document).on("click", ".admidio-move-row-down", function(){
                    let row = $(this).closest("tr");
                    let nextRow = row.next();
                    if (nextRow.length) {
                        row.insertAfter(nextRow);
                    }
                });
                $(document).on("click", ".admidio-delete", function(){
                    let row = $(this).closest("tr").fadeOut(300, function() {
                        $(this).remove();
                    });
                });
                ', true);

        // Add JS code to set the oidc field input to the Admidio field name if no name was set yet


        $form->addSelectBox(
            'sso_roles_access',
            $gL10n->get('SYS_SSO_ROLES'),
            $allRolesSet,
            array(
                'property' => FormPresenter::FIELD_DEFAULT,
                'defaultValue' => $client->getAccessRolesIds(),
                'multiselect' => true,
                'helpTextId' => 'SYS_SSO_ROLES_DESC'
            )
        );
    
        
        $form->addSubmitButton(
            'adm_button_save', 
            $gL10n->get('SYS_SAVE'), 
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3'));



        $this->smarty->assign('nameUserCreated', $client->getNameOfCreatingUser());
        $this->smarty->assign('timestampUserCreated', $client->getValue('ocl_timestamp_create'));
        $this->smarty->assign('nameLastUserEdited', $client->getNameOfLastEditingUser());
        $this->smarty->assign('timestampLastUserEdited', $client->getValue('ocl_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }


    /** 
     * Display a toggle to enable/disable a client (via a json call).
     * @param  $name
     */
    protected function generateEnableLink(SSOClient $client) {
        global $gL10n;
        $enabled = $client->isEnabled();
        $uuid = $client->getValue($client->getColumnPrefix() . '_uuid');

        $clientToggleURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'enable', 'uuid' => $uuid, 'enabled' => !$enabled));
        $actions = '
        <a href="#" 
           class="admidio-icon-link toggle-client-status" 
           data-uuid="' . $uuid . '" 
           data-enabled="' . ($enabled ? '1' : '0') . '" 
           title="' . $gL10n->get($enabled ? 'SYS_DISABLE' : 'SYS_ENABLE') . '">
           <i class="bi bi-toggle-' . ($enabled ? 'on' : 'off') . ' fs-2"></i>
        </a>';
        return $actions;
    } 

    /**
     * Create the list of SAML and OIDC clients to show to the user.
     * @throws Exception|\Smarty\Exception
     */
    public function createList(): void
    {
        global $gCurrentSession, $gL10n, $gDb, $gCurrentUser;

        $this->setHtmlID('adm_sso_clients_configuration');
        $this->setHeadline($gL10n->get('SYS_SSO_CLIENT_ADMIN'));


        // link to preferences
        $this->addPageFunctionsMenuItem(
            'menu_item_sso_preferences',
            $gL10n->get('SYS_SETTINGS'),
            ADMIDIO_URL . FOLDER_MODULES . '/preferences.php?panel=sso',
            'bi-gear-fill'
        );

        // link to add new client (SAML 2.0 or OIDC is selectable)
        $this->addPageFunctionsMenuItem(
            'menu_item_sso_new_client_saml',
            $gL10n->get('SYS_SSO_CLIENT_ADD_SAML'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'edit_saml')),
            'bi-plus-circle-fill'
        );

        // link to add new client (SAML 2.0 or OIDC is selectable)
        $this->addPageFunctionsMenuItem(
            'menu_item_sso_new_client_oidc',
            $gL10n->get('SYS_SSO_CLIENT_ADD_OIDC'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'edit_oidc')),
            'bi-plus-circle-fill'
        );

        ChangelogService::displayHistoryButton($this, 'sso-clients', array('saml_clients', 'oidc_clients'));


        $this->addHtml('<p>' . $gL10n->get('SYS_SSO_CLIENT_ADMIN_DESC') . '</p>');

        /* ****************************************************/  
        // SAML 2.0 clients
        /* ****************************************************/  
        $this->addHtml('<h3 class="admidio-content-subheader">' . $gL10n->get('SYS_SSO_CLIENTS_SAML') . '</h3>');
    
        $table = new \HtmlTable('adm_saml_clients_table', $this, true, false);
    
        $table->addRowHeadingByArray(array(
            $gL10n->get('SYS_ENABLED'),
            $gL10n->get('SYS_SSO_CLIENT_NAME'),
            $gL10n->get('SYS_SSO_CLIENT_ID'),
            $gL10n->get('SYS_SSO_ACS_URL'),
            $gL10n->get('SYS_SSO_ROLES'),
            ''
        ));
    
        $table->setMessageIfNoRowsFound('SYS_SSO_NO_SAML_CLIENTS_FOUND');
    
        $table->disableDatatablesColumnsSort(array(3, 6));
        $table->setDatatablesColumnsNotHideResponsive(array(6));
        // special settings for the table
    
    
        $SAMLService = new SAMLService($gDb, $gCurrentUser);
        $templateClientNodes = array();
        foreach ($SAMLService->getUUIDs() as $clientUUID) {
            $clientEditURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'edit_saml', 'uuid' => $clientUUID));
            $client = new SAMLClient($gDb);
            $client->readDataByUuid($clientUUID);
            $templateClient = array();
            $templateClient[] = $this->generateEnableLink($client);
            $templateClient[] = '<a href="' . $clientEditURL . '">' . $client->getValue('smc_client_name') . '</a>';
            $templateClient[] = $client->getValue('smc_client_id');
            $templateClient[] = $client->getValue('smc_acs_url');
            $templateClient[] = implode(', ', $client->getAccessRolesNames());
            //$templateClient[] = $client->getValue('create_name');

            $actions = '';
            // add link to edit SAML client
            $actions .= '<a class="admidio-icon-link" href="' . $clientEditURL . '">' .
                    '<i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SSO_EDIT_SAML_CLIENT') . '"></i></a>';
            
            // add link to delete SAML client
            $actions .= '<a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                    data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($client->readableName())) . '"
                    data-href="callUrlHideElement(\'adm_saml_client_' . $clientUUID . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'delete_saml', 'uuid' => $clientUUID)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                    <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SSO_CLIENT_DELETE') . '"></i>
                </a>';
            $templateClient[] = $actions;

            $table->addRowByArray($templateClient, 'adm_saml_client_' . $clientUUID, array('nobr' => 'true'));
        }
    
        // add table to the form
        $this->addHtml(html: $table->show());

        


        /* ****************************************************/  
        // OIDC clients
        /* ****************************************************/
        $this->addHtml('<h3 class="admidio-content-subheader">' . $gL10n->get('SYS_SSO_CLIENTS_OIDC') . '</h3>');
    
        $table = new \HtmlTable('adm_oidc_clients_table', $this, true, false);
    
        $table->addRowHeadingByArray(array(
            $gL10n->get('SYS_ENABLED'),
            $gL10n->get('SYS_SSO_CLIENT_NAME'),
            $gL10n->get('SYS_SSO_CLIENT_ID'),
            $gL10n->get('SYS_SSO_REDIRECT_URI'),
            $gL10n->get('SYS_SSO_ROLES'),
            ''
        ));
    
        $table->setMessageIfNoRowsFound('SYS_SSO_NO_OIDC_CLIENTS_FOUND');
    
        $table->disableDatatablesColumnsSort(array(3, 6));
        $table->setDatatablesColumnsNotHideResponsive(array(6));
        // special settings for the table
    
    
        $OIDCService = new OIDCService($gDb, $gCurrentUser);
        $templateClientNodes = array();
        foreach ($OIDCService->getUUIDs() as $clientUUID) {
            $clientEditURL = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'edit_oidc', 'uuid' => $clientUUID));
            $client = $OIDCService->createClientObject($clientUUID);
            $client->readDataByUuid($clientUUID);
            $templateClient = array();
            $templateClient[] = $this->generateEnableLink($client);
            $templateClient[] = '<a href="' . $clientEditURL . '">' . $client->getValue('ocl_client_name') . '</a>';
            $templateClient[] = $client->getValue('ocl_client_id');
            $templateClient[] = $client->getValue('ocl_redirect_uri');
            $templateClient[] = implode(', ', $client->getAccessRolesNames());
            //$templateClient[] = $client->getValue('create_name');

            $actions = '';
            // add link to edit SAML client
            $actions .= '<a class="admidio-icon-link" href="' . $clientEditURL . '">' .
                    '<i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SSO_EDIT_OIDC_CLIENT') . '"></i></a>';
            
            // add link to delete SAML client
            $actions .= '<a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                    data-message="' . $gL10n->get('SYS_DELETE_ENTRY', array($client->readableName())) . '"
                    data-href="callUrlHideElement(\'adm_oidc_client_' . $clientUUID . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'delete_oidc', 'uuid' => $clientUUID)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                    <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_SSO_CLIENT_DELETE') . '"></i>
                </a>';
            $templateClient[] = $actions;

            $table->addRowByArray($templateClient, 'adm_saml_client_' . $clientUUID, array('nobr' => 'true'));
        }

        // Add JS for toggling clients (enabled/disabled)
        $this->addJavascript("
            $('.toggle-client-status').on('click', function (e) {
              e.preventDefault();
          
              var \$link = $(this);
              var \$icon = \$link.find('i');
              var currentlyEnabled = \$link.data('enabled') === 1 || \$link.data('enabled') === '1';
              var newEnabled = currentlyEnabled ? 0 : 1;
          
              $.get('clients.php?mode=enable', {
                uuid: \$link.data('uuid'),
                enabled: newEnabled
              })
              .done(function (response) {
                var data = typeof response === 'string' ? JSON.parse(response) : response;
          
                if (data.success) {
                  // Toggle icon class
                  \$icon.removeClass('bi-toggle-' + (currentlyEnabled ? 'on' : 'off'))
                       .addClass('bi-toggle-' + (currentlyEnabled ? 'off' : 'on'));
          
                  // Update tooltip title (you might want to localize this)
                  \$link.attr('title', currentlyEnabled ? 'Enable' : 'Disable');
                  \$link.tooltip('dispose').tooltip(); // Refresh tooltip if Bootstrap tooltip is used
          
                  // Update data-enabled attribute
                  \$link.data('enabled', newEnabled);
                } else {
                  alert('Update failed: ' + (data.message || 'Unknown error'));
                }
              })
              .fail(function () {
                alert('Server communication error.');
              });
            });
          ", true);
          
        // add table to the form
        $this->addHtml(html: $table->show());

    }
}
