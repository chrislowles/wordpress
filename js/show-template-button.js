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
	var $titleWrap = $('#titlewrap');
	if ($titleWrap.length) {
		$titleWrap.append($templateButton);
	}

	// Handle button click
	$templateButton.on('click', function(e) {
		e.preventDefault();

		var $titleField = $('#title');
		var editor = getEditor();
		var currentBody = getEditorContent(editor);

		// Check if there is existing content to warn the user
		var hasContent = $titleField.val().trim() !== '' || currentBody.trim() !== '';
		
		if (hasContent) {
			if (!confirm('This will replace the current title and content. Continue?')) {
				return;
			}
		}
		
		// Set the title
		$titleField.val(showTemplate.title).trigger('input');
		
		// Set the body content
		setEditorContent(editor, showTemplate.body);
		
		// Visual feedback
		$templateButton.text('Template Loaded').prop('disabled', true);
		setTimeout(function() {
			$templateButton.text('Load Template').prop('disabled', false);
		}, 2000);
	});

	/**
	 * Robustly find the Editor Instance
	 * Checks globals, DOM properties, and CodeMirror classes
	 */
	function getEditor() {
		// 1. Try Global EasyMDE/SimpleMDE (Standard implementations)
		if (typeof window.easyMDE !== 'undefined') return window.easyMDE;
		if (typeof window.simpleMDE !== 'undefined') return window.simpleMDE;

		// 2. Try User's Hint: .mmd-running (Markup Markdown Plugin specific)
		// Plugins often attach the instance to the textarea element
		var mmdTextarea = document.querySelector('.mmd-running');
		if (mmdTextarea) {
			if (mmdTextarea.EasyMDE) return mmdTextarea.EasyMDE;
			if (mmdTextarea.codemirror) return mmdTextarea.codemirror;
		}

		// 3. specific check for Markup Markdown's global instance pattern
		// Sometimes plugins use a specific global variable like 'mmd_editor'
		if (typeof window.mmd_editor !== 'undefined') return window.mmd_editor;

		// 4. Fallback: Find the CodeMirror instance directly in the DOM
		// EasyMDE wraps the editor in a div with class .CodeMirror
		var cmElement = document.querySelector('.CodeMirror');
		if (cmElement && cmElement.CodeMirror) {
			return cmElement.CodeMirror;
		}
		
		// 5. Last Resort: Return the standard WordPress textarea
		return $('#content');
	}

	/**
	 * Get content from the found editor
	 */
	function getEditorContent(editor) {
		if (!editor) return '';

		// EasyMDE / SimpleMDE instance
		if (typeof editor.value === 'function') {
			return editor.value();
		}

		// CodeMirror instance
		if (typeof editor.getValue === 'function') {
			return editor.getValue();
		}

		// jQuery Object or DOM Element (textarea)
		if (editor instanceof jQuery) {
			return editor.val();
		}
		if (editor.value !== undefined) {
			return editor.value;
		}

		return '';
	}

	/**
	 * Set content to the found editor
	 */
	function setEditorContent(editor, content) {
		if (!editor) return;

		// EasyMDE / SimpleMDE instance
		if (typeof editor.value === 'function') {
			editor.value(content);
			return;
		}

		// CodeMirror instance (Markup Markdown likely exposes this via the DOM)
		if (typeof editor.setValue === 'function') {
			editor.setValue(content);
			// Refresh is sometimes needed if the editor was hidden
			if (typeof editor.refresh === 'function') {
				editor.refresh();
			}
			return;
		}

		// Fallback: Textarea
		// We trigger 'change' and 'input' to ensure any listeners (like autosave) pick it up
		if (editor instanceof jQuery) {
			editor.val(content).trigger('change').trigger('input');
		} else if (editor.value !== undefined) {
			editor.value = content;
		}
	}
});