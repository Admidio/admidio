<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle the navigation within a module
 *
 * This class stores every url that you add to the object in a stack. From
 * there it's possible to return the last called url or a previous url. This
 * can be used to allow a navigation within a module.
 *
 * **Code example:**
 * ```
 * // start the navigation in a module (the object $gNavigation is created in common.php)
 * $gNavigation->addFirst('https://www.example.com/index.php');
 *
 * // add a new url from another page within the same module
 * $gNavigation->add('https://www.example.com/addentry.php');
 *
 * // if you want to remove the last entry from the stack
 * $gNavigation->removeLast();
 * ```
 */
class Navigation
{
    /**
     * @var array<int,string>
     */
    private $urlStack;

    /**
     * Navigation constructor.
     * @param array<int,string> $urlStack
     */
    public function __construct(array $urlStack = array())
    {
        $this->urlStack = $urlStack;
    }

    /**
     * Removes all urls from the stack.
     * @return void
     */
    public function reset()
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
     * Get all urls from the stack.
     * @return array<int,string> Returns all urls from the stack.
     */
    public function getAll()
    {
        return $this->urlStack;
    }

    /**
     * Checks the given url.
     * @param string $url The url that should be checked.
     * @throws \UnexpectedValueException
     */
    protected function checkUrl($url)
    {
        global $gLogger;

        if (filter_var($url, FILTER_VALIDATE_URL) === false)
        {
            $gLogger->notice('NAVIGATION: Invalid URL!', array('url' => $url));
            throw new \UnexpectedValueException('Invalid url!');
        }
    }

    /**
     * Initialize the stack and adds a new url to the navigation stack.
     * @param string $url The url that should be added to the navigation stack.
     * @return void
     * @throws \UnexpectedValueException
     */
    public function addFirst($url)
    {
        $this->checkUrl($url); // Check before clearing the stack
        $this->urlStack = array();
        $this->add($url);
    }

    /**
     * Add a new url to the navigation stack. Before the url will be added to the stack
     * the method checks if the current url was already added to the url.
     * @param string $url   The url that should be added to the navigation stack.
     * @param bool   $clear Set to true, to start with an empty stack.
     * @return bool Returns true if the navigation-stack got changed and false if not.
     * @throws \UnexpectedValueException
     */
    public function add($url, $clear = false)
    {
        $this->checkUrl($url);

        if ($clear)
        {
            $this->urlStack = array();
        }
        else
        {
            $count = count($this->urlStack);

            // if the last url is equal to the new url than don't add the new url
            if ($count > 0 && $url === $this->urlStack[$count - 1])
            {
                return false;
            }
        }

        $this->urlStack[] = $url;

        return true;
    }

    /**
     * Get the last added url from the stack.
     * @return string Returns the last added url.
     * @throws \UnderflowException
     */
    public function getLast()
    {
        $count = count($this->urlStack);

        if ($count > 0)
        {
            return $this->urlStack[$count - 1];
        }

        throw new \UnderflowException('Url-Stack is empty!');
    }

    /**
     * Get the previous url from the stack. This is not the last url that was added to the stack!
     * @return string Returns the previous added url.
     * @throws \UnderflowException
     */
    public function getPrevious()
    {
        $count = count($this->urlStack);

        if ($count > 1)
        {
            return $this->urlStack[$count - 2];
        }

        throw new \UnderflowException('Url-Stack has not enough urls!');
    }

    /**
     * Removes the last url from the stack.
     * @return string Returns the removed url
     * @throws \UnderflowException
     */
    public function removeLast()
    {
        if (count($this->urlStack) > 1)
        {
            return array_pop($this->urlStack);
        }

        throw new \UnderflowException('Url-Stack has not enough urls!');
    }

    /**
     * Redirects to the previous page
     * @throws \UnderflowException
     */
    public function goBack()
    {
        $count = count($this->urlStack);

        if ($count > 1)
        {
            array_pop($this->urlStack);

            admRedirect($this->urlStack[$count - 2]);
            // => EXIT
        }

        throw new \UnderflowException('Url-Stack has not enough urls!');
    }
}
