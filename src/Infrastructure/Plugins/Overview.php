<?php
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
namespace Admidio\Infrastructure\Plugins;

use Admidio\UI\Presenter\PagePresenter;
use Smarty\Smarty;
use Admidio\Infrastructure\Exception;

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
     * @var array An array with all the variables that should be assigned to the template.
     */
    protected array $smartyVariables = array();

    /**
     * Constructor for the overview plugin.
     * @param string $name Name of the plugin. This is ideally the folder name of the plugin.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Method assigns a variable to the template that should be used. The variable could be an array or a string.
     * @param string $name Name of the variable within the template
     * @param array|string $value Content of the variable.
     * @return void
     */
    public function assignTemplateVariable(string $name, array|string $value): void
    {
        $this->smartyVariables[$name] = $value;
    }

    /**
     * Creates a Smarty object and store this in a class member.
     * @return Smarty Returns the initialized Smarty object.
     * @throws Exception
     */
    public function createSmartyObject(): Smarty
    {
        global $gL10n, $gCurrentOrganization;

        try {
            $this->smarty = new Smarty();

            // initialize php template engine smarty
            $this->smarty->setTemplateDir(THEME_PATH . '/templates/');
            $this->smarty->addTemplateDir(ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $this->name . '/templates/');
            $this->smarty->setCacheDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/cache/');
            $this->smarty->setCompileDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/compile/');
            $this->smarty->registerPlugin('function', 'array_key_exists', 'Admidio\Infrastructure\Plugins\Smarty::arrayKeyExists');
            $this->smarty->registerPlugin('function', 'is_translation_string_id', 'Admidio\Infrastructure\Plugins\Smarty::isTranslationStringID');

            $this->smarty->assign('name', $this->name);
            $this->smarty->assign('l10n', $gL10n);
            $this->smarty->assign('urlAdmidio', ADMIDIO_URL);
            $this->smarty->assign('currentOrganization', $gCurrentOrganization);

            return $this->smarty;
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * Creates the html page of the given template. In addition to the html method, this method
     * also includes the html header with javascript and css files.
     * @param string $template Name of the template file that should be used.
     */
    public function showHtmlPage(string $template): void
    {
        $overviewPage = new PagePresenter('adm_overview_plugin');
        $overviewPage->setInlineMode();
        $overviewPage->addTemplateFile(ADMIDIO_PATH . FOLDER_PLUGINS . '/' . $this->name . '/templates/' . $template);

        foreach($this->smartyVariables as $name => $value) {
            $overviewPage->assignSmartyVariable($name, $value);
        }

        $overviewPage->show();
    }

    /**
     * Creates the html of the given template and return the complete html code.
     * @param string $template Name of the template file that should be used.
     * @return string Returns the html of the template
     * @throws Exception
     */
    public function html(string $template): string
    {
        try {
            if (!isset($this->smarty)) {
                $this->createSmartyObject();
            }

            foreach($this->smartyVariables as $name => $value) {
                $this->smarty->assign($name, $value);
            }

            return $this->smarty->fetch($template);
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
