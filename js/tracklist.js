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

	// 5. Function to add a new row
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
				<button type="button" class="remove-track button">Remove</button>
			</div>
		`;
		container.append(html);
		calculateTotalDuration();
	}

	// 6. Button Click Events
	$('.add-track').on('click', function() { addRow('track'); });
	$('.add-spacer').on('click', function() { addRow('spacer'); });

	// 7. Remove Button Event (Delegated for dynamically added items)
	container.on('click', '.remove-track', function() {
		$(this).closest('.track-row').remove();
		calculateTotalDuration();
	});

	// 8. Update total when duration changes
	container.on('input', '.track-duration-input', function() {
		calculateTotalDuration();
	});

	// 9. Calculate initial total on page load
	calculateTotalDuration();
});