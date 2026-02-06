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
		text: 'Load Template'
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
			if (!confirm('This will replace the current title, content, and tracklist. Continue?')) {
				return;
			}
		}
		
		// Set the title
		$titleField.val(showTemplate.title).trigger('input');
		
		// Set the body content
		setEditorContent(editor, showTemplate.body);
		
		// Add spacer rows to tracklist
		addTemplateSpacers();
		
		// Visual feedback
		$templateButton.text('Template Loaded').prop('disabled', true);
		setTimeout(function() {
			$templateButton.text('Load Template').prop('disabled', false);
		}, 2000);
	});

	/**
	 * Add pre-header linked Spacer rows to the tracklist
	 */
	function addTemplateSpacers() {
		var $tracklistWrapper = $('.tracklist-wrapper');
		if ($tracklistWrapper.length === 0) {
			console.warn('Tracklist wrapper not found');
			return;
		}

		var $tracklistItems = $tracklistWrapper.find('.tracklist-items');
		
		// Clear existing tracklist if user confirmed
		$tracklistItems.empty();
		
		// Add spacers for each section
		if (typeof showTemplate.spacers !== 'undefined' && showTemplate.spacers.length > 0) {
			$.each(showTemplate.spacers, function(index, spacerTitle) {
				addSpacerRow($tracklistItems, spacerTitle, index);
			});
			
			// Trigger refresh of input names and calculations
			// These functions are defined in tracklist.js
			if (typeof window.refreshTracklistInputNames === 'function') {
				window.refreshTracklistInputNames();
			}
			if (typeof window.calculateTracklistDuration === 'function') {
				window.calculateTracklistDuration();
			}
		}
	}

	/**
	 * Add a single spacer row to the tracklist
	 */
	function addSpacerRow($container, title, index) {
		var html = `
			<div class="track-row is-spacer">
				<span class="drag-handle" title="Drag">|||</span>
				<input type="hidden" name="tracklist[${index}][type]" value="spacer" class="track-type" />
				<input type="text" name="tracklist[${index}][track_title]" class="track-title-input" placeholder="Segment Title..." value="${escapeHtml(title)}" />
				<input type="url" name="tracklist[${index}][track_url]" class="track-url-input" placeholder="https://..." style="display:none" />
				<input type="text" name="tracklist[${index}][duration]" class="track-duration-input" placeholder="3:45" style="width:60px; display:none" />
				<label class="link-checkbox-label" title="Link this spacer to a section in the body content">
					<input type="checkbox" name="tracklist[${index}][link_to_section]" class="link-to-section-checkbox" value="1" checked />
					Link
				</label>
				<button type="button" class="fetch-duration button" style="display:none">Fetch</button>
				<button type="button" class="add-to-show-btn button">Add to Show</button>
				<button type="button" class="remove-track button">Delete</button>
			</div>
		`;
		$container.append(html);
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		if (!text) return '';
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

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