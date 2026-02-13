jQuery(document).ready(function($) {
	
	var formSubmitting = false;
	var confirmShown = false;
	
	/**
	 * Helper function to get editor content
	 * Works with various markdown editors
	 */
	function getEditorContent() {
		// Try EasyMDE/SimpleMDE first
		if (typeof window.easyMDE !== 'undefined' && window.easyMDE.value) {
			return window.easyMDE.value();
		}
		if (typeof window.simpleMDE !== 'undefined' && window.simpleMDE.value) {
			return window.simpleMDE.value();
		}
		
		// Try textarea with .mmd-running class (Markup Markdown)
		var $mmdTextarea = $('.mmd-running');
		if ($mmdTextarea.length) {
			var elem = $mmdTextarea[0];
			if (elem.EasyMDE) return elem.EasyMDE.value();
			if (elem.codemirror) return elem.codemirror.getValue();
		}
		
		// Try CodeMirror instance
		var cmElement = document.querySelector('.CodeMirror');
		if (cmElement && cmElement.CodeMirror) {
			return cmElement.CodeMirror.getValue();
		}
		
		// Fallback to #content textarea
		return $('#content').val();
	}
	
	/**
	 * Check if content has bare URLs (not in markdown links)
	 * Pattern matches URLs not inside [text](url) format
	 */
	function hasBareUrls(content) {
		if (!content) return false;
		
		// Match URLs not preceded by ](
		var pattern = /(?<!\]\()\b(https?:\/\/[^\s\)\]<>"']+)/gi;
		var matches = content.match(pattern);
		
		return matches && matches.length > 0;
	}
	
	/**
	 * Count bare URLs in content
	 */
	function countBareUrls(content) {
		if (!content) return 0;
		
		var pattern = /(?<!\]\()\b(https?:\/\/[^\s\)\]<>"']+)/gi;
		var matches = content.match(pattern);
		
		return matches ? matches.length : 0;
	}
	
	/**
	 * Intercept form submission to show confirmation dialog
	 */
	$('#post').on('submit', function(e) {
		// If we've already confirmed or are submitting, let it through
		if (formSubmitting) {
			return true;
		}
		
		// Get current editor content
		var content = getEditorContent();
		
		// Check if there are bare URLs
		if (hasBareUrls(content)) {
			// Prevent the default submission
			e.preventDefault();
			
			var urlCount = countBareUrls(content);
			var message = 'Found ' + urlCount + ' bare URL' + (urlCount !== 1 ? 's' : '') + ' in the content.\n\n' +
				'Do you want to fetch page titles for these URLs?\n\n' +
				'• Yes = Fetch titles and convert to [Title](URL) format\n' +
				'• No = Save without fetching titles';
			
			// Show confirmation dialog
			if (confirm(message)) {
				// User wants to fetch titles
				// Add a hidden field to indicate this
				if ($('input[name="fetch_link_titles"]').length === 0) {
					$('#post').append('<input type="hidden" name="fetch_link_titles" value="1">');
				} else {
					$('input[name="fetch_link_titles"]').val('1');
				}
			} else {
				// User doesn't want to fetch titles
				// Make sure the field is not present or set to 0
				if ($('input[name="fetch_link_titles"]').length === 0) {
					$('#post').append('<input type="hidden" name="fetch_link_titles" value="0">');
				} else {
					$('input[name="fetch_link_titles"]').val('0');
				}
			}
			
			// Now submit the form
			formSubmitting = true;
			$('#post').submit();
			
			return false;
		} else {
			// No bare URLs, proceed normally
			return true;
		}
	});
	
	/**
	 * Also intercept the publish/update button clicks
	 * WordPress has multiple ways to trigger saves
	 */
	$('#publish, #save-post').on('click', function(e) {
		if (formSubmitting) {
			return true;
		}
		
		// The form submit handler will catch it
		return true;
	});
});