<?php
namespace Admidio\Plugins;

use Smarty\Smarty;
use Admidio\Exception;

/**
 * @brief Support for the plugins used at the overview page
 *
 * **Code example**
 * ```
 * // create content of plugin
 * $myPlugin = new Overview(basename(__DIR__));
 * $myPlugin->assignTemplateVariable('message',$gL10n->get('SYS_NO_ENTRIES'));
 * echo $myPlugin->html('plugin.my.tpl');
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Overview
{
    /**
     * @var string The name of the overview plugin. This should be the name of the folder.
     */
    protected string $name = '';
    /**
     * @var Smarty An object ot the Smarty template engine.
     */
    protected Smarty $smarty;

    /**
     * Constructor for the overview plugin.
     * @param string $name Name of the plugin. This is ideally the folder name of the plugin.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Method assigns a Smarty
     * @param string $name Name of the variable within the template
     * @param string|array $value Content of the variable.
     * @return void
     * @throws Exception
     */
    public function assignTemplateVariable(string $name, $value)
    {
        if (!isset($this->smarty)) {
            $this->createSmartyObject();
        }
        $this->smarty->assign($name, $value);
    }

    /**
     * Creates a Smarty object and store this in a class member.
     * @return Smarty Returns the initialized Smarty object.
     * @throws Exception
     */
    public function createSmartyObject(): Smarty
    {
        global $gL10n;

        try {
            $this->smarty = new Smarty();

            // initialize php template engine smarty
            $this->smarty->setTemplateDir(THEME_PATH . '/templates/');
            $this->smarty->addTemplateDir(ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $this->name . '/templates/');
            $this->smarty->setCacheDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/cache/');
            $this->smarty->setCompileDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/compile/');
            $this->smarty->registerPlugin('function', 'array_key_exists', 'Admidio\Plugins\Smarty::arrayKeyExists');
            $this->smarty->registerPlugin('function', 'is_translation_string_id', 'Admidio\Plugins\Smarty::isTranslationStringID');

            $this->smarty->assign('name', $this->name);
            $this->smarty->assign('l10n', $gL10n);
            $this->smarty->assign('urlAdmidio', ADMIDIO_URL);

            return $this->smarty;
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Creates the html of the given template and return the complete html code.
     * @return string Returns the html of the template
     * @throws Exception
     */
    public function html(string $template): string
    {
        try {
            if (!isset($this->smarty)) {
                $this->createSmartyObject();
            }

            return $this->smarty->fetch($template);
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
