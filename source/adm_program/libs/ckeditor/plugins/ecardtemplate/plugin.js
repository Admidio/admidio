CKEDITOR.plugins.add( 'ecardtemplate',
{   
    requires : ['richcombo'], //, 'styles' ],
    init : function( editor )
    {
        var config = editor.config,
		lang = editor.lang.format;
		 
        editor.ui.addRichCombo( 'EcardTemplate',
		{
			label : ecardJS.template_Text,
			title : ecardJS.template_Text,
			voiceLabel : ecardJS.template_Text,
			className : 'cke_format',
			multiSelect : false,

			panel :
			{
			   css : [ config.contentsCss, CKEDITOR.getUrl( editor.skinPath + 'editor.css' ) ],
			   voiceLabel : lang.panelVoiceLabel
			},

			init : function()
			{
				this.startGroup( ecardJS.template_Text );
				//this.add('value', 'drop_text', 'drop_label');
				for (var this_tag in ecardJS.templates){
					this.add(ecardJS.templates[this_tag][0], ecardJS.templates[this_tag][1], ecardJS.templates[this_tag][2]);
				}
			},

			onClick : function( value )
			{
				editor.fire( 'saveSnapshot' );
				$("#" + ecardJS.ecardformid + " input[name='ecard[template_name]']").val(value);
			}
		 });
   }
});