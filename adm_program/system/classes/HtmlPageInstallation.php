<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an Admidio specific complete html page specialized for installation and update process
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. It
 * also provides some methods to easily add new html data to the page. The generated
 * page will automatically integrate the chosen theme. You can optional disable the
 * integration of the theme files. Additional to the basic class HtmlPage this class only assigns
 * variables that are in installation and update mode available. There is also a method that will
 * easily creates a message page.
 *
 * **Code example**
 * ```
 * // create a simple html page with some text
 * $page = new HtmlPageInstallation('admidio-example');
 * $page->addTemplateFile('update.tpl');
 * $page->setUpdateModus();
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 *
 * // create a message
 * $page = new HtmlPageInstallation();
 * $page->setUpdateModus();
 * $page->showMessage('error', 'Message', 'Some error message.', $gL10n->get('SYS_OVERVIEW'), 'fa-home', ADMIDIO_URL . '/adm_program/overview.php');
 * ```
 */
class HtmlPageInstallation extends HtmlPage
{
    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $id       Id of the page. This id will be set in the html <body> tag.
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag
     *                         and also set the title of the page.
     */
    public function __construct($id, $headline = '')
    {
        parent::__construct($id, $headline);

        // initialize php template engine smarty
        $this->addTemplateDir(ADMIDIO_PATH . FOLDER_INSTALLATION . '/templates/', 'inst');

        // if no modus set then set installation modus
        if ($headline === '') {
            $this->setInstallationModus();
        }
    }

    /**
     * Internal method that will assign a default set of variables to the Smarty template engine.
     * These variables are available in all installation and update template files.
     */
    private function assignDefaultVariables()
    {
        global $gDebug, $gSettingsManager, $gValidLogin, $gL10n;

        $urlImprint = '';
        $urlDataProtection = '';

        $this->assign('additionalHeaderData', $this->getHtmlAdditionalHeader());
        $this->assign('id', $this->id);
        $this->assign('title', $this->title);
        $this->assign('headline', $this->headline);
        $this->assign('urlAdmidio', ADMIDIO_URL);
        $this->assign('urlTheme', THEME_URL);
        $this->assign('javascriptContent', $this->javascriptContent);
        $this->assign('javascriptContentExecuteAtPageLoad', $this->javascriptContentExecute);

        $this->assign('validLogin', $gValidLogin);
        $this->assign('debug', $gDebug);

        $this->assign('printView', $this->printView);
        $this->assign('templateFile', $this->templateFile);
        $this->assign('content', $this->pageContent);

        // add translation object
        $this->assign('l10n', $gL10n);

        // add imprint and data protection
        if(is_object($gSettingsManager)) {
            if ($gSettingsManager->has('system_url_imprint') && strlen($gSettingsManager->getString('system_url_imprint')) > 0) {
                $urlImprint = $gSettingsManager->getString('system_url_imprint');
            }
            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
                $urlDataProtection = $gSettingsManager->getString('system_url_data_protection');
            }
        }
        $this->assign('urlImprint', $urlImprint);
        $this->assign('urlDataProtection', $urlDataProtection);
    }

    /**
     * Set the form in the installation modus. Therefore headline and title will be changed.
     * This is the default modus and will be set automatically if not modus is set in the calling code.
     */
    public function setInstallationModus()
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_INSTALLATION');
        $this->headline = $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * Set the form in the update modus. Therefore headline and title will be changed.
     */
    public function setUpdateModus()
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_UPDATE');
        $this->headline = $gL10n->get('INS_UPDATE_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * This method will set all variables for the Smarty engine and than send the whole html
     * content also to the template engine which will generate the html page.
     * Call this method if you have finished your page layout.
     */
    public function show()
    {
        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        $this->assignDefaultVariables();
        $this->display('index.tpl');
    }

    /**
     * This Method creates a message page that will show a simple message text with a button
     * that will navigate to a custom url.
     * @param string $outputMode     Defines the style of the html message. The values are:
     *                               **error** Shows a red box with the message text.
     *                               **success** Shows a green box with the message text.
     * @param string $headline       The headline of the message page.
     * @param string $text           The text of the message.
     * @param string $buttonText     The text of the button which will navigate to the **$destinationUrl**
     * @param string $buttonIcon     The icon of the button which will navigate to the **$destinationUrl**
     * @param string $destinationUrl A url to which the user should navigate if he click on the button.
     */
    public function showMessage($outputMode, $headline, $text, $buttonText, $buttonIcon, $destinationUrl)
    {
        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        $this->assign('outputMode', $outputMode);
        $this->assign('messageHeadline', $headline);
        $this->assign('messageText', $text);
        $this->addTemplateFile('message.tpl');

        // add form with submit button
        $form = new HtmlForm('installation-form', $destinationUrl);
        $form->addSubmitButton('next_page', $buttonText, array('icon' => $buttonIcon));
        $this->addHtml($form->show());

        $this->assignDefaultVariables();
        $this->display('index.tpl');
        exit();
    }
}
