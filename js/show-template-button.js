jQuery(document).ready(function($) {
	
	// Only proceed if we have the template data
	if (typeof showTemplate === 'undefined') {
		return;
	}

	// Create the "Load Template" button
	var $templateButton = $('<button>', {
		type: 'button',
		class: 'button button-secondary',
		id: 'load-show-template',
		text: 'Load Template',
		css: {
			marginLeft: '10px'
		}
	});

	// Insert the button next to the title field
	// WordPress places the title in #titlewrap
	var $titleWrap = $('#titlewrap');
	
	if ($titleWrap.length) {
		$titleWrap.append($templateButton);
	}

	// Handle button click
	$templateButton.on('click', function(e) {
		e.preventDefault();
		
		// Confirm before overwriting (if there's existing content)
		var $titleField = $('#title');
		var $editor = getEditor();
		
		var hasContent = $titleField.val().trim() !== '' || getEditorContent().trim() !== '';
		
		if (hasContent) {
			if (!confirm('This will replace the current title and content. Continue?')) {
				return;
			}
		}
		
		// Set the title
		$titleField.val(showTemplate.title).trigger('input');
		
		// Set the body content
		setEditorContent(showTemplate.body);
		
		// Visual feedback
		$templateButton.text('Template Loaded').prop('disabled', true);
		setTimeout(function() {
			$templateButton.text('Load Template').prop('disabled', false);
		}, 2000);
	});

	/**
	 * Get the editor instance (handles both Classic Editor and EasyMDE/Markup Markdown)
	 */
	function getEditor() {
		// Check for EasyMDE (Markup Markdown plugin)
		if (typeof window.easyMDE !== 'undefined') {
			return window.easyMDE;
		}
		
		// Check for global EasyMDE instances
		if (typeof window.simpleMDE !== 'undefined') {
			return window.simpleMDE;
		}
		
		// Fallback to textarea
		return $('#content');
	}

	/**
	 * Get current editor content
	 */
	function getEditorContent() {
		var editor = getEditor();
		
		// EasyMDE instance
		if (editor && typeof editor.value === 'function') {
			return editor.value();
		}
		
		// jQuery object (textarea)
		if (editor && editor.val) {
			return editor.val();
		}
		
		return '';
	}

	/**
	 * Set editor content
	 */
	function setEditorContent(content) {
		// finally found the markup markdown (easymde) editor (i think, idk i'm tired lmao) (unfinished, to be used in refactor)
		//new EasyMDE({
			//element: document.querySelector('.mmd-running')
		//}).value(content);

		var editor = getEditor();
		
		// EasyMDE instance
		if (editor && typeof editor.value === 'function') {
			editor.value(content);
			return;
		}
		
		// jQuery object (textarea)
		if (editor && editor.val) {
			editor.val(content).trigger('input');
			return;
		}
		
		// Last resort: try to find the textarea
		$('#content').val(content).trigger('input');
	}
});