jQuery(document).ready(function($) {
	var container = $('#tracklist-container');

	// 1. Enable Drag and Drop Sorting using WordPress built-in jQuery UI
	container.sortable({
		handle: '.drag-handle',
		placeholder: 'placeholder-highlight',
		axis: 'y',
		update: function() {
			calculateTotalDuration();
			updateYouTubePlaylistLink();
		}
	});

	// 2. Function to parse duration string (3:45 or 45) into seconds
	function parseToSeconds(duration) {
		if (!duration) return 0;
		duration = duration.trim();
		
		// Handle formats like "3:45" or "45"
		if (duration.includes(':')) {
			var parts = duration.split(':');
			var mins = parseInt(parts[0]) || 0;
			var secs = parseInt(parts[1]) || 0;
			return (mins * 60) + secs;
		} else {
			// Just seconds
			return parseInt(duration) || 0;
		}
	}

	// 3. Function to format seconds back to MM:SS
	function formatDuration(totalSeconds) {
		var mins = Math.floor(totalSeconds / 60);
		var secs = totalSeconds % 60;
		return mins + ':' + (secs < 10 ? '0' : '') + secs;
	}

	// 4. Function to calculate and display total duration
	function calculateTotalDuration() {
		var total = 0;
		
		container.find('.track-row:not(.is-spacer)').each(function() {
			var duration = $(this).find('.track-duration-input').val();
			total += parseToSeconds(duration);
		});
		
		$('#total-duration').text(formatDuration(total));
	}

	// 5. Function to extract YouTube video ID from URL
	function extractYouTubeID(url) {
		if (!url) return null;
		
		// Handle various YouTube URL formats
		var regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
		var match = url.match(regExp);
		return (match && match[7].length == 11) ? match[7] : null;
	}

	// 6. NEW: Function to check if URL is a YouTube URL
	function isYouTubeURL(url) {
		if (!url) return false;
		return url.includes('youtube.com') || url.includes('youtu.be');
	}

	// 7. NEW: Function to update YouTube playlist link
	function updateYouTubePlaylistLink() {
		var videoIds = [];
		var allYouTube = true;
		var hasAnyTracks = false;

		// Collect all video IDs from track rows (not spacers)
		container.find('.track-row:not(.is-spacer)').each(function() {
			var urlInput = $(this).find('.track-url-input');
			var url = urlInput.val();
			
			if (url) {
				hasAnyTracks = true;
				if (isYouTubeURL(url)) {
					var videoId = extractYouTubeID(url);
					if (videoId) {
						videoIds.push(videoId);
					}
				} else {
					allYouTube = false;
				}
			}
		});

		// Show or hide the YouTube playlist link
		if (allYouTube && videoIds.length > 0 && hasAnyTracks) {
			var playlistUrl = `https://www.youtube.com/watch_videos?video_ids=${videoIds.join(',')}`;
			
			// Create or update the link
			if ($('#youtube-playlist-link').length === 0) {
				$('#total-duration').parent().after(
					`<div style="font-size: 13px; margin-top: 8px;">
						<a id="youtube-playlist-link" href="${playlistUrl}" title="The condition of all the tracks being from YouTube was met so heres a temp playlist." target="_blank" rel="noopener noreferrer" class="button button-secondary" style="text-decoration: none;">Play All</a>
					</div>`
				);
			} else {
				$('#youtube-playlist-link').attr('href', playlistUrl);
			}
		} else {
			$('#youtube-playlist-link').parent().remove();
		}
	}

	// 8. Function to fetch duration from any supported platform
	function fetchDuration(button) {
		var row = button.closest('.track-row');
		var urlInput = row.find('.track-url-input');
		var durationInput = row.find('.track-duration-input');
		var url = urlInput.val();

		if (!url) {
			alert('Please enter a valid URL first.');
			return;
		}

		// Disable button and show loading state
		button.prop('disabled', true).text('Grabbing Duration');

		// Use noembed.com which supports multiple platforms (YouTube, Vimeo, SoundCloud, Dailymotion, etc.)
		$.ajax({
			url: 'https://noembed.com/embed',
			data: { url: url },
			dataType: 'json',
			success: function(data) {
				if (data.duration) {
					durationInput.val(formatDuration(data.duration));
					calculateTotalDuration();
					button.prop('disabled', false).text('Grab Duration');
				} else {
					alert('Duration not available for this URL. Please enter it manually.');
					button.prop('disabled', false).text('Grab Duration');
				}
			},
			error: function() {
				alert('Couldn\'t fetch duration. Please enter it manually.');
				button.prop('disabled', false).text('Grab Duration');
			}
		});
	}

	// 9. Function to add a new row
	function addRow(type) {
		var index = container.find('.track-row').length;

		// Define placeholders based on type
		var titlePlaceholder = (type === 'spacer') ? 'Segment (In The Cinema/The Pin Drop/Walking On Thin Ice/One Up P1-2)' : 'Artist/Group - Track Title';

		// Hide URL and duration inputs if it is a spacer
		var hiddenStyle = (type === 'spacer') ? 'display:none;' : '';
		var rowClass = (type === 'spacer') ? 'track-row is-spacer' : 'track-row';

		var html = `
			<div class="${rowClass}">
				<span class="drag-handle" title="Drag to reorder">|||</span>
				<input type="hidden" name="tracklist[${index}][type]" value="${type}" />
				<input type="text" name="tracklist[${index}][track_title]" placeholder="${titlePlaceholder}" class="track-title-input" />
				<input type="url" name="tracklist[${index}][track_url]" placeholder="https://..." class="track-url-input" style="${hiddenStyle}" />
				<input type="text" name="tracklist[${index}][duration]" placeholder="3:45" class="track-duration-input" style="width: 60px; ${hiddenStyle}" />
				<button type="button" class="fetch-duration button" style="${hiddenStyle}">Grab Duration</button>
				<button type="button" class="remove-track button">Remove</button>
			</div>
		`;
		container.append(html);
		calculateTotalDuration();
		updateYouTubePlaylistLink();
	}

	// 10. Button Click Events
	$('.add-track').on('click', function() { addRow('track'); });
	$('.add-spacer').on('click', function() { addRow('spacer'); });

	// 11. Remove Button Event (Delegated for dynamically added items)
	container.on('click', '.remove-track', function() {
		$(this).closest('.track-row').remove();
		calculateTotalDuration();
		updateYouTubePlaylistLink();
	});

	// 12. Fetch Duration Button Event (Delegated for dynamically added items)
	container.on('click', '.fetch-duration', function() {
		fetchDuration($(this));
	});

	// 13. Update total when duration changes
	container.on('input', '.track-duration-input', function() {
		calculateTotalDuration();
	});

	// 14. NEW: Update YouTube link when URL changes
	container.on('input', '.track-url-input', function() {
		updateYouTubePlaylistLink();
	});

	// 15. Calculate initial total and YouTube link on page load
	calculateTotalDuration();
	updateYouTubePlaylistLink();
});