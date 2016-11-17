/**
 * @license Copyright (c) 2003-2016, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For complete reference see:
	// http://docs.ckeditor.com/#!/api/CKEDITOR.config

	// The toolbar groups arrangement, optimized for two toolbar rows.
	config.toolbarGroups = [
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
		{ name: 'links' },
		{ name: 'insert' },
		{ name: 'forms' },
		{ name: 'tools' },
		{ name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
		{ name: 'others' },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'styles' },
		{ name: 'colors' },
		{ name: 'about' }
	];

	config.toolbar_AdmidioDefault = [
		['Format', 'FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', '-',
			'NumberedList', 'BulletedList', '-','Image', 'Link', 'Unlink', 'Table', '-', 'Maximize']
	];

	config.toolbar_AdmidioGuestbook = [
		['Font','FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft','JustifyCenter','JustifyRight', '-',
			'Smiley', 'Link', 'Unlink', '-', 'NumberedList', 'BulletedList', '-', 'Maximize']
	];

	config.toolbar_AdmidioPlugin_WC = [
		['Format', 'FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', '-',
			'NumberedList', 'BulletedList', '-','-', '-', '-', 'Table', '-', 'Maximize']
	];

	// Remove some buttons provided by the standard plugins, which are
	// not needed in the Standard(s) toolbar.
	config.removeButtons = 'Underline,Subscript,Superscript';

	// Set the most common block elements.
	config.format_tags = 'p;h1;h2;h3;pre';

	// Simplify the dialog windows.
	config.removeDialogTabs = 'image:advanced;link:advanced';
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
