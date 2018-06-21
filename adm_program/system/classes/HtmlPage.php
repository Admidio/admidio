<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
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
 * **Code example:**
 * ```
 * // create a simple html page with some text
 * $page = new HtmlPage();
 * $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show();
 * ```
 */
class HtmlPage
{
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
     * @var HtmlNavbar An object of the menu of this page
     */
    protected $menu;
    /**
     * @var bool If set to true then the custom html code of the theme for each page will be included.
     */
    protected $showThemeHtml = true;
    /**
     * @var bool If set to true then the menu will be included.
     */
    protected $showMenu = true;
    /**
     * @var bool Flag if the current page has a navbar.
     */
    protected $hasNavbar = false;
    /**
     * @var bool If set to true then html code for a modal window will be included.
     */
    protected $showModal = false;
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
    protected $printMode = false;
    /**
     * @var string Contains the custom javascript of the current page. This will be added to the header part of the page.
     */
    protected $javascriptContent = '';
    /**
     * @var string Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.
     */
    protected $javascriptContentExecute = '';
    /**
     * @var string Contains the custom html code of the header theme file. This will be added to the header part of the page.
     */
    protected $htmlMyHeader = '';
    /**
     * @var string Contains the custom html code of the top body theme file. This will be added to the top of the body part of the page.
     */
    protected $htmlMyBodyTop = '';
    /**
     * @var string Contains the custom html code of the bottom body theme file. This will be added to the end of thebody part of the page.
     */
    protected $htmlMyBodyBottom = '';


    /**
     * Constructor creates the page object and initialized all parameters
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag.
     */
    public function __construct($headline = '')
    {
        $this->menu = new HtmlNavbar('menu_main_script', $headline, $this);

        $this->setHeadline($headline);

        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap/css/bootstrap.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/jquery/jquery.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/bootstrap/js/bootstrap.js');
        $this->addJavascriptFile(ADMIDIO_URL . '/adm_program/system/js/common_functions.js');
    }

    /**
     * Adds a cascading style sheets file to the html page.
     * @param string $cssFile The url with filename or the relative path starting with **adm_program** of the css file.
     */
    public function addCssFile($cssFile)
    {
        if (!in_array($cssFile, $this->cssFiles, true))
        {
            if (admStrStartsWith($cssFile, 'http'))
            {
                $this->cssFiles[] = $cssFile;
            }
            else
            {
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
        if ($title !== '')
        {
            $this->rssFiles[$title] = $rssFile;
        }
        elseif (!in_array($rssFile, $this->rssFiles, true))
        {
            $this->rssFiles[] = $rssFile;
        }
    }

    /**
     * Adds a javascript file to the html page.
     * @param string $jsFile The url with filename or the relative path starting with **adm_program** of the javascript file.
     */
    public function addJavascriptFile($jsFile)
    {
        if (!in_array($jsFile, $this->jsFiles, true))
        {
            if (admStrStartsWith($jsFile, 'http'))
            {
                $this->jsFiles[] = $jsFile;
            }
            else
            {
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
        if ($executeAfterPageLoad)
        {
            $this->javascriptContentExecute .= $javascriptCode. "\n";
        }
        else
        {
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
     * adds the main necessary files
     */
    private function addMainFilesAndContent()
    {
        global $gSettingsManager;

        // add admidio css file at last because there the user can redefine all css
        $this->addCssFile(THEME_URL.'/css/admidio.css');

        // if print mode is set then add a print specific css file
        if ($this->printMode)
        {
            $this->addCssFile(THEME_URL.'/css/print.css');
        }

        // add custom css file if it exists to add own css styles without edit the original admidio css
        if (is_file(THEME_URL.'/css/custom.css'))
        {
            $this->addCssFile(THEME_URL.'/css/custom.css');
        }

        if ($gSettingsManager->has('system_browser_update_check') && $gSettingsManager->getBool('system_browser_update_check'))
        {
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/browser-update/browser-update.js');
        }

        // add code for a modal window
        if ($this->showModal)
        {
            $this->addJavascript('
                $("body").on("hidden.bs.modal", ".modal", function() {
                    $(this).removeData("bs.modal");
                });',
                true
            );
            $this->addHtml('
                <div class="modal fade" id="admidio_modal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content"></div>
                    </div>
                </div>'
            );
        }

        // load content of theme files at this point so that files could add css and js files
        if ($this->showThemeHtml)
        {
            $this->htmlMyHeader     = $this->getFileContent('my_header.php');
            $this->htmlMyBodyTop    = $this->getFileContent('my_body_top.php');
            $this->htmlMyBodyBottom = $this->getFileContent('my_body_bottom.php');
        }
    }

    /**
     * Adds the modal menu
     */
    public function addModalMenu()
    {
        global $gL10n, $gValidLogin, $gDb, $gCurrentUser;

        $mainMenuStatement = self::getMainMenuStatement();

        while ($mainMenu = $mainMenuStatement->fetch())
        {
            $menuIcon = '/dummy.png';
            $menuItems = array();

            $menuStatement = self::getMenuStatement($mainMenu['men_id']);
            while ($row = $menuStatement->fetch())
            {
                if ((int) $row['men_com_id'] === 0 || Component::isVisible($row['com_name_intern']))
                {
                    // Read current roles rights of the menu
                    $displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
                    $rolesDisplayRight = $displayMenu->getRolesIds();

                    // check for right to show the menu
                    if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                    {
                        continue;
                    }

                    // special case because there are different links if you are logged in or out for mail
                    if ($gValidLogin && $row['men_name_intern'] === 'mail')
                    {
                        $menuUrl = ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php';
                        $menuIcon = '/icons/messages.png';
                        $menuName = $gL10n->get('SYS_MESSAGES') . self::getUnreadMessagesBadge();
                    }
                    else
                    {
                        $menuUrl = $row['men_url'];
                        if(strlen($row['men_icon']) > 2)
                        {
                            $menuIcon = $row['men_icon'];
                        }
                        $menuName = Language::translateIfTranslationStrId($row['men_name']);
                    }

                    $menuItems[] = array(
                        'intern' => $row['men_name_intern'],
                        'url'    => $menuUrl,
                        'name'   => $menuName,
                        'icon'   => $menuIcon
                    );
                }
            }

            if (count($menuItems) > 0)
            {
                $menuName = Language::translateIfTranslationStrId($mainMenu['men_name']);

                $this->menu->addItem(
                    'menu_item_'.$mainMenu['men_name_intern'], '', $menuName,
                    'application_view_list.png', 'right', 'navbar', 'admidio-default-menu-item'
                );

                foreach ($menuItems as $menuItem)
                {
                    $this->menu->addItem(
                        $menuItem['intern'], $menuItem['url'], $menuItem['name'], $menuItem['icon'], 'right',
                        'menu_item_'.$mainMenu['men_name_intern'], 'admidio-default-menu-item'
                    );
                }
            }
        }

        if ($gValidLogin)
        {
            // show link to own profile
            $this->menu->addItem(
                'menu_item_my_profile', ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', $gL10n->get('PRO_MY_PROFILE'),
                'profile.png', 'right', 'navbar', 'admidio-default-menu-item'
            );
            // show logout link
            $this->menu->addItem(
                'menu_item_logout', ADMIDIO_URL . '/adm_program/system/logout.php', $gL10n->get('SYS_LOGOUT'),
                'door_in.png', 'right', 'navbar', 'admidio-default-menu-item'
            );
        }
        else
        {
            // show registration link
            $this->menu->addItem(
                'menu_item_registration', ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php', $gL10n->get('SYS_REGISTRATION'),
                'new_registrations.png', 'right', 'navbar', 'admidio-default-menu-item'
            );
            // show login link
            $this->menu->addItem(
                'menu_item_login', ADMIDIO_URL . '/adm_program/system/login.php', $gL10n->get('SYS_LOGIN'),
                'key.png', 'right', 'navbar', 'admidio-default-menu-item'
            );
        }
    }

    /**
     * Adds the html code for a modal window to the current script.
     * The link must have the following attributes: data-toggle="modal" data-target="#admidio_modal"
     */
    public function enableModal()
    {
        $this->showModal = true;
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

        if ((!$gDebug && is_file(ADMIDIO_PATH . $filepathMin)) || !is_file(ADMIDIO_PATH . $filepathDebug))
        {
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

    /**
     * Loads the content of the given theme file
     * @param string $filename Filename to load out of the theme directory
     * @return string
     */
    private function getFileContent($filename)
    {
        global $gLogger, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser;
        global $gValidLogin, $gProfileFields, $gHomepage, $gDbType, $gSettingsManager;
        global $g_root_path, $gPreferences;

        $filePath = THEME_ADMIDIO_PATH . '/' . $filename;
        if (!is_file($filePath))
        {
            $gLogger->error('THEME: Theme file "' . $filename . '" not found!', array('filePath' => $filePath));

            return '';
        }

        ob_start();
        require($filePath);
        $fileContent = ob_get_contents();
        ob_end_clean();

        return $fileContent;
    }

    /**
     * Builds the HTML-Header content
     * @return string
     */
    private function getHtmlHeader()
    {
        global $gL10n, $gSettingsManager, $gSetCookieForDomain;

        $headerContent = '';

        // add css files to page
        foreach ($this->cssFiles as $cssFile)
        {
            $headerContent .= '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />';
        }

        // add some special scripts so that ie8 could better understand the Bootstrap 3 framework
        $headerContent .= '<!--[if lt IE 9]>
            <script src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/html5shiv/html5shiv.min.js"></script>
            <script src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/respond/respond.min.js"></script>
        <![endif]-->';

        // add javascript files to page
        foreach ($this->jsFiles as $jsFile)
        {
            $headerContent .= '<script type="text/javascript" src="' . $jsFile . '"></script>';
        }

        // add rss feed files to page
        foreach ($this->rssFiles as $title => $rssFile)
        {
            if (!is_numeric($title))
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" title="' . $title . '" href="' . $rssFile . '" />';
            }
            else
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" href="' . $rssFile . '" />';
            }
        }

        // add javascript code to page
        if ($this->javascriptContent !== '')
        {
            $headerContent .= '<script type="text/javascript">' . $this->javascriptContent . '</script>';
        }

        // add javascript code to page that will be executed after page is fully loaded
        if ($this->javascriptContentExecute !== '')
        {
            $headerContent .= '<script type="text/javascript">
                $(function() {
                    $("[data-toggle=\'popover\']").popover();
                    $(".admidio-icon-info, .admidio-icon-link img, [data-toggle=tooltip]").tooltip();
                    ' . $this->javascriptContentExecute . '
                });
            </script>';
        }

        if ($gSettingsManager->has('system_cookie_note') && $gSettingsManager->getBool('system_cookie_note'))
        {
            if ($gSetCookieForDomain)
            {
                $path = '/';
            }
            else
            {
                $path = ADMIDIO_URL_PATH . '/';
            }

            // add cookie approval to the page
            $headerContent .= '<link rel="stylesheet" type="text/css" href="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/cookieconsent/cookieconsent.min.css" />
            <script src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/cookieconsent/cookieconsent.min.js"></script>
            <script>
                window.addEventListener("load", function() {
                    window.cookieconsent.initialise({
                        "cookie": {
                            "name": "' . COOKIE_PREFIX . '_cookieconsent_status",
                            "domain": "' . DOMAIN .'",
                            "path": "' . $path .'"
                        },
                        "content": {
                            "message": "' . $gL10n->get('SYS_COOKIE_DESC') . '",
                            "dismiss": "' . $gL10n->get('SYS_OK') . '",';
                            if ($gSettingsManager->has('system_url_data_protection') && strlen($gSettingsManager->getString('system_url_data_protection')) > 0)
                            {
                                $headerContent .= ' "href": "'. $gSettingsManager->getString('system_url_data_protection') .'", ';
                            }
                            $headerContent .= '"link": "' . $gL10n->get('SYS_FURTHER_INFORMATIONS') . '"
                        },
                        "position": "bottom",
                        "theme": "classic",
                        "palette": {
                            "popup": {
                                "background": "#252e39"
                            },
                            "button": {
                                "background": "#409099"
                            }
                        }
                    });
                });
            </script>';
        }

        $htmlHeader = '<head>
            <!-- (c) 2004 - 2018 The Admidio Team - ' . ADMIDIO_HOMEPAGE . ' -->

            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />

            <title>' . $this->title . '</title>

            <script type="text/javascript">
                var gRootPath  = "' . ADMIDIO_URL . '";
                var gThemePath = "' . THEME_URL . '";
            </script>';

        $htmlHeader .= $headerContent;
        $htmlHeader .= $this->header;
        $htmlHeader .= $this->htmlMyHeader;
        $htmlHeader .= '</head>';

        return $htmlHeader;
    }

    /**
     * Builds the HTML-Body content
     * @return string
     */
    private function getHtmlBody()
    {
        $htmlMenu         = '';
        $htmlHeadline     = '';

        if ($this->showMenu)
        {
            // add modules and administration modules to the menu
            $this->showMainMenu();
            $this->addModalMenu();
            $htmlMenu = $this->menu->show();
        }

        if ($this->headline !== '')
        {
            if ($this->hasNavbar)
            {
                $htmlHeadline = '<h1 class="admidio-module-headline hidden-xs">' . $this->headline . '</h1>';
            }
            else
            {
                $htmlHeadline = '<h1 class="admidio-module-headline">' . $this->headline . '</h1>';
            }
        }

        $htmlBody = '<body>';
        $htmlBody .= $this->htmlMyBodyTop;
        $htmlBody .= '<div class="admidio-content">';
        $htmlBody .= $htmlHeadline;
        $htmlBody .= $htmlMenu;
        $htmlBody .= $this->pageContent;
        $htmlBody .= '</div>';
        $htmlBody .= $this->htmlMyBodyBottom;
        $htmlBody .= '</body>';

        return $htmlBody;
    }

    /**
     * @return \PDOStatement|false
     */
    private static function getMainMenuStatement()
    {
        global $gDb;

        $sql = 'SELECT men_id, men_name, men_name_intern
                  FROM '.TBL_MENU.'
                 WHERE men_men_id_parent IS NULL
              ORDER BY men_order';

        return $gDb->queryPrepared($sql);
    }

    /**
     * @param int $menId
     * @return \PDOStatement|false
     */
    private static function getMenuStatement($menId)
    {
        global $gDb;

        $sql = 'SELECT men_id, men_com_id, men_name_intern, men_name, men_description, men_url, men_icon, com_name_intern
                  FROM '.TBL_MENU.'
             LEFT JOIN '.TBL_COMPONENTS.'
                    ON com_id = men_com_id
                 WHERE men_men_id_parent = ? -- $menId
              ORDER BY men_men_id_parent DESC, men_order';

        return $gDb->queryPrepared($sql, array($menId));
    }

    /**
     * Returns the menu object of this html page.
     * @return HtmlNavbar Returns the menu object of this html page.
     */
    public function getMenu()
    {
        return $this->menu;
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
     * Get a badge with the unread messages count
     * @return string
     */
    private static function getUnreadMessagesBadge()
    {
        global $gDb, $gCurrentUser;

        // get number of unread messages for user
        $message = new TableMessage($gDb);
        $unread = $message->countUnreadMessageRecords((int) $gCurrentUser->getValue('usr_id'));

        if ($unread > 0)
        {
            return '<span class="badge">' . $unread . '</span>';
        }

        return '';
    }

    /**
     * Flag if the current page has a navbar.
     * @return void
     */
    public function hasNavbar()
    {
        $this->hasNavbar = true;
    }

    /**
     * Every html page of Admidio contains a menu.
     * If the menu should not be included in the current page, than this method must be called.
     * @return void
     */
    public function hideMenu()
    {
        $this->showMenu = false;
    }

    /**
     * Every html page of Admidio contains three files of the custom theme.
     * my_header.php, my_body_top.php and my_body_bottom.php
     * With these files the administrator can contain custom layout to Admidio.
     * If these files should not be included in the current page, than this method must be called.
     * @return void
     */
    public function hideThemeHtml()
    {
        $this->showThemeHtml = false;
    }

    /**
     * Set the h1 headline of the current html page. If the title of the page
     * was not set until now than this will also be the title.
     * @param string $headline A string that contains the headline for the page.
     * @return void
     */
    public function setHeadline($headline)
    {
        if ($this->title === '')
        {
            $this->setTitle($headline);
        }

        $this->headline = $headline;
        $this->menu->setName($headline);
    }

    /**
     * Set the title of the html page that will be shown in the <title> tag.
     * @param string $title A string that contains the title for the page.
     * @return void
     */
    public function setTitle($title)
    {
        global $gCurrentOrganization;

        if ($title === '')
        {
            $this->title = $gCurrentOrganization->getValue('org_longname');
        }
        else
        {
            $this->title = $gCurrentOrganization->getValue('org_longname') . ' - ' . $title;
        }
    }

    /**
     * If print mode is set then a print specific css file will be loaded.
     * All styles will be more print compatible and are only black, grey and white.
     * @return void
     */
    public function setPrintMode()
    {
        $this->printMode = true;
    }

    /**
     * This method send the whole html code of the page to the browser. Call this method
     * if you have finished your page layout.
     * @param bool $directOutput If set to **true** (default) the html page will be directly send
     *                           to the browser. If set to **false** the html will be returned.
     * @return string|void If $directOutput is set to **false** this method will return the html code of the page.
     */
    public function show($directOutput = true)
    {
        $this->addMainFilesAndContent();

        $html = '<!DOCTYPE html><html>';
        $html .= $this->getHtmlHeader();
        $html .= $this->getHtmlBody();
        $html .= '</html>';

        // now show the complete html of the page
        if ($directOutput)
        {
            header('Content-type: text/html; charset=utf-8');
            echo $html;
        }
        else
        {
            return $html;
        }
    }

    /**
     * create and show Mainmenu
     * @param bool $details indicator to set if there should be details in the menu.
     * @return string HTML of the Menu
     */
    public function showMainMenu($details = true)
    {
        global $gL10n, $gValidLogin, $gSettingsManager, $gDb, $gCurrentUser;

        $menuIcon = '/dummy.png';
        $htmlMenu = '';

        $mainMenuStatement = self::getMainMenuStatement();

        while ($mainMenu = $mainMenuStatement->fetch())
        {
            $unreadBadge = '';

            $menuStatement = self::getMenuStatement($mainMenu['men_id']);

            if ($menuStatement->rowCount() > 0)
            {
                $menu = new Menu($mainMenu['men_name_intern'], Language::translateIfTranslationStrId($mainMenu['men_name']));

                while ($row = $menuStatement->fetch())
                {
                    if ((int) $row['men_com_id'] === 0 || Component::isVisible($row['com_name_intern']))
                    {
                        // Read current roles rights of the menu
                        $displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
                        $rolesDisplayRight = $displayMenu->getRolesIds();

                        // check for right to show the menu
                        if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                        {
                            continue;
                        }

                        $menuName = Language::translateIfTranslationStrId($row['men_name']);
                        $menuDescription = Language::translateIfTranslationStrId($row['men_description']);
                        $menuUrl = $row['men_url'];

                        if (strlen($row['men_icon']) > 2)
                        {
                            $menuIcon = $row['men_icon'];
                        }

                        // special case because there are different links if you are logged in or out for mail
                        if ($gValidLogin && $row['men_name_intern'] === 'mail')
                        {
                            $unreadBadge = self::getUnreadMessagesBadge();

                            $menuUrl = ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php';
                            $menuIcon = 'messages.png';
                            $menuName = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                        }

                        $menu->addItem($row['men_name_intern'], $menuUrl, $menuName, $menuIcon, $menuDescription);

                        if ($details)
                        {
                            // Submenu for Lists
                            if ($gValidLogin && $row['men_name_intern'] === 'lists')
                            {
                                $menu->addSubItem(
                                    'lists', 'rolinac', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/lists/lists.php', array('active_role' => 0)),
                                    $gL10n->get('ROL_INACTIV_ROLE')
                                );
                            }

                            // Submenu for Dates
                            if ($row['men_name_intern'] === 'dates'
                                && ((int) $gSettingsManager->get('enable_dates_module') === 1
                                || ((int) $gSettingsManager->get('enable_dates_module') === 2 && $gValidLogin)))
                            {
                                $menu->addSubItem(
                                    'dates', 'olddates', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php', array('mode' => 'old')),
                                    $gL10n->get('DAT_PREVIOUS_DATES', array($gL10n->get('DAT_DATES')))
                                );
                            }
                        }
                    }
                }

                $htmlMenu .= $menu->show($details);
            }

            $this->menu->addItem(
                'menu_item_private_message', ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php', $gL10n->get('SYS_MESSAGES') . $unreadBadge,
                'messages.png', 'right', 'menu_item_modules', 'admidio-default-menu-item'
            );
        }

        return $htmlMenu;
    }
}
