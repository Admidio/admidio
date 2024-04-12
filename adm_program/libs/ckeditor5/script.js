//import ClassicEditor from "@ckeditor/ckeditor5-editor-classic/src/classiceditor";
ClassicEditor
	.create( document.querySelector( '.editor' ), {
		// Editor configuration.
        /*plugins: [
            Essentials,
            Paragraph
        ],*/

        // Add the toolbar configuration.
        toolbar: {
            items: [
                'undo',
                'redo'
            ]
        }
	} )
	.then( editor => {
		window.editor = editor;
	} )
	.catch( handleSampleError );

function handleSampleError( error ) {
	const issueUrl = 'https://github.com/ckeditor/ckeditor5/issues';

	const message = [
		'Oops, something went wrong!',
		`Please, report the following error on ${ issueUrl } with the build id "ra405ikksenm-cwxph3v5k2lh" and the error stack trace:`
	].join( '\n' );

	console.error( message );
	console.error( error );
}
