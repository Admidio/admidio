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
    /** Original translation id when the exception was constructed from a language key. */
    private ?string $translationId = null;
    /**
     * Constructor saves the parameters to the class and will call the parent constructor.
     *
     * Creating the exception does not touch the database. A request that really ends because of an
     * exception is rolled back by handleException(); an exception that is caught and handled leaves
     * the transaction of its caller intact.
     * @param string $message Translation **id** or simple text that should be shown when exception is caught
     * @param array<int,string> $params Optional parameter for language string of translation id
     * @throws Exception
     */
    public function __construct($message, $params = array())
    {
        global $gLogger, $gL10n;

        // Keep the stable translation id for machine-readable interfaces, while the normal
        // exception message remains the translated human-readable text.
        if (Language::isTranslationStringId($message)) {
            $this->translationId = (string)$message;
            $message = $gL10n->get($message, $params);
        }

        $gLogger->notice('Exception is thrown!', array('message' => $message));

        parent::__construct($message);
    }
    /**
     * Return the original Admidio translation id, if one was supplied.
     */
    public function getTranslationId(): ?string
    {
        return $this->translationId;
    }

}
