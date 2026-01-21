jQuery(document).ready(function($) {
	var container = $('#tracklist-container');

	// 1. Enable Drag and Drop Sorting using WordPress built-in jQuery UI
	container.sortable({
		handle: '.drag-handle',
		placeholder: 'placeholder-highlight',
		axis: 'y',
		update: function() {
			calculateTotalDuration();
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

	// 6. Function to fetch duration from YouTube
	function fetchYouTubeDuration(button) {
		var row = button.closest('.track-row');
		var urlInput = row.find('.track-url-input');
		var durationInput = row.find('.track-duration-input');
		var url = urlInput.val();
		
		if (!url) {
			alert('Please enter a valid URL first (YouTube).');
			return;
		}
		
		var videoId = extractYouTubeID(url);
		if (!videoId) {
			alert('URL not valid for grabbing duration. Please check the URL and try again or grab duration manually.');
			return;
		}
		
		// Disable button and show loading state
		button.prop('disabled', true).text('Grabbing Duration');
		
		// Use YouTube oEmbed API (no API key required)
		$.ajax({
			url: 'https://www.youtube.com/oembed',
			data: {
				url: 'https://www.youtube.com/watch?v=' + videoId,
				format: 'json'
			},
			dataType: 'json',
			success: function(data) {
				// oEmbed doesn't provide duration, so we need to scrape it differently
				// Let's use the noembed.com service which provides more data
				$.ajax({
					url: 'https://noembed.com/embed',
					data: {
						url: 'https://www.youtube.com/watch?v=' + videoId
					},
					dataType: 'json',
					success: function(embedData) {
						if (embedData.duration) {
							durationInput.val(formatDuration(embedData.duration));
							calculateTotalDuration();
							button.prop('disabled', false).text('Grab Duration');
						} else {
							alert('Sorry, couldn\'t grab duration. Please get the duration manually.');
							button.prop('disabled', false).text('Grab Duration');
						}
					},
					error: function() {
						alert('Sorry, couldn\'t grab duration. Please get the duration manually.');
						button.prop('disabled', false).text('Grab Duration');
					}
				});
			},
			error: function() {
				alert('Sorry, couldn\'t grab duration. Please get the duration manually.');
				button.prop('disabled', false).text('Grab Duration');
			}
		});
	}

	// 7. Function to add a new row
	function addRow(type) {
		var index = container.find('.track-row').length;
		
		// Define placeholders based on type
		var titlePlaceholder = (type === 'spacer') ? '[In The Cinema/The Pin Drop/Walking On Thin Ice/One Up]' : 'Artist/Group - Track Title';

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
	}

	// 8. Button Click Events
	$('.add-track').on('click', function() { addRow('track'); });
	$('.add-spacer').on('click', function() { addRow('spacer'); });

	// 9. Remove Button Event (Delegated for dynamically added items)
	container.on('click', '.remove-track', function() {
		$(this).closest('.track-row').remove();
		calculateTotalDuration();
	});

	// 10. Fetch Duration Button Event (Delegated for dynamically added items)
	container.on('click', '.fetch-duration', function() {
		fetchYouTubeDuration($(this));
	});

	// 11. Update total when duration changes
	container.on('input', '.track-duration-input', function() {
		calculateTotalDuration();
	});

	// 12. Calculate initial total on page load
	calculateTotalDuration();
});