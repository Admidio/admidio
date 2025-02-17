<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Throwable;

/**
 * @brief Creates an Admidio specific complete html page specialized for installation and update process
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. It
 * also provides some methods to easily add new html data to the page. The generated
 * page will automatically integrate the chosen theme. You can optional disable the
 * integration of the theme files. Additional to the basic class PagePresenter this class only assigns
 * variables that are in installation and update mode available. There is also a method that will
 * easily create a message page.
 *
 * **Code example**
 * ```
 * // create a simple html page with some text
 * $page = new InstallationPresenter('admidio-example');
 * $page->addTemplateFile('update.tpl');
 * $page->setUpdateModus();
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 *
 * // create a message
 * $page = new Installation();
 * $page->setUpdateModus();
 * $page->showMessage('error', 'Message', 'Some error message.', $gL10n->get('SYS_OVERVIEW'), 'bi-house-door-fill', ADMIDIO_URL . '/adm_program/overview.php');
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InstallationPresenter extends PagePresenter
{
    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $id ID of the page. This id will be set in the html <body> tag.
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag
     *                         and also set the title of the page.
     * @throws Exception
     */
    public function __construct(string $id, string $headline = '')
    {
        $this->id = $id;

        if ($headline !== '') {
            $this->setHeadline($headline);
        }

        // initialize php template engine smarty
        $this->smarty = $this->createSmartyObject();
        $this->smarty->addTemplateDir(ADMIDIO_PATH . FOLDER_INSTALLATION . '/templates/', 'inst');
        $this->assignBasicSmartyVariables();

        // if no modus set then set installation modus
        if ($headline === '') {
            $this->setInstallationModus();
        }
    }

    /**
     * Internal method that will assign a default set of variables to the Smarty template engine.
     * These variables are available in all installation and update template files.
     * @throws Exception
     */
    private function assignBasicSmartyVariables(): void
    {
        global $gDebug, $gSettingsManager, $gValidLogin, $gL10n;

        $urlImprint = '';
        $urlDataProtection = '';

        $this->smarty->assign('id', $this->id);
        $this->smarty->assign('title', $this->title);
        $this->smarty->assign('headline', $this->headline);
        $this->smarty->assign('urlAdmidio', ADMIDIO_URL);
        $this->smarty->assign('urlTheme', THEME_URL);

        $this->smarty->assign('validLogin', $gValidLogin);
        $this->smarty->assign('debug', $gDebug);

        $this->smarty->assign('printView', $this->printView);

        // add translation object
        $this->smarty->assign('l10n', $gL10n);

        // add imprint and data protection
        if(is_object($gSettingsManager)) {
            if ($gSettingsManager->has('system_url_imprint') && strlen($gSettingsManager->getString('system_url_imprint')) > 0) {
                $urlImprint = $gSettingsManager->getString('system_url_imprint');
            }
            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
                $urlDataProtection = $gSettingsManager->getString('system_url_data_protection');
            }
        }
        $this->smarty->assign('urlImprint', $urlImprint);
        $this->smarty->assign('urlDataProtection', $urlDataProtection);
    }

    /**
     * Set the form in the installation modus. Therefore, headline and title will be changed.
     * This is the default modus and will be set automatically if not modus is set in the calling code.
     * @throws Exception
     */
    public function setInstallationModus(): void
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_INSTALLATION');
        $this->headline = $gL10n->get('INS_INSTALLATION_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * Set the form in the update modus. Therefore, headline and title will be changed.
     * @throws Exception
     */
    public function setUpdateModus(): void
    {
        global $gL10n;

        $this->title = $gL10n->get('INS_UPDATE');
        $this->headline = $gL10n->get('INS_UPDATE_VERSION', array(ADMIDIO_VERSION_TEXT));
    }

    /**
     * This method will set all variables for the Smarty engine and then send the whole html
     * content also to the template engine which will generate the html page.
     * Call this method if you have finished your page layout.
     * @throws Exception
     */
    public function show(): void
    {
        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        $this->smarty->assign('additionalHeaderData', $this->getHtmlAdditionalHeader());
        $this->smarty->assign('javascriptContent', $this->javascriptContent);
        $this->smarty->assign('javascriptContentExecuteAtPageLoad', $this->javascriptContentExecute);
        $this->smarty->assign('templateFile', $this->templateFile);
        $this->smarty->assign('content', $this->pageContent);
        $this->smarty->assign('rssFeeds', $this->rssFiles);
        $this->smarty->assign('cssFiles', $this->cssFiles);
        $this->smarty->assign('javascriptFiles', $this->jsFiles);
        try {
            $this->smarty->display('index.tpl');
        } catch (Throwable $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * This Method creates a message page that will show a simple message text with a button
     * that will navigate to a custom url.
     * @param string $outputMode Defines the style of the html message. The values are:
     *                               **error** Shows a red box with the message text.
     *                               **success** Shows a green box with the message text.
     * @param string $headline The headline of the message page.
     * @param string $text The text of the message.
     * @param string $buttonText The text of the button which will navigate to the **$destinationUrl**
     * @param string $buttonIcon The icon of the button which will navigate to the **$destinationUrl**
     * @param string $destinationUrl An url to which the user should navigate if he clicks on the button.
     * @throws Exception
     */
    public function showMessage(string $outputMode, string $headline, string $text, string $buttonText, string $buttonIcon, string $destinationUrl): void
    {
        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        try {
            $this->smarty->assign('outputMode', $outputMode);
            $this->smarty->assign('messageHeadline', $headline);
            $this->smarty->assign('messageText', $text);
            $this->addTemplateFile('message.tpl');
            $this->smarty->assign('templateFile', $this->templateFile);
            $this->smarty->assign('content', $this->pageContent);
            $this->smarty->assign('buttonIcon', $buttonIcon);
            $this->smarty->assign('buttonText', $buttonText);
            $this->smarty->assign('destinationUrl', $destinationUrl);

            $this->smarty->display('index.tpl');
        } catch (Throwable $exception) {
            throw new Exception($exception->getMessage());
        }
        exit();
    }
}
