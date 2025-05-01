(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 */

	$(document).ready(function() {
		// Temperature range input value display
		$('input[type="range"]').on('input', function() {
			$(this).next('output').val($(this).val());
		});
		
		// Test chat functionality
		if ($('.ai-chat-bedrock-test-chat').length) {
			// The test page uses the same chat interface as the public side,
			// so we don't need to duplicate the chat functionality here.
			// The public JS will handle the chat interface.
		}
	});

})( jQuery );
