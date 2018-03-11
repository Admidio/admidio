<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle the navigation within a module and could create a html navigation bar
 *
 * This class stores every url that you add to the object in a stack. From
 * there it's possible to return the last called url or a previous url. This
 * can be used to allow a navigation within a module. It's also possible
 * to create a html navigation bar. Therefore you should add a url and a link text
 * to the object everytime you submit a url.
 *
 * **Code example:**
 * ```
 * // start the navigation in a module (the object $gNavigation is created in common.php)
 * $gNavigation->addStartUrl('https://www.example.com/index.php', 'Example-Module');
 *
 * // add a new url from another page within the same module
 * $gNavigation->addUrl('https://www.example.com/addentry.php', 'Add Entry');
 *
 * // optional you can now create the html navigation bar
 * $gNavigation->getHtml();
 *
 * // if you want to remove the last entry from the stack
 * $gNavigation->deleteLastUrl();
 * ```
 *
 * **Code example:**
 * ```
 * // show a navigation bar in your html code
 * ... <br /><?php echo $gNavigation->getHtmlNavigationBar('id-my-navigation'); ?><br /> ...
 * ```
 */
class Navigation
{
    /**
     * @var array<int,array<string,string>>
     */
    private $urlStack = array();

    /**
     * Constructor will initialize the local parameters
     */
    public function __construct()
    {

    }

    /**
     * Initialize the url stack and set the internal counter to 0
     * @return void
     */
    public function clear()
    {
        $this->urlStack = array();
    }

    /**
     * Number of urls that a currently in the stack
     * @return int
     */
    public function count()
    {
        return count($this->urlStack);
    }

    /**
     * Initialize the stack and adds a new url to the navigation stack.
     * If a html navigation bar should be created later than you should fill the text and maybe the icon.
     * @param string $url  The url that should be added to the navigation stack.
     * @param string $text A text that should be shown in the html navigation stack and
     *                     would be linked with the $url.
     * @param string $icon A url to the icon that should be shown in the html navigation stack
     *                     together with the text and would be linked with the $url.
     * @return void
     */
    public function addStartUrl($url, $text = null, $icon = null)
    {
        $this->clear();
        $this->addUrl($url, $text, $icon);
    }

    /**
     * Add a new url to the navigation stack. If a html navigation bar should be created later
     * than you should fill the text and maybe the icon. Before the url will be added to the stack
     * the method checks if the current url was already added to the url.
     * @param string $url  The url that should be added to the navigation stack.
     * @param string $text A text that should be shown in the html navigation stack and
     *                     would be linked with the $url.
     * @param string $icon A url to the icon that should be shown in the html navigation stack
     *                     together with the text and would be linked with the $url.
     * @return bool Returns true if the navigation-stack got changed and false if not.
     */
    public function addUrl($url, $text = null, $icon = null)
    {
        global $gLogger;

        if (!strValidCharacters($url, 'url'))
        {
            // TODO throw Exception or return false
            $gLogger->notice('NAVIGATION: Invalid URL added to navigation-stack!', array('url' => $url, 'text' => $text, 'icon' => $icon));
        }

        $count = count($this->urlStack);

        // if the last url is equal to the new url than don't add the new url
        if ($count > 0 && $url === $this->urlStack[$count - 1]['url'])
        {
            return false;
        }

        // if the second last url is equal to the new url then only remove the last url
        if ($count > 1 && $url === $this->urlStack[$count - 2]['url'])
        {
            array_pop($this->urlStack);
        }
        else
        {
            $this->urlStack[] = array('url' => $url, 'text' => $text, 'icon' => $icon);
        }

        return true;
    }

    /**
     * Removes the last url from the stack. If there is only one element
     * in the stack than don't remove it, because this will be the initial
     * url that should be called.
     * @return array<string,string>|null Returns the removed element
     */
    public function deleteLastUrl()
    {
        if (count($this->urlStack) > 1)
        {
            return array_pop($this->urlStack);
        }

        return null;
    }

    /**
     * Get the last added url from the stack.
     * @return string|null Returns the last added url. If the stack is empty returns null
     */
    public function getUrl()
    {
        $count = count($this->urlStack);

        if ($count === 0)
        {
            // TODO throw Exception
            return null;
        }

        return $this->urlStack[$count - 1]['url'];
    }

    /**
     * Get the previous url from the stack. This is not the last url that was added to the stack!
     * @return string|null Returns the previous added url. If only one url is added it returns this one. If no url is added returns null
     */
    public function getPreviousUrl()
    {
        $count = count($this->urlStack);

        if ($count === 0)
        {
            // TODO throw Exception
            return null;
        }

        // Only one url, take this one
        $entry = max(0, $count - 2);

        return $this->urlStack[$entry]['url'];
    }

    /**
     * Returns html code that contain a link back to the previous url.
     * @param string $id Optional you could set an id for the back link
     * @return string Returns html code of the navigation back link.
     */
    public function getHtmlBackButton($id = 'adm-navigation-back')
    {
        global $gL10n;

        // now get the "new" last url from the stack. This should be the last page
        $url = $this->getPreviousUrl();

        // if no page was found then show nothing
        if ($url === null)
        {
            return '';
        }

        return '
        <div id="'.$id.'" class="admNavigation admNavigationBack">
            <a class="btn" href="'.$url.'">'.
                '<img src="'.THEME_URL.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" />'
                .$gL10n->get('SYS_BACK').
            '</a>
        </div>';
    }

    /**
     * Returns html code that contain links to all previous added urls from the stack.
     * The output will look like: `FirstPage > SecondPage > ThirdPage ...`
     * The last page of this list is always the current page.
     * @param string $id Optional you could set an id for the navigation bar
     * @return string Returns html code of the navigation bar.
     */
    public function getHtmlNavigationBar($id = 'adm-navigation-bar')
    {
        $links = array();

        foreach ($this->urlStack as $url)
        {
            if (strlen($url['text']) > 0)
            {
                $links[] = '<a href="'.$url['url'].'">'.$url['text'].'</a>';
            }
        }

        // if no links where found then show nothing
        if (count($links) === 0)
        {
            return '';
        }

        return '<div id="'.$id.'" class="admNavigation admNavigationBar">'.implode('', $links).'</div>';
    }
}
