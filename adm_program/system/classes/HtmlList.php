<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create html lists
 *
 * This class creates html list elements.
 * Create a list object for ordered, unordered or data list an add the list items.
 * The class supports data lists and lists and combination of nested lists and data lists.
 * The parsed list object is returned as string.
 *
 * **Code example**
 * ```
 * // Example 1: Creating datalist
 *
 * // Get instance
 * $list = new HtmlList('dl', 'id_dl', 'class'); // Parameter for list type, id and class are optional ( Default list type = ul )
 * // In html strict a data list is determined to be nested in a list element if used in an ordered/unordered list
 * $list->addListItem();
 * // add  2 list items term and description as string. Arrays are not supported!
 * $list->addDataListItems('term_1', 'Listdata_1');
 * $list->addDataListItems('term_2', 'Listdata_2');
 * // get parsed datalist as string
 * echo $list->getHtmlList();
 * ```
 *
 * **Code example**
 * ```
 * // Example 2: Creating  ordered list
 *
 * // Get Instance
 * $list = new HtmlList('ol', 'id_ol', 'class');
 * // Set type Attribute
 * $list->addAttribute('type', 'square');
 * // Define a list element with ID "Item_0" and data "Listdata_1" as string
 * $list->addListItem('Item_0', 'Listdata_1');
 * // Next list elements: Defining a term, a datalist is automatically nested in the list element
 * $list->addListItem('Item_1', 'Listdata_2', 'term_2');
 * $list->addListItem('Item_2', 'Listdata_3', 'term_3');
 * $list->addListItem('Item_3', 'Listdata_4', 'term_4');
 * // Also manually configuration is possible
 * // Define list element "li" with attribute ID = Item_5
 * $list->addListItem('Item_5');
 * // Define datalist in link element
 * $list->addDataList();
 * // Define several term and description of the data list
 * $list->addDataListItems('term_5', 'Listdata_5');
 * list->addDataListItems('term_5.1', 'Listdata_5.1');
 * list->addDataListItems('term_5.2', 'Listdata_5.2');
 * // get parsed datalist as string
 * echo $list->getHtmlList();
 * ```
 */
class HtmlList extends HtmlElement
{
    /**
     * Constructor creates the element
     *
     * @param string $list  List element ( ul/ol Default: ul)
     * @param string $id    Id of the list
     * @param string $class Class name of the list
     */
    public function __construct($list = 'ul', $id = null, $class = null)
    {
        parent::__construct($list);

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($class !== null) {
            $this->addAttribute('class', $class);
        }
    }

    /**
     * Add datalist (dl).
     * @param string $id          id Attribute
     * @param string $term        term as string for datalist
     * @param string $description description as string for data description
     */
    public function addDataList($id = null, $term = null, $description = null)
    {
        // First check whether open list item tag  must be closed before setting new item
        if (in_array('dl', $this->arrParentElements, true)) {
            $this->closeParentElement('dl');
        }
        $this->addParentElement('dl');

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($term !== null && $description !== null) {
            $this->addDataListItems($term, $description);
        }
    }

    /**
     * Add term and description to datalist (dl).
     * @param string $term        Term as string for datalist
     * @param string $description Description as string for data
     */
    public function addDataListItems($term, $description)
    {
        $this->addElement('dt', '', '', $term);
        $this->addElement('dd', '', '', $description);
    }

    /**
     * Add list item (li).
     * @param string $id   id Attribute
     * @param string $data element data
     * @param string $term optional term as string for nested datalist
     */
    public function addListItem($id = null, $data = null, $term = null)
    {
        if ($data !== null) {
            if ($term !== null) {
                // First check whether open list item tag  must be closed before setting new item
                if (in_array('li', $this->arrParentElements, true)) {
                    $this->closeParentElement('li');
                }

                // Set new item
                $this->addParentElement('li');

                if ($id !== null) {
                    $this->addAttribute('id', $id);
                }

                // Define datalist with term and data as description
                $this->addDataList(null, $term, $data);
                $this->closeParentElement('li');
            } else {
                $this->addElement('li');

                if ($id !== null) {
                    $this->addAttribute('id', $id);
                }

                $this->addData($data);
            }
        } else {
            $this->closeParentElement('li');
            // handle as parent element maybe a datalist could be nested next
            $this->addParentElement('li');

            if ($id !== null) {
                $this->addAttribute('id', $id);
            }
        }
    }

    /**
     * Get the parsed html list
     *
     * @return string Returns the validated html list as string
     */
    public function getHtmlList()
    {
        $this->closeParentElement('.$this->currentElement().');

        return $this->getHtmlElement();
    }
}
