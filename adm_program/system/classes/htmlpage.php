<?php 
/*****************************************************************************/
/** @class HtmlPage
 *  @brief Creates an Admidio specific complete html page
 *
 *  This class creates a html page with head and body and integrates some Admidio
 *  specific elements like css files, javascript files and javascript code. It
 *  also provides some methods to easily add new html data to the page. The generated
 *  page will automatically integrate the choosen theme. You can optional disable the 
 *  integration of the theme files.
 *  @par Examples
 *  @code // create a simple html page with some text
 *  $page = new HtmlPage();
 *  $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery/jquery.js');
 *  $page->addHeadline('A simple Html page');
 *  $page->addHtml('<strong>This is a simple Html page!</strong>');
 *  $page->show();@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class HtmlPage
{
    protected $pageContent;     ///< Contains the custom html of the current page. This will be added to the default html of each page.
    protected $javascriptContent; ///< Contains the custom javascript of the current page. This will be added to the header part of the page.
    protected $javascriptContentExecute; ///< Contains the custom javascript of the current page that should be executed after pageload. This will be added to the header part of the page.
    protected $title;           ///< The title for the html page and the headline for the Admidio content.
    protected $headline;        ///< The main headline for the html page.
    protected $header;          ///< Additional header that could not be set with the other methods. This content will be add to head of html page without parsing.
    protected $hasNavbar;       ///< Flag if the current page has a navbar.
    protected $containThemeHtml; ///< If set to true then the custom html code of the theme for each page will be included.
    protected $cssFiles;        ///< An array with all necessary cascading style sheets files for the html page.
    protected $jsFiles;         ///< An array with all necessary javascript files for the html page.
    protected $rssFiles;        ///< An array with all necessary rss files for the html page.
    protected $printMode;       ///< A flag that indicates if the page should be styled in print mode then no colors will be shown
    
    /** Constructor creates the page object and initialized all parameters
     *  @param $title A string that contains the title for the page.
     */
    public function __construct($title = '')
    {
        global $g_root_path, $gDebug;
    
        $this->pageContent = '';
        $this->header = '';
        $this->title = $title;
		$this->bodyOnload = '';
        $this->containThemeHtml = true;
        $this->printMode = false;
        $this->hasNavbar = false;
        $this->hasModal  = false;
        
        if($gDebug)
        {
            $this->cssFiles = array($g_root_path.'/adm_program/libs/bootstrap/css/bootstrap.css');
            $this->jsFiles  = array($g_root_path.'/adm_program/libs/jquery/jquery.js', 
                                    $g_root_path. '/adm_program/system/js/common_functions.js',
                                    $g_root_path.'/adm_program/libs/bootstrap/js/bootstrap.js');
        }
        else
        {
            // if not in debug mode only load the minified files
            $this->cssFiles = array($g_root_path.'/adm_program/libs/bootstrap/css/bootstrap.min.css');
            $this->jsFiles  = array($g_root_path.'/adm_program/libs/jquery/jquery.js', 
                                    $g_root_path. '/adm_program/system/js/common_functions.js',
                                    $g_root_path.'/adm_program/libs/bootstrap/js/bootstrap.min.js');
        }
        $this->rssFiles = array();
    }
    
    /** Adds a cascading style sheets file to the html page.
     *  @param $file The url with filename of the css file.
     */
    public function addCssFile($file)
    {
        if(in_array($file, $this->cssFiles) == false)
        {
            $this->cssFiles[] = $file;
        }
    }
    
    public function addHeader($header)
    {
        $this->header .= $header;
    }
    
    /** Set the h1 headline of the current html page. If the title of the page was not set
     *  until now than this will also be the title.
     *  @param $headline A string that contains the headline for the page.
     */
    public function addHeadline($headline)
    {
        if(strlen($this->title) == 0)
        {
            $this->setTitle($headline);
        }
        
        $this->headline = $headline;
        $this->addHtml('<h1>'.$headline.'</h1>');
    }
    
    /** Adds any html content to the page. The content will be added in the order
     *  you call this method. The first call will place the content at the top of 
     *  the page. The second call below the first etc.
     *  @param $html A valid html code that will be added to the page.
     */
    public function addHtml($html)
    {
        $this->pageContent .= $html;
    } 

    /** Adds any javascript content to the page. The javascript will be added in the order
     *  you call this method.
     *  @param $javascriptCode A valid javascript code that will be added to the header of the page.
     *  @param $executeAfterPageLoad If set to @b true the javascript code will be executed after
     *                               the page is fully loaded.
     */
    public function addJavascript($javascriptCode, $executeAfterPageLoad = false)
    {
        if($executeAfterPageLoad)
        {
            $this->javascriptContentExecute .= $javascriptCode;
        }
        else
        {
            $this->javascriptContent .= $javascriptCode;
        }
    } 

    /** Adds a javascript file to the html page.
     *  @param $file The url with filename of the javascript file.
     */
    public function addJavascriptFile($file)
    {
        if(in_array($file, $this->jsFiles) == false)
        {
            $this->jsFiles[] = $file;
        }
    }
    
    /** Adds a RSS file to the html page.
     *  @param $file  The url with filename of the rss file.
     *  @param $title Optional set a title. This is the name of the feed and
     *                will be shown when adding the rss feed.
     */
    public function addRssFile($file, $title = null)
    {
        if($title != null)
        {
            $this->rssFiles[$title] = $file;
        }
        else
        {
            $this->rssFiles[] = $file;            
        }
    }
    
    /** Every html page of Admidio contains three files of the custom theme.
     *  my_header.php, my_body_top.php and my_body_bottom.php
     *  With these files the webmaster can contain custom layout to Admidio.
     *  If these files should not be included in the current page, than
     *  this method must be called.
     */
    public function excludeThemeHtml()
    {
        $this->containThemeHtml = false;
    }
    
    /** Returns the title of the html page.
     *  @return Returns the title of the html page.
     */ 
    public function getTitle()
    {
        return $this->title;
    }
    
    /** Flag if the current page has a navbar.
     */
    public function hasNavbar()
    {
        $this->hasNavbar = true;
        
        // set css clss to hide headline in mobile mode if navbar is shown
        $this->pageContent = str_replace('<h1>'.$this->headline.'</h1>', '<h1 class="hidden-xs">'.$this->headline.'</h1>', $this->pageContent);
    }
    
    /** If print mode is set then a print specific css file will be loaded.
     *  All styles will be more print compatible and are only black, grey and white.
     */
    public function setPrintMode()
    {
        $this->printMode = true;
    }
        
    /** Set the title of the html page. This will also be the h1 headline for the Admidio page.
     *  @param $title A string that contains the title for the page.
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

	/** This method send the whole html code of the page to the browser. Call this method
	 *  if you have finished your page layout.
     *  @param $directOutput If set to @b true (default) the html page will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the page.
	 */
    public function show($directOutput = true)
    {
        global $g_root_path, $gL10n, $gDb, $gCurrentSession, $gCurrentOrganization, $gCurrentUser, $gPreferences, $gValidLogin;
        
        $newLayout        = true;
        $headerContent    = '';
        $htmlMyHeader     = '';
        $htmlMyBodyTop    = '';
        $htmlMyBodyBottom = '';
        
        // add admidio css file at last because there the user can redefine all css
        $this->addCssFile(THEME_PATH.'/css/admidio.css');
        
        // if print mode is set then add a print specific css file
        if($this->printMode)
        {
            $this->addCssFile(THEME_PATH.'/css/print.css');
        }
        
        // load content of theme files
        if($this->containThemeHtml)
        {
            ob_start();
            include(THEME_SERVER_PATH. '/my_header.php');
            $htmlMyHeader = ob_get_contents();
            ob_end_clean();

            ob_start();
            include(THEME_SERVER_PATH. '/my_body_top.php');
            $htmlMyBodyTop = ob_get_contents();
            ob_end_clean();

            // if user had set another db in theme content then switch back to admidio db
            $gDb->setCurrentDB();

            ob_start();
            include(THEME_SERVER_PATH. '/my_body_bottom.php');
            $htmlMyBodyBottom = ob_get_contents();
            ob_end_clean();

            // if user had set another db in theme content then switch back to admidio db
            $gDb->setCurrentDB();
        }
        
        // add css files to page
        foreach($this->cssFiles as $file)
        {
            $headerContent .= '<link rel="stylesheet" type="text/css" href="'.$file.'" />';
        }
        
        // add some special scripts so that ie8 could better understand the Bootstrap 3 framework
        $headerContent .= '<!--[if lt IE 9]>  
            <script src="'.$g_root_path.'/adm_program/libs/html5shiv/html5shiv.min.js"></script>
            <script src="'.$g_root_path.'/adm_program/libs/respond/respond.js"></script>
        <![endif]-->';
        
        // add javascript files to page
        foreach($this->jsFiles as $file)
        {
            $headerContent .= '<script type="text/javascript" src="'.$file.'"></script>';
        }

        // add rss feed files to page
        foreach($this->rssFiles as $title => $file)
        {
            if(is_numeric($title) == false)
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" title="'.$title.'" href="'.$file.'" />';
            }
            else
            {
                $headerContent .= '<link rel="alternate" type="application/rss+xml" href="'.$file.'" />';                
            }
        }
        
        // add organization name to title
        if(strlen($this->title) > 0)
        {
            $this->title = $gCurrentOrganization->getValue('org_longname').' - '.$this->title;
        }
        else
        {
        	$this->title = $gCurrentOrganization->getValue('org_longname');
        }
        
        // add code for a modal window
        $this->addJavascript('$("body").on("hidden.bs.modal", ".modal", function () { $(this).removeData("bs.modal"); });', true);
        $this->addHtml('<div class="modal fade" id="admidio_modal" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog"><div class="modal-content"></div></div>
                        </div>');

        // add javascript code to page        
        if(strlen($this->javascriptContent) > 0)
        {
            $headerContent .= '<script type="text/javascript"><!-- 
                '.$this->javascriptContent.'
            --></script>';
        }

        // add javascript code to page that will be excecuted after page is fully loaded       
        if(strlen($this->javascriptContentExecute) > 0)
        {
            $headerContent .= '<script type="text/javascript"><!-- 
                $(document).ready(function(){
                    $(".icon-information, .icon-link img, [data-toggle=tooltip]").tooltip();
                    '.$this->javascriptContentExecute.'
                });
            --></script>';
        }
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <!-- (c) 2004 - 2014 The Admidio Team - http://www.admidio.org -->
            
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            
            <title>'.$this->title.'</title>

            <script type="text/javascript"><!-- 
        		var gRootPath  = "'. $g_root_path. '"; 
        		var gThemePath = "'. THEME_PATH. '";
        	--></script>';
            
            $html .= $headerContent;
            
            if(strlen($this->header) > 0)
            {
                $html .= $this->header;
            }
            
            $html .= $htmlMyHeader.'
        </head>
        <body>'.
            $htmlMyBodyTop.'
            <div class="admContent">'.$this->pageContent.'</div>'.
            $htmlMyBodyBottom.'          
        </body>
        </html>';

        // now show the complete html of the page
        if($directOutput)
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
?>