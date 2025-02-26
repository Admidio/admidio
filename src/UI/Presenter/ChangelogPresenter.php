<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;

//use Admidio\Changelog\Service\ChangelogService;


/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the changelog module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new Changelog('changelog_form', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ChangelogPresenter extends PagePresenter
{


    /**
     * Create a changelog display page
     * @throws Exception|\Smarty\Exception
     */
    public function createList()
    {
        // global $gCurrentSession, $gL10n, $gDb, $gSettingsManager;
// TODO_RK
    }
}
