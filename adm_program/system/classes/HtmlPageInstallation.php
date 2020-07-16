<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2019 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an Admidio specific complete html page
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. It
 * also provides some methods to easily add new html data to the page. The generated
 * page will automatically integrate the chosen theme. You can optional disable the
 * integration of the theme files.
 *
 * **Code example**
 * ```
 * // create a simple html page with some text
 * $page = new HtmlPage();
 * $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 * ```
 */

class HtmlPageInstallation extends HtmlPage
{
    /**
     * Constructor creates the page object and initialized all parameters
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag.
     */
    public function __construct($headline = '')
    {
        parent::__construct($headline);

        // initialize php template engine smarty
        $this->addTemplateDir(ADMIDIO_PATH . FOLDER_INSTALLATION . '/templates/', 'inst');
    }

    /**
     * This method will set all variables for the Smarty engine and than send the whole html
     * content also to the template engine which will generate the html page.
     * Call this method if you have finished your page layout.
     */
    public function show()
    {
        global $gDebug, $gMenu, $gCurrentOrganization, $gCurrentUser, $gValidLogin, $gL10n, $gSettingsManager, $gSetCookieForDomain;

        $this->assign('additionalHeaderData', $this->getHtmlAdditionalHeader());
        $this->assign('title', $this->title);
        $this->assign('headline', $this->headline);
        $this->assign('urlPreviousPage', $this->urlPreviousPage);
        $this->assign('organizationName', $gCurrentOrganization->getValue('org_longname'));
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

        $this->display('installation.tpl');
    }
}
