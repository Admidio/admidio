/*
Copyright (c) 2003-2011, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.editorConfig = function( config )
{
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	
    // config.toolbar = 'Full';
     
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
};
