<?php
/******************************************************************************
 * Extends the ckeditor-php-class for admidio requirements
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Beside the methods of the parent class there are the following additional methods:
 *
 * createDefaultEditor($name) - creates the editor with 1 line of buttons
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/libs/ckeditor/ckeditor_php5.php');

class CKEditorSpecial extends CKEditor
{
    // creates the editor with 1 line of buttons
    // requires the name (id) of the element
    public function createEditor($elementName, $value = '', $toolbar = 'AdmidioDefault', $height = '300px')
    {
        global $gPreferences;
        
        $this->returnOutput = true;
        $this->config['toolbarCanCollapse'] = false;
        $this->config['filebrowserImageUploadUrl'] = '../../system/ckeditor_upload_handler.php';
        $this->config['fontSize_sizes'] = '8/8pt;9/9pt;10/10pt;11/11pt;12/12pt;14/14pt;16/16pt;18/18pt;20/20pt;22/22pt;24/24pt;26/26pt;28/28pt;36/36pt;48/48pt;72/72pt';
        $this->config['resize_maxWidth']  = '100%';
        $this->config['uiColor']  = $gPreferences['system_js_editor_color'];
        $this->config['height']   = $height;
        //$this->config['toolbar']  = 'Full';
        $this->config['toolbar']  = $toolbar;
        $this->config['language'] = $gPreferences['system_language'];
        $this->config['contentsCss'] = 'body {font-family: Arial, Verdana, sans-serif; font-size: 11pt; }';
        //$this->addEventHandler('instanceReady', 'function (ev) {$(".cke_wrapper").css("background-color", $("body").css("background-color")); }');

        if($gPreferences['system_js_editor_enabled'] == 0)
        {
            return $this->createTextArea($elementName, $value);
        }
        else
        {
            return $this->Editor($elementName, $value);
        }
    }

    // creates the editor with 1 line of buttons
    // use the default settings of ecards
    public function createEcardEditor($elementName, $value = '', $toolbar = 'AdmidioEcard', $height = '300px')
    {
        global $gPreferences;
        
        $this->returnOutput = true;
        $this->config['toolbarCanCollapse'] = false;
        $this->config['extraPlugins']    = 'ecardtemplate';
        $this->config['fontSize_sizes']  = '8/8pt;9/9pt;10/10pt;11/11pt;12/12pt;14/14pt;16/16pt;18/18pt;20/20pt;22/22pt;24/24pt;26/26pt;28/28pt;36/36pt;48/48pt;72/72pt';
        $this->config['resize_maxWidth'] = '100%';
        $this->config['uiColor']  = $gPreferences['system_js_editor_color'];
        $this->config['height']   = $height;
        $this->config['toolbar']  = $toolbar;
        $this->config['language'] = $gPreferences['system_language'];
        $this->config['contentsCss'] = 'body {font-family: Arial, Verdana, sans-serif; font-size: 11pt; }';

        if($gPreferences['system_js_editor_enabled'] == 0)
        {
            return $this->createTextArea($elementName, $value);
        }
        else
        {
            return $this->Editor($elementName, $value);
        }
    }
    
    // creates a textarea element with the config of the ckeditor
    private function createTextArea($elementName, $value = '')
    {
        $out = '<textarea id="'.$elementName.'" name="'.$elementName.'" 
                    style="width: '.$this->config['width'].'; height: '.$this->config['height'].';">
                    '.$value.'</textarea>';
        return $out;
    }
}

?>