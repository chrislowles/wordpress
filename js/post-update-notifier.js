jQuery(document).ready(function($) {
	// Only run if we have the necessary data
	if (!postUpdateNotifier || !postUpdateNotifier.postId) {
		return;
	}

	var initialModified = postUpdateNotifier.lastModified;
	var postId = postUpdateNotifier.postId;
	var hasPrompted = false;
	var isEditScreen = postUpdateNotifier.isEditScreen;

	// Hook into heartbeat send
	$(document).on('heartbeat-send', function(e, data) {
		// Send check request with post ID and last known modified time
		data.post_update_check = {
			post_id: postId,
			last_modified: initialModified
		};
	});

	// Hook into heartbeat receive
	$(document).on('heartbeat-tick', function(e, data) {
		if (!data.post_updated) {
			return;
		}

		// Post has been updated!
		if (!hasPrompted) {
			hasPrompted = true;
			
			var message = isEditScreen 
				? 'This post has been updated in another window or by another user. Would you like to refresh to see the changes?\n\nNote: Any unsaved changes will be lost.'
				: 'This post has been updated. Would you like to refresh the page to see the changes?';
			
			if (confirm(message)) {
				location.reload();
			}
		}
	});

	// Force a heartbeat check on load
	if (typeof wp !== 'undefined' && wp.heartbeat) {
		wp.heartbeat.connectNow();
	}
});