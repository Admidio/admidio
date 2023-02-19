<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an Admidio specific complete html page with the template engine Smarty.
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. The class is derived
 * from the Smarty class. It provides method to add new html data to the page and also set or
 * choose the necessary template files. The generated page will automatically integrate the
 * chosen theme. You can also create a html reduces page without menu header and footer
 * informations.
 *
 * **Code example**
 * ```
 * // create a simple html page with some text
 * $page = new HtmlPage('admidio-example');
 * $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 * ```
 */
class HtmlPage extends \Smarty
{
    /**
     * @var string The id of the html page that will be set within the <body> tag.
     */
    protected $id = '';
    /**
     * @var string The title for the html page and the headline for the Admidio content.
     */
    protected $title = '';
    /**
     * @var string Additional header that could not be set with the other methods. This content will be add to head of html page without parsing.
     */
    protected $header = '';
    /**
     * @var string The main headline for the html page.
     */
    protected $headline = '';
    /**
     * @var string Contains the custom html of the current page. This will be added to the default html of each page.
     */
    protected $pageContent = '';
    /**
     * @var MenuNode An object that represents all functions of the current page that should be shown in the menu of this page
     */
    protected $menuNodePageFunctions;
    /**
     * @var array<int,string> An array with all necessary cascading style sheets files for the html page.
     */
    protected $cssFiles = array();
    /**
     * @var array<int,string> An array with all necessary javascript files for the html page.
     */
    protected $jsFiles = array();
    /**
     * @var array<int|string,string> An array with all necessary rss files for the html page.
     */
    protected $rssFiles = array();
    /**
     * @var bool A flag that indicates if the page should be styled in print mode then no colors will be shown
     */
    protected $printView = false;
    /**
     * @var string Contains the custom javascript of the current page. This will be added to the header part of the page.
     */
    protected $javascriptContent = '';
    /**
     * @var string Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.
     */
    protected $javascriptContentExecute = '';
    /**
     * @var bool If set to true then a page without header menue and sidebar menu will be created. The main template file will be index_inline.tpl
     */
    protected $modeInline = false;
    /**
     * @var string Name of an additional template file that should be loaded within the current page.
     */
    protected $templateFile = '';
    /**
     * @var string Flag that will be responsible of a back button with the url to the previous page will be shown.
     */
    protected $showBackLink = '';

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $id       Id of the page. This id will be set in the html <body> tag.
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag
     *                         and also set the title of the page.
     */
    public function __construct($id, $headline = '')
    {
        global $gSettingsManager;

        $this->menuNodePageFunctions = new MenuNode('admidio-menu-page-functions', $headline);

        $this->id = $id;
        $this->showBackLink = true;

        if ($headline !== '') {
            $this->setHeadline($headline);
        }

        parent::__construct();

        // initialize php template engine smarty
        if (defined('THEME_PATH')) {
            $this->setTemplateDir(THEME_PATH . '/templates/');
        }

        $this->setCacheDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/cache/');
        $this->setCompileDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/compile/');
        $this->addPluginsDir(ADMIDIO_PATH . '/adm_program/system/smarty-plugins/');

        if (is_object($gSettingsManager) && $gSettingsManager->has('system_browser_update_check')
        && $gSettingsManager->getBool('system_browser_update_check')) {
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/browser-update/browser-update.js');
        }
    }

    /**
     * Adds a cascading style sheets file to the html page.
     * @param string $cssFile The url with filename or the relative path starting with **adm_program** of the css file.
     */
    public function addCssFile($cssFile)
    {
        if (!in_array($cssFile, $this->cssFiles, true)) {
            if (str_starts_with($cssFile, 'http')) {
                $this->cssFiles[] = $cssFile;
            } else {
                $this->cssFiles[] = $this->getDebugOrMinFilepath($cssFile);
            }
        }
    }

    /**
     * Adds a RSS file to the html page.
     * @param string $rssFile The url with filename of the rss file.
     * @param string $title   (optional) Set a title. This is the name of the feed and will be shown when adding the rss feed.
     */
    public function addRssFile($rssFile, $title = '')
    {
        if ($title !== '') {
            $this->rssFiles[$title] = $rssFile;
        } elseif (!in_array($rssFile, $this->rssFiles, true)) {
            $this->rssFiles[] = $rssFile;
        }
    }

    /**
     * Adds a javascript file to the html page.
     * @param string $jsFile The url with filename or the relative path starting with **adm_program** of the javascript file.
     */
    public function addJavascriptFile($jsFile)
    {
        if (!in_array($jsFile, $this->jsFiles, true)) {
            if (str_starts_with($jsFile, 'http')) {
                $this->jsFiles[] = $jsFile;
            } else {
                $this->jsFiles[] = $this->getDebugOrMinFilepath($jsFile);
            }
        }
    }

    /**
     * Adds any javascript content to the page. The javascript will be added in the order you call this method.
     * @param string $javascriptCode       A valid javascript code that will be added to the header of the page.
     * @param bool   $executeAfterPageLoad (optional) If set to **true** the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    public function addJavascript($javascriptCode, $executeAfterPageLoad = false)
    {
        if ($executeAfterPageLoad) {
            $this->javascriptContentExecute .= $javascriptCode. "\n";
        } else {
            $this->javascriptContent .= $javascriptCode. "\n";
        }
    }

    /**
     * Add content to the header segment of a html page.
     * @param string $header Content for the html header segment.
     */
    public function addHeader($header)
    {
        $this->header .= $header;
    }

    /**
     * Adds any html content to the page. The content will be added in the order
     * you call this method. The first call will place the content at the top of
     * the page. The second call below the first etc.
     * @param string $html A valid html code that will be added to the page.
     */
    public function addHtml($html)
    {
        $this->pageContent .= $html;
    }

    /**
     * Add a new menu item to the page menu part. This is only the menu that will show functions of the
     * current page. The menu header will automatically the name of the page. If a dropdown menu item should
     * be created than $parentMenuItemId must be set to each entry of the dropdown. If a badge should
     * be shown at this menu item than set the $badgeCount.
     * @param string $id.         Id of the menu item that will be the html id of the <a> tag
     * @param string $name        Name of the menu node that will also shown in the menu
     * @param string $url         The url of this menu item that will be called if someone click the menu item
     * @param string $icon        An icon that will be shown together with the name in the menu
     * @param string $parentMenuItemId The id of the parent item to which this item will be added.
     * @param string $badgeCount  If set > 0 than a small badge with the number will be shown after the menu item name
     * @param string $description A optional description of the menu node that could be shown in some output cases
     */
    public function addPageFunctionsMenuItem($id, $name, $url, $icon, $parentMenuItemId = '', $badgeCount = 0, $description = '')
    {
        $this->menuNodePageFunctions->addItem($id, $name, $url, $icon, $parentMenuItemId, $badgeCount, $description);
    }

    /**
     * This method add a specific template file of the themes folder to the current page. The default
     * template will be loaded and this file will be included after the main page content.
     * @param string $templateFile The name of the template file in the templates folder of
     *                             the current theme that should be loaded within the current page.
     */
    public function addTemplateFile($templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * The method will return the filename. If you are in debug mode than it will return the
     * not minified version of the filename otherwise it will return the minified version.
     * Therefore you must provide 2 versions of the file. One with a **min** before the file extension
     * and one version without the **min**.
     * @param string $filepath Filename of the NOT minified file.
     * @return string Returns the filename in dependence of the debug mode.
     */
    private function getDebugOrMinFilepath($filepath)
    {
        global $gDebug;

        $fileInfo = pathinfo($filepath);
        $filename = basename($fileInfo['filename'], '.min');

        $filepathDebug = '/' . $fileInfo['dirname'] . '/' . $filename . '.'     . $fileInfo['extension'];
        $filepathMin   = '/' . $fileInfo['dirname'] . '/' . $filename . '.min.' . $fileInfo['extension'];

        if ((!$gDebug && is_file(ADMIDIO_PATH . $filepathMin)) || !is_file(ADMIDIO_PATH . $filepathDebug)) {
            return ADMIDIO_URL . $filepathMin;
        }

        return ADMIDIO_URL . $filepathDebug;
    }

    /**
     * Returns the headline of the current Admidio page. This is the text of the <h1> tag of the page.
     * @return string Returns the headline of the current Admidio page.
     */
    public function getHeadline()
    {
        return $this->headline;
    }

    /* Add page specific javascript files, css files or rss files to the header. Also specific header
     * informations will also be added
     * @return string Html string with all additional header informations
     */
    public function getHtmlAdditionalHeader()
    {
        $this->header .= $this->getHtmlCssFiles() . $this->getHtmlJsFiles() . $this->getHtmlRssFiles();
        return $this->header;
    }

    // add css files to page
    public function getHtmlCssFiles()
    {
        $html = '';

        foreach ($this->cssFiles as $cssFile) {
            $html .= '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />'."\n";
        }

        return $html;
    }

    // add javascript files to page
    public function getHtmlJsFiles()
    {
        $html = '';

        foreach ($this->jsFiles as $jsFile) {
            $html .= '<script type="text/javascript" src="' . $jsFile . '"></script>'."\n";
        }

        return $html;
    }

    // add rss feed files to page
    public function getHtmlRssFiles()
    {
        $html = '';

        foreach ($this->rssFiles as $title => $rssFile) {
            if (!is_numeric($title)) {
                $html .= '<link rel="alternate" type="application/rss+xml" title="' . $title . '" href="' . $rssFile . '" />'."\n";
            } else {
                $html .= '<link rel="alternate" type="application/rss+xml" href="' . $rssFile . '" />'."\n";
            }
        }

        return $html;
    }

    /**
     * Returns the title of the html page.
     * @return string Returns the title of the html page.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * If this method is called than the back link to the previous page will not be shown.
     */
    public function hideBackLink()
    {
        $this->showBackLink = false;
    }

    /**
     * Set the h1 headline of the current html page. If the title of the page
     * was not set until now than this will also be the title.
     * @param string $headline A string that contains the headline for the page.
     * @return void
     */
    public function setHeadline($headline)
    {
        if ($this->title === '') {
            $this->setTitle($headline);
        }

        $this->menuNodePageFunctions->setName($headline);
        $this->headline = $headline;
    }

    /** If set to true then a page without header menue and sidebar menu will be created.
     *  The main template file will be **index_reduced.tpl** instead of index.tpl.
     */
    public function setInlineMode()
    {
        $this->modeInline = true;
    }

    /**
     * Set the title of the html page that will be shown in the <title> tag.
     * @param string $title A string that contains the title for the page.
     * @return void
     */
    public function setTitle($title)
    {
        global $gCurrentOrganization;

        if ($title === '') {
            $this->title = $gCurrentOrganization->getValue('org_longname');
        } else {
            $this->title = $gCurrentOrganization->getValue('org_longname') . ' - ' . $title;
        }
    }

    /**
     * If print mode is set then the reduced template file **index_reduced.tpl** will be loaded with
     * a print specific css file **print.css**. All styles will be more print compatible and are
     * only black, grey and white.
     * @return void
     */
    public function setPrintMode()
    {
        $this->setInlineMode();
        $this->printView = true;
    }

    /**
     * This method will set all variables for the Smarty engine and than send the whole html
     * content also to the template engine which will generate the html page.
     * Call this method if you have finished your page layout.
     */
    public function show()
    {
        global $gDebug, $gMenu, $gCurrentOrganization, $gCurrentUser, $gValidLogin, $gL10n, $gSettingsManager,
               $gSetCookieForDomain, $gNavigation;

        $urlImprint = '';
        $urlDataProtection = '';
        $hasPreviousUrl = false;

        // if there is more than 1 url in the stack than show the back button
        if ($this->showBackLink && $gNavigation->count() > 1) {
            $hasPreviousUrl = true;
        }

        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        $this->assign('additionalHeaderData', $this->getHtmlAdditionalHeader());
        $this->assign('languageIsoCode', $gL10n->getLanguageIsoCode());
        $this->assign('id', $this->id);
        $this->assign('title', $this->title);
        $this->assign('headline', $this->headline);
        $this->assign('hasPreviousUrl', $hasPreviousUrl);
        $this->assign('organizationName', $gCurrentOrganization->getValue('org_longname'));
        $this->assign('urlAdmidio', ADMIDIO_URL);
        $this->assign('urlTheme', THEME_URL);
        $this->assign('javascriptContent', $this->javascriptContent);
        $this->assign('javascriptContentExecuteAtPageLoad', $this->javascriptContentExecute);
        $this->assign('navigationStack', $gNavigation->getStack());

        $this->assign('userUuid', $gCurrentUser->getValue('usr_uuid'));
        $this->assign('validLogin', $gValidLogin);
        $this->assign('debug', $gDebug);
        $this->assign('registrationEnabled', $gSettingsManager->getBool('registration_enable_module'));

        $this->assign('printView', $this->printView);
        $this->assign('menuSidebar', $gMenu);
        $this->assign('menuFunctions', $this->menuNodePageFunctions);
        $this->assign('templateFile', $this->templateFile);
        $this->assign('content', $this->pageContent);

        // add imprint and data protection
        if ($gSettingsManager->has('system_url_imprint') && strlen($gSettingsManager->getString('system_url_imprint')) > 0) {
            $urlImprint = $gSettingsManager->getString('system_url_imprint');
        }
        if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
            $urlDataProtection = $gSettingsManager->getString('system_url_data_protection');
        }
        $this->assign('urlImprint', $urlImprint);
        $this->assign('urlDataProtection', $urlDataProtection);
        $this->assign('cookieNote', $gSettingsManager->getBool('system_cookie_note'));

        // show cookie note
        if ($gSettingsManager->has('system_cookie_note') && $gSettingsManager->getBool('system_cookie_note')) {
            $this->assign('cookieDomain', DOMAIN);
            $this->assign('cookiePrefix', COOKIE_PREFIX);

            if ($gSetCookieForDomain) {
                $this->assign('cookiePath', '/');
            } else {
                $this->assign('cookiePath', ADMIDIO_URL_PATH . '/');
            }

            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
                $this->assign('cookieDataProtectionUrl', '"href": "'. $gSettingsManager->getString('system_url_data_protection') .'", ');
            } else {
                $this->assign('cookieDataProtectionUrl', '');
            }
        }

        // add translation object
        $this->assign('l10n', $gL10n);

        try {
            if ($this->modeInline) {
                $this->display('index_reduced.tpl');
            } else {
                $this->display('index.tpl');
            }
        } catch (SmartyException $exception) {
            echo $exception->getMessage();
            echo '<br />Please check if the theme folder "<strong>' . $gSettingsManager->getString('theme') . '</strong>" exists within the folder "adm_themes".';
        }
    }
}
