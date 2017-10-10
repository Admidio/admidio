<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class HtmlPage
 * @brief Creates an Admidio specific complete html page
 *
 * This class creates a html page with head and body and integrates some Admidio
 * specific elements like css files, javascript files and javascript code. It
 * also provides some methods to easily add new html data to the page. The generated
 * page will automatically integrate the choosen theme. You can optional disable the
 * integration of the theme files.
 * @par Examples
 * @code // create a simple html page with some text
 * $page = new HtmlPage();
 * $page->addJavascriptFile('adm_program/libs/jquery/jquery.min.js');
 * $page->setHeadline('A simple Html page');
 * $page->addHtml('<strong>This is a simple Html page!</strong>');
 * $page->show(); @endcode
 */
class HtmlPage
{
    protected $title         = '';              ///< The title for the html page and the headline for the Admidio content.
    protected $header        = '';              ///< Additional header that could not be set with the other methods. This content will be add to head of html page without parsing.
    protected $headline      = '';              ///< The main headline for the html page.
    protected $pageContent   = '';              ///< Contains the custom html of the current page. This will be added to the default html of each page.
    protected $menu;                            ///< An object of the menu of this page
    protected $showThemeHtml = true;            ///< If set to true then the custom html code of the theme for each page will be included.
    protected $showMenu      = true;            ///< If set to true then the menu will be included.
    protected $hasNavbar     = false;           ///< Flag if the current page has a navbar.
    protected $showModal     = false;           ///< If set to true then html code for a modal window will be included.
    protected $cssFiles      = array();         ///< An array with all necessary cascading style sheets files for the html page.
    protected $jsFiles       = array();         ///< An array with all necessary javascript files for the html page.
    protected $rssFiles      = array();         ///< An array with all necessary rss files for the html page.
    protected $printMode     = false;           ///< A flag that indicates if the page should be styled in print mode then no colors will be shown
    protected $javascriptContent        = '';   ///< Contains the custom javascript of the current page. This will be added to the header part of the page.
    protected $javascriptContentExecute = '';   ///< Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.

    /**
     * Constructor creates the page object and initialized all parameters
     * @param string $headline A string that contains the headline for the page that will be shown in the <h1> tag.
     */
    public function __construct($headline = '')
    {
        $this->menu = new HtmlNavbar('menu_main_script', $headline, $this);

        $this->setHeadline($headline);

        $this->addCssFile('adm_program/libs/bootstrap/css/bootstrap.css');
        $this->addJavascriptFile('adm_program/libs/jquery/jquery.js');
        $this->addJavascriptFile('adm_program/system/js/common_functions.js');
        $this->addJavascriptFile('adm_program/libs/bootstrap/js/bootstrap.js');
    }

    /**
     * The method will return the filename. If you are in debug mode than it will return the
     * not minified version of the filename otherwise it will return the minified version.
     * Therefore you must provide 2 versions of the file. One with a @b min before the file extension
     * and one version without the @b min.
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
     * Adds a cascading style sheets file to the html page.
     * @param string $cssFile The url with filename or the relative path starting with @i adm_program of the css file.
     */
    public function addCssFile($cssFile)
    {
        if (!in_array($cssFile, $this->cssFiles, true))
        {
            if (strpos($cssFile, 'http') !== false)
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
     * @param string $jsFile The url with filename or the relative path starting with @i adm_program of the javascript file.
     */
    public function addJavascriptFile($jsFile)
    {
        if (!in_array($jsFile, $this->jsFiles, true))
        {
            if (strpos($jsFile, 'http') !== false)
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
     * @param bool   $executeAfterPageLoad (optional) If set to @b true the javascript code will be executed after
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
     * create and show Mainmenu
     * @param bool $details  indicator to set if there should be details in the menu.
     * @return HTML of the Menu
     */
    public function showMainMenu($details = true)
    {
        global $gL10n, $gPreferences, $gValidLogin, $gDb, $gCurrentUser;
        $men_icon  = '/dummy.png';
        $full_menu = '';

        // display Menu
        $sql = 'SELECT *
          FROM '.TBL_MENU.'
          where men_parent_id is null
         ORDER BY men_order';
        $main_men_statement = $gDb->query($sql);

        while ($main_men = $main_men_statement->fetchObject())
        {
            $unreadBadge = '';

            // display Menu
            $sql = 'SELECT *
              FROM '.TBL_MENU.'
              where men_parent_id = ? -- $main_men->men_id
             ORDER BY men_parent_id DESC, men_order';
            $statement = $gDb->queryPrepared($sql, array($main_men->men_id));

            if($statement->rowCount() > 0)
            {
                $last = '';

                while ($row = $statement->fetchObject())
                {
                    if($row->men_parent_id != $last)
                    {
                        if($last > 0)
                        {
                            $full_menu .= $Menu->show($details);
                        }
                        $Menu = new Menu($main_men->men_modul_name, $gL10n->get($main_men->men_translate_name));
                        $last = $row->men_parent_id;
                    }

                    $men_display = true;
                    $desc = '';

                    if(strlen($row->men_translate_desc) > 2)
                    {
                        $desc = $gL10n->get($row->men_translate_desc);
                        if($desc == '##' || $desc[0] == '#')
                        {
                            $desc = $row->men_translate_desc;
                        }
                    }

                    // Read current roles rights of the menu
                    $displayMenu = new RolesRights($gDb, 'men_display', $row->men_id);
                    $rolesDisplay = $displayMenu->getRolesIds();

                    if($row->men_need_enable == 1)
                    {
                        if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1  || ($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin))
                        {
                            $men_display = true;
                        }
                        else
                        {
                            $men_display = false;
                        }
                    }

                    $men_url = $row->men_url;

                    if(strlen($row->men_icon) > 2)
                    {
                        $men_icon = $row->men_icon;
                    }

                    $men_translate_name = $gL10n->get($row->men_translate_name);
                    if($men_translate_name == '##' || $men_translate_name[0] == '#')
                    {
                        $men_translate_name = $row->men_translate_name;
                    }

                    //special case because there are differnent links if you are logged in or out for mail
                    if($row->men_modul_name === 'mail' && $gValidLogin)
                    {
                        // get number of unread messages for user
                        $message = new TableMessage($gDb);
                        $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

                        if($unread > 0)
                        {
                            $unreadBadge = '<span class="badge">' . $unread . '</span>';
                        }

                        $men_url = '/adm_program/modules/messages/messages.php';
                        $men_icon = 'messages.png';
                        $men_translate_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                    }

                    if(count($rolesDisplay) >= 1)
                    {
                        // check for rigth to show the menue
                        if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                        {
                            $men_display = false;
                        }
                    }

                    // special check for "newreg"
                    if($row->men_modul_name === 'newreg')
                    {
                        $men_display = false;
                        if($gCurrentUser->getValue('usr_id') === 0 && $gPreferences['registration_enable_module'] > 0)
                        {
                            $men_display = true;
                        }
                    }

                    // special check for "usrmgt"
                    if($row->men_modul_name === 'usrmgt')
                    {
                        if(!$gCurrentUser->editUsers())
                        {
                            $men_display = false;
                        }
                    }

                    // special check for "roladm"
                    if($row->men_modul_name === 'roladm')
                    {
                        if(!$gCurrentUser->manageRoles())
                        {
                            $men_display = false;
                        }
                    }

                    if($men_display == true)
                    {
                        $Menu->addItem($row->men_modul_name, $men_url, $men_translate_name, $men_icon, $desc);
                    }

                    if($details == true)
                    {
                        //Submenu for Lists
                        if($gValidLogin && $row->men_modul_name === 'lists')
                        {
                            $Menu->addSubItem('lists', 'mylist', '/adm_program/modules/lists/mylist.php',
                                                    $gL10n->get('LST_MY_LIST'));
                            $Menu->addSubItem('lists', 'rolinac', '/adm_program/modules/lists/lists.php?active_role=0',
                                                    $gL10n->get('ROL_INACTIV_ROLE'));
                        }

                        //Submenu for Dates
                        if(($gPreferences['enable_dates_module'] == 1 && $row->men_modul_name === 'dates')
                        || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin && $row->men_modul_name === 'dates'))
                        {
                            $Menu->addSubItem('dates', 'olddates', '/adm_program/modules/dates/dates.php?mode=old',
                                                    $gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
                        }
                    }
                }
                $full_menu .= $Menu->show($details);
            }

            $this->menu->addItem(
                'menu_item_private_message', FOLDER_MODULES . '/messages/messages.php', $gL10n->get('SYS_MESSAGES') . $unreadBadge,
                'messages.png', 'right', 'menu_item_modules', 'admidio-default-menu-item'
            );
        }

        return $full_menu;
    }

    /**
     * Adds the modal menu
     * @return void
     */
    public function addModalMenu()
    {
        global $gL10n, $gPreferences, $gValidLogin, $gDb, $gCurrentUser;

        // display Menu
        $sql = 'SELECT *
          FROM '.TBL_MENU.'
          where men_parent_id is null
         ORDER BY men_order';
        $main_men_statement = $gDb->query($sql);

        while ($main_men = $main_men_statement->fetchObject())
        {
            // display Menu
            $sql = 'SELECT *
              FROM '.TBL_MENU.'
              where men_parent_id = ? -- $main_men->men_id
             ORDER BY men_parent_id DESC, men_order';
            $statement = $gDb->queryPrepared($sql, array($main_men->men_id));

            if($statement->rowCount() > 0)
            {
                $last = '';

                while ($row = $statement->fetchObject())
                {
                    if($row->men_parent_id != $last)
                    {
                        $this->menu->addItem('menu_item_'.$main_men->men_modul_name, null, $gL10n->get($main_men->men_translate_name), 'application_view_list.png', 'right', 'navbar', 'admidio-default-menu-item');
                        $last = $row->men_parent_id;
                    }

                    $men_display = true;
                    $desc = '';

                    if(strlen($row->men_translate_desc) > 2)
                    {
                        $desc = $gL10n->get($row->men_translate_desc);
                    }

                    // Read current roles rights of the menu
                    $displayMenu = new RolesRights($gDb, 'men_display', $row->men_id);
                    $rolesDisplayRight = $displayMenu->getRolesIds();

                    if($row->men_need_enable == 1)
                    {
                        if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1  || ($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin))
                        {
                            $men_display = true;
                        }
                        else
                        {
                            $men_display = false;
                        }
                    }

                    $men_url = $row->men_url;
                    $men_icon = $row->men_icon;
                    $men_translate_name = $gL10n->get($row->men_translate_name);

                    //special case because there are differnent links if you are logged in or out for mail
                    if($row->men_modul_name === 'mail' && $gValidLogin)
                    {
                        $unreadBadge = '';

                        // get number of unread messages for user
                        $message = new TableMessage($gDb);
                        $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

                        if($unread > 0)
                        {
                            $unreadBadge = '<span class="badge">' . $unread . '</span>';
                        }

                        $men_url = '/adm_program/modules/messages/messages.php';
                        $men_icon = '/icons/messages.png';
                        $men_translate_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
                    }

                    if(count($rolesDisplayRight) >= 1)
                    {
                        // check for rigth to show the menue
                        if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
                        {
                            $men_display = false;
                        }
                    }

                    // special check for "newreg"
                    if($row->men_modul_name === 'newreg')
                    {
                        $men_display = false;
                        if($gCurrentUser->getValue('usr_id') === 0 && $gPreferences['registration_enable_module'] > 0)
                        {
                            $men_display = true;
                        }
                    }

                    // special check for "usrmgt"
                    if($row->men_modul_name === 'usrmgt')
                    {
                        if(!$gCurrentUser->editUsers())
                        {
                            $men_display = false;
                        }
                    }

                    // special check for "roladm"
                    if($row->men_modul_name === 'roladm')
                    {
                        if(!$gCurrentUser->manageRoles())
                        {
                            $men_display = false;
                        }
                    }

                    if($men_display == true)
                    {
                        $this->menu->addItem($row->men_modul_name, $men_url, $men_translate_name, $men_icon, 'right', 'menu_item_'.$main_men->men_modul_name, 'admidio-default-menu-item');
                    }
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
                'menu_item_logout', '/adm_program/system/logout.php', $gL10n->get('SYS_LOGOUT'),
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
                'menu_item_login', '/adm_program/system/login.php', $gL10n->get('SYS_LOGIN'),
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
     * Returns the headline of the current Admidio page. This is the text of the <h1> tag of the page.
     * @return string Returns the headline of the current Admidio page.
     */
    public function getHeadline()
    {
        return $this->headline;
    }

    /**
     * Returns the menu object of this html page.
     * @return \HtmlNavbar Returns the menu object of this html page.
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
     * Flag if the current page has a navbar.
     * @return void
     */
    public function hasNavbar()
    {
        $this->hasNavbar = true;
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
        global $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser, $gPreferences;
        global $gValidLogin, $gProfileFields, $gHomepage, $gDbType;
        global $g_root_path;

        $headerContent    = '';
        $htmlMyHeader     = '';
        $htmlMyBodyTop    = '';
        $htmlMyBodyBottom = '';
        $htmlMenu         = '';
        $htmlHeadline     = '';

        if($this->showMenu)
        {
            // add modules and administration modules to the menu
            $this->addModalMenu();
            $htmlMenu = $this->menu->show();
        }
    }

    /**
     * adds the main necessary files
     */
    private function addMainFilesAndContent()
    {
        global $gPreferences;

        // add admidio css file at last because there the user can redefine all css
        $this->addCssFile(THEME_PATH.'/css/admidio.css');

        // add custom css file if it exists to add own css styles without edit the original admidio css
        if(is_file(THEME_PATH.'/css/custom.css'))
        {
            $this->addCssFile(THEME_PATH.'/css/custom.css');
        }

        // if print mode is set then add a print specific css file
        if ($this->printMode)
        {
            $this->addCssFile(THEME_PATH.'/css/print.css');
        }

        // add custom css file if it exists to add own css styles without edit the original admidio css
        if (is_file(THEME_URL.'/css/custom.css'))
        {
            $this->addCssFile(THEME_URL.'/css/custom.css');
        }

        if (isset($gPreferences['system_browser_update_check']) && $gPreferences['system_browser_update_check'] == 1)
        {
            $this->addJavascriptFile('adm_program/libs/browser-update/browser-update.js');
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
    }

    /**
     * Loads the content of the given theme file
     * @param string $filename Filename to load out of the theme directory
     * @return string
     */
    private function getFileContent($filename)
    {
        global $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser, $gPreferences;
        global $gValidLogin, $gProfileFields, $gHomepage, $gDbType;
        global $g_root_path;

        ob_start();
        include(THEME_ADMIDIO_PATH . '/' . $filename);
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
        $headerContent = '';
        $htmlMyHeader  = '';

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

        // load content of theme files
        if ($this->showThemeHtml)
        {
            $htmlMyHeader = $this->getFileContent('my_header.php');
        }

        $htmlHeader = '<head>
            <!-- (c) 2004 - 2017 The Admidio Team - ' . ADMIDIO_HOMEPAGE . ' -->

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
        $htmlHeader .= $htmlMyHeader;
        $htmlHeader .= '</header>';

        return $htmlHeader;
    }

    /**
     * Builds the HTML-Body content
     * @return string
     */
    private function getHtmlBody()
    {
        $htmlMyBodyTop    = '';
        $htmlMyBodyBottom = '';
        $htmlMenu         = '';
        $htmlHeadline     = '';

        if ($this->showMenu)
        {
            // add modules and administration modules to the menu
            $this->showMainMenu();
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

        // load content of theme files
        if ($this->showThemeHtml)
        {
            $htmlMyBodyTop    = $this->getFileContent('my_body_top.php');
            $htmlMyBodyBottom = $this->getFileContent('my_body_bottom.php');
        }

        $htmlBody = '<body>';
        $htmlBody .= $htmlMyBodyTop;
        $htmlBody .= '<div class="admidio-content">';
        $htmlBody .= $htmlHeadline;
        $htmlBody .= $htmlMenu;
        $htmlBody .= $this->pageContent;
        $htmlBody .= '</div>';
        $htmlBody .= $htmlMyBodyBottom;
        $htmlBody .= '</body>';

        return $htmlBody;
    }

    /**
     * This method send the whole html code of the page to the browser. Call this method
     * if you have finished your page layout.
     * @param bool $directOutput If set to @b true (default) the html page will be directly send
     *                           to the browser. If set to @b false the html will be returned.
     * @return string|void If $directOutput is set to @b false this method will return the html code of the page.
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
}
