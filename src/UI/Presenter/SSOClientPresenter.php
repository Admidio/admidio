<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\SAMLService;
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

    protected function createSAMLEditFormJS(array $available, array $config = [], $type = "saml_fields") {
        global $gL10n;

        $jsInit = '';
        $js = '$("#saml_fields_tbody").sortable();';
        
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
            function addColumn_' . $type . '(samlField = "", admidioField = "")
            {
                var category = "";
                var table = document.getElementById("' . $type . '_tbody");
                var newTableRow = table.insertRow(fieldNumberIntern_' . $type . ');
                newTableRow.setAttribute("id", "row" + (fieldNumberIntern_' . $type . '))

                // New column for selecting the field
                var newCellAdmidio = newTableRow.insertCell(-1);
                htmlAdmidioValues = "<select class=\"form-control admidio-field-select\"  size=\"1\" id=\"admidio_' . $type . '" + fieldNumberIntern_' . $type . ' + "\" class=\"List_' . $type . '\" name=\"Admidio_' . $type . '[]\">";
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
                htmlAdmidioValues += "</select>";
                newCellAdmidio.innerHTML = htmlAdmidioValues;
        
                // New column for selecting the mapped name
                var newCellSAML = newTableRow.insertCell(-1);
                htmlMappedName = "<input type=\"text\" class=\"form-control saml-field-input\" id=\"saml_' . $type . '" + fieldNumberIntern_' . $type . ' + "\" name=\"SAML_' . $type . '[]\" value=\"" + samlField + "\" size=\"30\" maxlength=\"250\">";
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
        foreach ($config as $samlField => $admidioField) {
            $js .= '
            addColumn_' . $type . '("' . $samlField . '", "' . $admidioField . '");';
        }

        return array('js' => $js, 'jsInit' => $jsInit);
    }


    /**
     * Create the data for the edit form of a SAML client.
     * @throws Exception
     */
    public function createSAMLEditForm(): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gProfileFields;

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
        }

        ChangelogService::displayHistoryButton($this, 'saml-client', 'saml_clients', !empty($this->objectUUID), array('uuid' => $this->objectUUID));

        // show form
        $form = new FormPresenter(
            'adm_saml_client_edit_form',
            'modules/saml_client.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('uuid' => $this->objectUUID, 'mode' => 'save_saml')),
            $this
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

        $js = $this->createSAMLEditFormJS( $userFields, $client->getFieldMapping(), "saml_fields");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#saml_fields_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("Admidio_saml_fields", '', '');
        $form->addCustomContent("SAML_saml_fields", '', '');

        $form->addCheckbox(
            'saml_fields_all_other',
            $gL10n->get('SYS_SSO_SAML_ATTRIBUTES_ALLOTHER'),
            $client->getFieldMappingCatchall(),
            array('helpTextId' => '')
        );

        
        $js = $this->createSAMLEditFormJS($allRolesSet, $client->getRoleMapping(), "saml_roles");
        $this->addJavascript($js['jsInit'], false);
        $this->addJavascript($js['js'], true);
        $this->addJavascript('$("#saml_roles_tbody").sortable({cancel: ".nosort, input, select, .admidio-move-row-up, .admidio-move-row-down"});', true);

        // Add dummy elements for the mapping arrays, otherwise the form processing function will complain!!!
        $form->addCustomContent("Admidio_saml_roles", '', '');
        $form->addCustomContent("SAML_saml_roles", '', '');

        $form->addCheckbox(
            'saml_roles_all_other',
            $gL10n->get('SYS_SSO_SAML_ROLES_ALLOTHER'),
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
            'saml_roles_access',
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
/*        $this->addPageFunctionsMenuItem(
            'menu_item_sso_new_client_oidc',
            $gL10n->get('SYS_SSO_CLIENT_ADD_OIDC'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/sso/clients.php', array('mode' => 'edit_oidc')),
            'bi-plus-circle-fill'
        );
*/
        ChangelogService::displayHistoryButton($this, 'sso-clients', array('saml_clients', 'oauth_clients'));


        $this->addHtml('<p>' . $gL10n->get('SYS_SSO_CLIENT_ADMIN_DESC') . '</p>');

        /* ****************************************************/  
        // SAML 2.0 clients
        /* ****************************************************/  
        $this->addHtml('<h3 class="admidio-content-subheader">' . $gL10n->get('SYS_SSO_CLIENTS_SAML') . '</h3>');
    
        $table = new \HtmlTable('adm_saml_clients_table', $this, true, false);
    
    //    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'right'));
    
        $table->addRowHeadingByArray(array(
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
        /* **************************************************** 
        $this->addHtml('<h3 class="admidio-content-subheader">' . $gL10n->get('SYS_SSO_CLIENTS_OIDC') . '</h3>');

        $table = new \HtmlTable('adm_saml_clients_table', $this, true, true);
        $table->setMessageIfNoRowsFound('SYS_SSO_NO_OIDC_CLIENTS_FOUND');

        $this->addHtml($table->show());
        */


    }
}
