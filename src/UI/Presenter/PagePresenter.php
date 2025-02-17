<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Menu\ValueObject\MenuNode;
use Smarty\Smarty;

/**
 * @brief Creates an Admidio specific complete html page with the template engine Smarty.
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. The class is derived
 * from the Smarty class. It provides method to add new html data to the page and also set or
 * choose the necessary template files. The generated page will automatically integrate the
 * chosen theme. You can also create a html reduces page without menu header and footer
 * information.
 *
 * **Code example**
 * ```
 * // create a simple html page with some text
 * $page = PagePresenter::withHtmlIDAndHeadline('admidio-example');
 * $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class PagePresenter
{
    /**
     * @var Smarty An object ot the Smarty template engine.
     */
    protected Smarty $smarty;
    /**
     * @var string The id of the html page that will be set within the <body> tag.
     */
    protected string $id = '';
    /**
     * @var string The title for the html page and the headline for the Admidio content.
     */
    protected string $title = '';
    /**
     * @var bool Per default the content of the page is limited to a width of 1000px. If you want to have a full width page than set this flag to true.
     */
    protected bool $fullWidth = false;
    /**
     * @var string UUID of an object that represents the page. The data shown at the page will belong to this object.
     */
    protected string $objectUUID = '';
    /**
     * @var string Additional header that could not be set with the other methods. This content will be added to head of html page without parsing.
     */
    protected string $header = '';
    /**
     * @var string The main headline for the html page.
     */
    protected string $headline = '';
    /**
     * @var string Contains the custom html of the current page. This will be added to the default html of each page.
     */
    protected string $pageContent = '';
    /**
     * @var MenuNode An object that represents all functions of the current page that should be shown in the menu of this page
     */
    protected MenuNode $menuNodePageFunctions;
    /**
     * @var array<int,string> An array with all necessary cascading style sheets files for the html page.
     */
    protected array $cssFiles = array();
    /**
     * @var array<int,string> An array with all necessary javascript files for the html page.
     */
    protected array $jsFiles = array();
    /**
     * @var array<int|string,string> An array with all necessary rss files for the html page.
     */
    protected array $rssFiles = array();
    /**
     * @var bool A flag that indicates if the page should be styled in print mode then no colors will be shown
     */
    protected bool $printView = false;
    /**
     * @var string Contains the custom javascript of the current page. This will be added to the header part of the page.
     */
    protected string $javascriptContent = '';
    /**
     * @var string Contains the custom javascript of the current page that should be executed after page load. This will be added to the header part of the page.
     */
    protected string $javascriptContentExecute = '';
    /**
     * @var bool If set to true then a page without header menu and sidebar menu will be created. The main template file will be index_inline.tpl
     */
    protected bool $modeInline = false;
    /**
     * @var string Name of an additional template file that should be loaded within the current page.
     */
    protected string $templateFile = '';
    /**
     * @var bool Flag that will be responsible for a back button with the url to the previous page will be shown.
     */
    protected bool $showBackLink = false;

    /**
     * Static method which will return an instance of the PagePresenter. The HTML ID of the page will be set and
     * optional the headline of the page.
     * @param string $htmlID The HTML ID of the current page.
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag
     *                         and also set the title of the page.
     * @throws Exception
     */
    public static function withHtmlIDAndHeadline(string $htmlID, string $headline = '') {
        $instance = new self();
        $instance->setHtmlID($htmlID);
        $instance->setHeadline($headline);
        return $instance;
    }

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $objectUUID UUID of an object that represents the page. The data shown at the page will belong
     *                           to this object.
     * @throws Exception
     */
    public function __construct(string $objectUUID = '')
    {
        global $gSettingsManager;

        $this->menuNodePageFunctions = new MenuNode('admidio-menu-page-functions');

        $this->objectUUID = $objectUUID;
        $this->showBackLink = true;

        $this->smarty = $this->createSmartyObject();
        $this->assignBasicSmartyVariables();

        if (is_object($gSettingsManager) && $gSettingsManager->has('system_browser_update_check')
        && $gSettingsManager->getBool('system_browser_update_check')) {
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/browser-update/browser-update.js');
        }
    }

    /**
     * Adds a cascading style sheets file to the html page.
     * @param string $cssFile The url with filename or the relative path starting with **adm_program** of the css file.
     */
    public function addCssFile(string $cssFile)
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
     * Adds an RSS file to the html page.
     * @param string $rssFile The url with filename of the rss file.
     * @param string $title   (optional) Set a title. This is the name of the feed and will be shown when adding the rss feed.
     */
    public function addRssFile(string $rssFile, string $title = '')
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
    public function addJavascriptFile(string $jsFile)
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
     * @param bool $executeAfterPageLoad (optional) If set to **true** the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    public function addJavascript(string $javascriptCode, bool $executeAfterPageLoad = false)
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
    public function addHeader(string $header)
    {
        $this->header .= $header;
    }

    /**
     * Adds any html content to the page. The content will be added in the order
     * you call this method. The first call will place the content at the top of
     * the page. The second call below the first etc.
     * @param string $html A valid html code that will be added to the page.
     */
    public function addHtml(string $html)
    {
        $this->pageContent .= $html;
    }


    /**
     * Adds html content from a Smarty template file. Therefore, the template file will be fetched and
     * the html content will be added to the page content.
     * @param string $template Template name with relative template path that should be fetched.
     * @throws \Smarty\Exception
     */
    public function addHtmlByTemplate(string $template)
    {
        $this->pageContent .= $this->smarty->fetch($template);
    }

    /**
     * Add a new menu item to the page menu part. This is only the menu that will show functions of the
     * current page. The menu header will automatically the name of the page. If a dropdown menu item should
     * be created than $parentMenuItemId must be set to each entry of the dropdown. If a badge should
     * be shown at this menu item than set the $badgeCount.
     * @param string $id .         ID of the menu item that will be the html id of the <a> tag
     * @param string $name Name of the menu node that will be shown in the menu
     * @param string $url The url of this menu item that will be called if someone click the menu item
     * @param string $icon An icon that will be shown together with the name in the menu
     * @param string $parentMenuItemId The id of the parent item to which this item will be added.
     * @param int $badgeCount If set > 0 than a small badge with the number will be shown after the menu item name
     * @param string $description An optional description of the menu node that could be shown in some output cases
     * @throws Exception
     */
    public function addPageFunctionsMenuItem(string $id, string $name, string $url, string $icon, string $parentMenuItemId = '', int $badgeCount = 0, string $description = '')
    {
        $this->menuNodePageFunctions->addItem($id, $name, $url, $icon, $parentMenuItemId, $badgeCount, $description);
    }

    /**
     * This method add a specific template file of the themes folder to the current page. The default
     * template will be loaded and this file will be included after the main page content.
     * @param string $templateFile The name of the template file in the templates folder of
     *                             the current theme that should be loaded within the current page.
     */
    public function addTemplateFile(string $templateFile)
    {
        $this->templateFile = $templateFile;
    }

    /**
     * Public method to assign new variables to the Smarty template of the PagePresenter.
     * @return void
     * @throws Exception
     */
    private function assignBasicSmartyVariables()
    {
        global $gDebug, $gCurrentOrganization, $gCurrentUser, $gValidLogin, $gL10n, $gSettingsManager,
               $gSetCookieForDomain, $gCurrentSession;

        $urlImprint = '';
        $urlDataProtection = '';

        $this->smarty->assign('languageIsoCode', $gL10n->getLanguageIsoCode());
        $this->smarty->assign('id', $this->id);
        $this->smarty->assign('title', $this->title);
        $this->smarty->assign('headline', $this->headline);
        $this->smarty->assign('currentOrganization', $gCurrentOrganization);
        $this->smarty->assign('organizationName', $gCurrentOrganization->getValue('org_longname'));
        $this->smarty->assign('urlAdmidio', ADMIDIO_URL);
        $this->smarty->assign('urlTheme', THEME_URL);
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());

        $this->smarty->assign('currentUser', $gCurrentUser);
        $this->smarty->assign('validLogin', $gValidLogin);
        $this->smarty->assign('debug', $gDebug);
        $this->smarty->assign('registrationEnabled', $gSettingsManager->getBool('registration_enable_module'));

        // add imprint and data protection
        if ($gSettingsManager->has('system_url_imprint') && strlen($gSettingsManager->getString('system_url_imprint')) > 0) {
            $urlImprint = $gSettingsManager->getString('system_url_imprint');
        }
        if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
            $urlDataProtection = $gSettingsManager->getString('system_url_data_protection');
        }
        $this->smarty->assign('urlImprint', $urlImprint);
        $this->smarty->assign('urlDataProtection', $urlDataProtection);
        $this->smarty->assign('cookieNote', $gSettingsManager->getBool('system_cookie_note'));

        // show cookie note
        if ($gSettingsManager->has('system_cookie_note') && $gSettingsManager->getBool('system_cookie_note')) {
            $this->smarty->assign('cookieDomain', DOMAIN);
            $this->smarty->assign('cookiePrefix', COOKIE_PREFIX);

            if ($gSetCookieForDomain) {
                $this->smarty->assign('cookiePath', '/');
            } else {
                $this->smarty->assign('cookiePath', ADMIDIO_URL_PATH . '/');
            }

            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0) {
                $this->smarty->assign('cookieDataProtectionUrl', '"href": "'. $gSettingsManager->getString('system_url_data_protection') .'", ');
            } else {
                $this->smarty->assign('cookieDataProtectionUrl', '');
            }
        }

        // add translation object
        $this->smarty->assign('l10n', $gL10n);
        $this->smarty->assign('settings', $gSettingsManager);
    }

    /**
     * Public method to assign new variables to the Smarty template of the PagePresenter.
     * @param string $variable Name of the variable within the Smarty template.
     * @param string|array $value Value of the variable.
     * @return void
     */
    public function assignSmartyVariable(string $variable, $value)
    {
        $this->smarty->assign($variable, $value);
    }

    /**
     * Create an object of the template engine Smarty. This object uses the template folder of the
     * current theme. The all cacheable and compilable files will be stored in the templates folder
     * of **adm_my_files**.
     * @return Smarty Returns the initialized Smarty object.
     * @throws Exception
     */
    public static function createSmartyObject(): Smarty
    {
        $smartyObject = new Smarty();

        try {
            // initialize php template engine smarty
            if (defined('THEME_PATH')) {
                $smartyObject->setTemplateDir(THEME_PATH . '/templates/');
            }

            $smartyObject->setCacheDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/cache/');
            $smartyObject->setCompileDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/compile/');
            $smartyObject->registerPlugin('function', 'array_key_exists', 'Admidio\Infrastructure\Plugins\Smarty::arrayKeyExists');
            $smartyObject->registerPlugin('function', 'is_translation_string_id', 'Admidio\Infrastructure\Plugins\Smarty::isTranslationStringID');
            $smartyObject->registerPlugin('function', 'load_admidio_plugin', 'Admidio\Infrastructure\Plugins\Smarty::loadAdmidioPlugin');
            return $smartyObject;
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * The method will return the filename. If you are in debug mode than it will return the
     * not minified version of the filename otherwise it will return the minified version.
     * Therefore, you must provide 2 versions of the file. One with a **min** before the file extension
     * and one version without the **min**.
     * @param string $filepath Filename of the NOT minified file.
     * @return string Returns the filename in dependence of the debug mode.
     */
    private function getDebugOrMinFilepath(string $filepath): string
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
     * Returns the content of the page. Menu, page header and page footer will not be returned.
     * Just the specific content of the page.
     * @return string Returns the html of the page content.
     */
    public function getPageContent(): string
    {
        return $this->pageContent;
    }

    /**
     * Returns the headline of the current Admidio page. This is the text of the <h1> tag of the page.
     * @return string Returns the headline of the current Admidio page.
     */
    public function getHeadline(): string
    {
        return $this->headline;
    }

    /**
     * Add page specific javascript files, css files or rss files to the header. Also, specific header
     * information will also be added
     * @return string Html string with all additional header information
     */
    public function getHtmlAdditionalHeader(): string
    {
        return $this->header;
    }

    /**
     * Returns the Smarty template object.
     * @return Smarty Returns the Smarty template object.
     */
    public function getSmartyTemplate(): Smarty
    {
        return $this->smarty;
    }

    /**
     * Returns the title of the html page.
     * @return string Returns the title of the html page.
     */
    public function getTitle(): string
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
     * Per default the content of the page is limited to a width of 1000px. If you want to have a full width
     * page than you can call this method.
     * @return void
     * @throws Exception
     */
    public function setContentFullWidth()
    {
        $this->fullWidth = true;
    }

    /**
     * Set the h1 headline of the current html page. If the title of the page
     * was not set until now than this will also be the title.
     * @param string $headline A string that contains the headline for the page.
     * @return void
     * @throws Exception
     */
    public function setHeadline(string $headline)
    {
        if ($this->title === '') {
            $this->setTitle($headline);
        }

        if(isset($this->menuNodePageFunctions)) {
            $this->menuNodePageFunctions->setName($headline);
        }
        $this->headline = $headline;
        if(isset($this->smarty)) {
            $this->smarty->assign('title', $this->title);
            $this->smarty->assign('headline', $this->headline);
        }
    }


    /**
     * Set the HTML ID of the current html page.
     * @param string $htmlID The HTML ID of the current page.
     * @return void
     */
    public function setHtmlID(string $htmlID)
    {
        $this->id = $htmlID;
    }

    /** If set to true then a page without header menu and sidebar menu will be created.
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
     * @throws Exception
     */
    public function setTitle(string $title)
    {
        global $gCurrentOrganization;

        if(isset($gCurrentOrganization)) {
            if ($title === '') {
                $this->title = $gCurrentOrganization->getValue('org_longname');
            } else {
                $this->title = $gCurrentOrganization->getValue('org_longname') . ' - ' . $title;
            }
        } else {
            $this->title = $title;
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
     * This method will set all variables for the Smarty engine and then send the whole html
     * content also to the template engine which will generate the html page.
     * Call this method if you have finished your page layout.
     */
    public function show()
    {
        global $gSettingsManager, $gLayoutReduced, $gMenu, $gNavigation;

        $hasPreviousUrl = false;

        // if there is more than 1 url in the stack than show the back button
        if ($this->showBackLink && $gNavigation->count() > 1) {
            $hasPreviousUrl = true;
        }

        // disallow iFrame integration from other domains to avoid clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        $this->smarty->assign('additionalHeaderData', $this->getHtmlAdditionalHeader());
        $this->smarty->assign('javascriptContent', $this->javascriptContent);
        $this->smarty->assign('javascriptContentExecuteAtPageLoad', $this->javascriptContentExecute);

        $this->smarty->assign('navigationStack', $gNavigation->getStack());
        $this->smarty->assign('hasPreviousUrl', $hasPreviousUrl);
        $this->smarty->assign('printView', $this->printView);
        $this->smarty->assign('menuNavigation', $gMenu->getAllMenuItems());
        $this->smarty->assign('menuFunctions', $this->menuNodePageFunctions->getAllItems());
        $this->smarty->assign('templateFile', $this->templateFile);
        $this->smarty->assign('content', $this->pageContent);
        $this->smarty->assign('rssFeeds', $this->rssFiles);
        $this->smarty->assign('cssFiles', $this->cssFiles);
        $this->smarty->assign('javascriptFiles', $this->jsFiles);

        if ($this->fullWidth) {
            $this->smarty->assign('contentClass', 'admidio-max-content');
        } else {
            $this->smarty->assign('contentClass', '');
        }

        try {
            if ($this->modeInline || $gLayoutReduced) {
                $this->smarty->display('index_reduced.tpl');
            } else {
                $this->smarty->display('index.tpl');
            }
        } catch (\Smarty\Exception $exception) {
            echo $exception->getMessage();
            echo '<br />Please check if the theme folder "<strong>' . $gSettingsManager->getString('theme') . '</strong>" exists within the folder "themes".';
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
