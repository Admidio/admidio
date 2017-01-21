/**
 * @license Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
    // Define changes to default configuration here. For example:
    // config.language = 'fr';
    // config.uiColor = '#AADC6E';

    config.toolbar_AdmidioDefault =
    [
        ['Format', 'FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', '-',
        'NumberedList', 'BulletedList', '-','Image', 'Link', 'Unlink', 'Table', '-', 'Maximize']
    ];

    config.toolbar_AdmidioGuestbook =
    [
        ['Font','FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft','JustifyCenter','JustifyRight', '-',
        'Smiley', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Maximize']
    ];

    config.toolbar_AdmidioPlugin_WC =
    [
        ['Format', 'FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', '-',
        'NumberedList', 'BulletedList', '-','-', '-', '-', 'Table', '-', 'Maximize']
    ];
};

/* Set default width of table to 100% instead of 500%. This fits better with a responsive design. */
CKEDITOR.on('dialogDefinition', function( ev ) {

      var diagName = ev.data.name;
      var diagDefn = ev.data.definition;

      if(diagName === 'table') {
        var infoTab = diagDefn.getContents('info');

        var width = infoTab.get('txtWidth');
        width['default'] = "100%";
      }
});