<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
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
 * to create a html navigation bar. Therefore, you should add an url and a link text
 * to the object everytime you submit an url.
 *
 * **Code example**
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
 * **Code example**
 * ```
 * // show a navigation bar in your html code
 * ... <br /><?php echo $gNavigation->getHtmlNavigationBar('id-my-navigation'); ?><br /> ...
 * ```
 */
class Navigation
{
    /**
     * @var array<array<string,string,string>> Array with all urls of the navigation class.
     * The sub array will contain the url, a headline and an icon.
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
     * Number of urls that are currently in the stack
     * @return int Returns the number of the urls in the stack.
     */
    public function count()
    {
        return count($this->urlStack);
    }

    /**
     * Initialize the stack and adds a new url to the navigation stack.
     * If a html navigation bar should be created later than you should fill the text and maybe the icon.
     * @param string $url The url that should be added to the navigation stack.
     * @param string $text A text that should be shown in the html navigation stack and
     *                     would be linked with the $url.
     * @param string $icon The name of a fontawesome icon that should be shown in the html navigation stack
     *                     together with the text and would be linked with the $url.
     * @return void
     * @throws AdmException Throws an exception if the url has invalid characters.
     */
    public function addStartUrl($url, $text = null, $icon = null)
    {
        $this->clear();
        $this->addUrl($url, $text, $icon);
    }

    /**
     * Removes the last url from the stack. If there is only one element
     * in the stack than don't remove it, because this will be the initial
     * url that should be called.
     * @return array<string,string>|null Returns the removed element
     */
    public function deleteLastUrl()
    {
        if (count($this->urlStack) > 1) {
            return array_pop($this->urlStack);
        }

        return null;
    }

    /**
     * Add a new url to the navigation stack. If a html navigation bar should be created later
     * than you should fill the text and maybe the icon. Before the url will be added to the stack
     * the method checks if the current url was already added to the url.
     * @param string $url The url that should be added to the navigation stack.
     * @param string $text A text that should be shown in the html navigation stack and
     *                     would be linked with the $url.
     * @param string $icon The name of a fontawesome icon that should be shown in the html navigation stack
     *                     together with the text and would be linked with the $url.
     * @throws AdmException Throws an exception if the url has invalid characters.
     * @return bool Returns true if the navigation-stack got changed and false if not.
     */
    public function addUrl($url, $text = null, $icon = null)
    {
        if (!StringUtils::strValidCharacters($url, 'url')) {
            throw new AdmException('SYS_URL_INVALID_CHAR', array('navigation stack'));
        }

        $count = count($this->urlStack);

        // if the last url is equal to the new url than don't add the new url
        if ($count > 0 && $url === $this->urlStack[$count - 1]['url']) {
            return false;
        }

        // if the text of the last url is equal to the new url and the main url without query parameters is equal
        // than replace the last url with the current url.
        if($count > 0 && $text !== '' && $text === $this->urlStack[$count - 1]['text']) {
            $urlParts = parse_url($url);
            $previousUrlParts = parse_url($this->urlStack[$count - 1]['url']);

            if($urlParts['scheme'] === $previousUrlParts['scheme']
            && $urlParts['host'] === $previousUrlParts['host']
            && $urlParts['path'] === $previousUrlParts['path']) {
                array_pop($this->urlStack);
            }
        }

        // if the second last url is equal to the new url then only remove the last url
        if ($count > 1 && $url === $this->urlStack[$count - 2]['url']) {
            array_pop($this->urlStack);
        } else {
            $this->urlStack[] = array('url' => $url, 'text' => $text, 'icon' => $icon);
        }

        return true;
    }

    /**
     * The navigation stack contains each url. Optional a text and an icon is also set for the url.
     * @return array<int,array<string,string> Array with the navigation stack. The array has the following element **url**, **text** and **icon**
     */
    public function getStack()
    {
        return $this->urlStack;
    }

    /**
     * Get the last added url from the stack.
     * @throws AdmException Throws an exception if no url is in the navigation stack.
     * @return string|null Returns the last added url. If the stack is empty returns null
     */
    public function getUrl()
    {
        $count = count($this->urlStack);

        if ($count === 0) {
            throw new AdmException('No url within the navigation stack.');
        }

        return $this->urlStack[$count - 1]['url'];
    }

    /**
     * Get the previous url from the stack. This is not the last url that was added to the stack!
     * @throws AdmException Throws an exception if no previous url is in the navigation stack.
     * @return string|null Returns the previous added url. If only one url is added it returns this one. If no url is added returns null
     */
    public function getPreviousUrl()
    {
        $count = count($this->urlStack);

        if ($count === 0) {
            throw new AdmException('No previous url within the navigation stack.');
        }

        // Only one url, take this one
        $entry = max(0, $count - 2);

        return $this->urlStack[$entry]['url'];
    }
}
