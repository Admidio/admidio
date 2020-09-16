/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For complete reference see:
	// https://ckeditor.com/docs/ckeditor4/latest/api/CKEDITOR_config.html

    config.extraPlugins = 'font, button, panelbutton, floatpanel, colorbutton, justify, smiley'

	// The toolbar groups arrangement, optimized for two toolbar rows.
    config.toolbar_AdmidioDefault =
    [
        { name: 'styles', items: [ 'Format', 'FontSize' ] },
		{ name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'TextColor' ] },
		{ name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
		{ name: 'insert', items: [ 'Image', 'Link', 'Unlink', 'Table'] },
		{ name: 'tools', items: [ 'Maximize' ] }
	];

    config.toolbar_AdmidioGuestbook =
    [
        { name: 'styles', items: [ 'Font', 'FontSize' ] },
		{ name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'TextColor' ] },
		{ name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
		{ name: 'insert', items: [ 'Smiley', 'Link', 'Unlink'] },
		{ name: 'tools', items: [ 'Maximize' ] }
    ];

    config.toolbar_AdmidioPlugin_WC =
    [
        { name: 'styles', items: [ 'Format', 'FontSize' ] },
		{ name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'TextColor' ] },
		{ name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
		{ name: 'insert', items: [ 'Table'] },
		{ name: 'tools', items: [ 'Maximize' ] }
    ];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript';

	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';
};
