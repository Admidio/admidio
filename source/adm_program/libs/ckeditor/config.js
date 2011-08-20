/*
Copyright (c) 2003-2011, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.editorConfig = function( config )
{
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	
	//config.removePlugins = 'elementspath, resize';
	config.toolbarCanCollapse = false;
	
    //config.filebrowserImageBrowseUrl = '/browser/browse/type/image';
    config.filebrowserImageUploadUrl = '../../system/ckeditor_upload_handler.php';
    //config.filebrowserWindowWidth = '300';
    //config.filebrowserWindowHeight = '500';
    config.width = '98%';
    //congig.height = '200';
	
    config.toolbar = 'Admidio';
     
    config.toolbar_Admidio =
    [
        ['Preview', '-', 'Font','FontSize','Bold', 'Italic', '-', 'JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock', '-', 
        'NumberedList', 'BulletedList', '-', 'Image', 'Link', 'Unlink', 'Table']
    ];
};
