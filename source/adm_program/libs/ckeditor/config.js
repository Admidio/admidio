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
        ['Font','FontSize', 'Preview', '-', 'Bold', 'Italic', 'TextColor', '-', 'JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock', '-', 
        'NumberedList', 'BulletedList', '-', 'Image', 'Link', 'Unlink', 'Table']
    ];

    config.toolbar_AdmidioGuestbook =
    [
        ['Font','FontSize', 'Preview', '-', 'Bold', 'Italic', 'TextColor', 'BGColor', '-', 'JustifyLeft','JustifyCenter','JustifyRight', '-', 
        'Smiley', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList']
    ];
};
