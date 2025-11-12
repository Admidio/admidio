<?php
namespace Admidio\Infrastructure;

/**
 * @brief Admidio specific enhancements of the PHP exception class
 *
 * This class extends the default PHP exception class with an Admidio specific
 * constructor. The exception gets a language string as parameter and returns the
 * translated error if an exception is thrown
 *
 * **Code example**
 * ```
 * try {
 *    if($bla == 1)
 *    {
 *        // throw new exception with a translatable text
 *        throw new Exception('SYS_NOT_VALID_DATE_FORMAT');
 *    }
 *    ...
 * } catch(Throwable $e) {
 *    // show translated message
 *    echo $e->getMessage();
 * }
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Exception extends \Exception
{
    /**
     * Constructor saves the parameters to the class and will call the parent constructor. Also, a **rollback**
     * of open database translation will be done.
     * @param string $message Translation **id** or simple text that should be shown when exception is caught
     * @param array<int,string> $params Optional parameter for language string of translation id
     * @throws Exception
     */
    public function __construct($message, $params = array())
    {
        global $gLogger, $gDb, $gL10n;

        if ($gDb instanceof Database) {
            // if there is an open transaction we should perform a rollback
            $gDb->rollback();
        }

        // if text is a translation-id then translate it
        if (Language::isTranslationStringId($message)) {
            $message = $gL10n->get($message, $params);
        }

        $gLogger->notice('Exception is thrown!', array('message' => $message));

        parent::__construct($message);
    }
}
