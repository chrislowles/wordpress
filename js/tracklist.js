jQuery(document).ready(function($) {
	
	// Iterate over every tracklist wrapper (could be Local or Global)
	$('.tracklist-wrapper').each(function() {
		initTracklist($(this));
	});

	function initTracklist($wrapper) {
		var scope = $wrapper.data('scope'); // 'post' or 'global'
		var $list = $wrapper.find('.tracklist-items');
		var $durationDisplay = $wrapper.find('.total-duration-display');
		var $youtubeContainer = $wrapper.find('.youtube-playlist-container');
		
		// Locking Elements (Global only)
		var $overlay = $wrapper.find('.tracklist-lock-overlay');
		var $ownerLabel = $wrapper.find('.lock-owner-name');
		var isEditing = false;
		var idleTimer;

		// 1. Initialize Sortable
		// We use connectWith so you can drag from Global -> Local
		$list.sortable({
			handle: '.drag-handle',
			placeholder: 'placeholder-highlight',
			connectWith: '.tracklist-items', 
			axis: 'y',
			update: function(event, ui) {
				// Only calculate if the item was dropped here
				calculateTotalDuration();
				updateYouTubePlaylistLink();
				triggerEdit();
				
				// If we dragged an item from Global to Local, we need to rename input fields
				// so the Local form saves them correctly.
				var item = ui.item;
				if (item.parent().is($list)) {
					refreshInputNames($list, scope);
				}
			}
		});

		// 2. HELPER: Parse Duration
		function parseToSeconds(duration) {
			if (!duration) return 0;
			duration = duration.toString().trim();
			if (duration.includes(':')) {
				var parts = duration.split(':');
				var mins = parseInt(parts[0]) || 0;
				var secs = parseInt(parts[1]) || 0;
				return (mins * 60) + secs;
			}
			return parseInt(duration) || 0;
		}

		function formatDuration(totalSeconds) {
			var mins = Math.floor(totalSeconds / 60);
			var secs = totalSeconds % 60;
			return mins + ':' + (secs < 10 ? '0' : '') + secs;
		}

		// 3. HELPER: Calculate Total
		function calculateTotalDuration() {
			var total = 0;
			$list.find('.track-row:not(.is-spacer)').each(function() {
				var val = $(this).find('.track-duration-input').val();
				total += parseToSeconds(val);
			});
			$durationDisplay.text(formatDuration(total));
		}

		// 4. HELPER: Update YouTube Link
		function updateYouTubePlaylistLink() {
			var videoIds = [];
			var allYouTube = true;
			var hasTracks = false;

			$list.find('.track-row:not(.is-spacer)').each(function() {
				var url = $(this).find('.track-url-input').val();
				if (url) {
					hasTracks = true;
					if (url.includes('youtube.com') || url.includes('youtu.be')) {
						// Simple regex extract
						var match = url.match(/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/);
						var vid = (match && match[7].length == 11) ? match[7] : null;
						if (vid) videoIds.push(vid);
					} else {
						allYouTube = false;
					}
				}
			});

			if (allYouTube && videoIds.length > 0 && hasTracks) {
				var playlistUrl = `https://www.youtube.com/watch_videos?video_ids=${videoIds.join(',')}`;
				var linkHtml = `<a href="${playlistUrl}" target="_blank" class="button">Play All (YT)</a>`;
				$youtubeContainer.html(linkHtml);
			} else {
				$youtubeContainer.empty();
			}
		}

		// 5. HELPER: Fetch Duration (API)
		$wrapper.on('click', '.fetch-duration', function() {
			var btn = $(this);
			var row = btn.closest('.track-row');
			var url = row.find('.track-url-input').val();
			var durInput = row.find('.track-duration-input');
			var titleInput = row.find('.track-title-input'); // Select the title input

			if (!url) return alert('Enter URL first');
			
			btn.prop('disabled', true).text('...');
			triggerEdit();

			$.ajax({
				url: 'https://noembed.com/embed',
				data: { url: url },
				dataType: 'json',
				success: function(data) {
					// Handle Duration
					if (data.duration) {
						durInput.val(formatDuration(data.duration));
						calculateTotalDuration();
					}

					// Handle Title
					if (data.title) {
						titleInput.val(data.title); // Prefill the title
					}

					btn.prop('disabled', false).text('Grab');
				},
				error: function() {
					btn.prop('disabled', false).text('Err');
				}
			});
		});

		// 6. HELPER: Add Row
		function addRow(type) {
			var isSpacer = (type === 'spacer');
			// We use a dummy index '9999', refreshInputNames will fix it
			var namePrefix = (scope === 'global') ? 'global_tracklist' : 'tracklist';
			
			var html = `
				<div class="track-row ${isSpacer ? 'is-spacer' : ''}">
					<span class="drag-handle" title="Drag">|||</span>
					<input type="hidden" name="${namePrefix}[9999][type]" value="${type}" class="track-type" />
					<input type="text" name="${namePrefix}[9999][track_title]" class="track-title-input" 
						   placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" />
					<input type="url" name="${namePrefix}[9999][track_url]" class="track-url-input" 
						   placeholder="https://..." style="${isSpacer ? 'display:none' : ''}" />
					<input type="text" name="${namePrefix}[9999][duration]" class="track-duration-input" 
						   placeholder="3:45" style="width:60px; ${isSpacer ? 'display:none' : ''}" />
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Grab</button>
					<button type="button" class="remove-track button">X</button>
				</div>
			`;
			$list.append(html);
			refreshInputNames($list, scope); // Crucial for indexing
			triggerEdit();
		}

		$wrapper.find('.add-track').click(function() { addRow('track'); });
		$wrapper.find('.add-spacer').click(function() { addRow('spacer'); });
		
		$wrapper.on('click', '.remove-track', function() {
			$(this).closest('.track-row').remove();
			calculateTotalDuration();
			updateYouTubePlaylistLink();
			refreshInputNames($list, scope);
			triggerEdit();
		});

		// 7. INPUT HANDLING & LOCKING (Global Only)
		$wrapper.on('input', 'input', function() {
			calculateTotalDuration();
			updateYouTubePlaylistLink();
			triggerEdit();
		});

		function triggerEdit() {
			if (scope === 'global') {
				isEditing = true;
				clearTimeout(idleTimer);
				idleTimer = setTimeout(function() { isEditing = false; }, 30000); // 30s idle
			}
		}

		// 8. HELPER: Re-index inputs (Crucial when dragging between lists)
		function refreshInputNames($container, currentScope) {
			var prefix = (currentScope === 'global') ? 'global_tracklist' : 'tracklist';
			
			$container.find('.track-row').each(function(index) {
				var row = $(this);
				row.find('input, select, textarea').each(function() {
					var name = $(this).attr('name');
					if (name) {
						// Regex to replace [number] and the prefix
						// matches "something[123][field]"
						var newName = name.replace(/\[\d+\]/, '[' + index + ']');
						// Swap prefix if we dragged from Global to Local
						if (currentScope === 'post' && newName.indexOf('global_tracklist') !== -1) {
							newName = newName.replace('global_tracklist', 'tracklist');
						}
						if (currentScope === 'global' && newName.indexOf('tracklist') === 0) {
							newName = newName.replace('tracklist', 'global_tracklist');
						}
						$(this).attr('name', newName);
					}
				});
			});
		}

		// 9. GLOBAL SAVE & HEARTBEAT
		if (scope === 'global') {
			
			// AJAX Save
			$wrapper.find('.global-save-btn').click(function() {
				var btn = $(this);
				var spinner = $wrapper.find('.global-spinner');
				var data = [];

				btn.prop('disabled', true);
				spinner.addClass('is-active');

				// Build data array
				$list.find('.track-row').each(function() {
					var row = $(this);
					data.push({
						type: row.find('.track-type').val(),
						track_title: row.find('.track-title-input').val(),
						track_url: row.find('.track-url-input').val(),
						duration: row.find('.track-duration-input').val()
					});
				});

				$.post(tracklistSettings.ajax_url, {
					action: 'save_global_tracklist',
					nonce: tracklistSettings.nonce,
					data: data
				}).done(function(res) {
					if(res.success) {
						btn.text('Saved!');
						setTimeout(function(){ btn.text('Save Global List').prop('disabled', false); }, 2000);
					} else {
						alert(res.data.message);
						btn.prop('disabled', false);
					}
				}).always(function() {
					spinner.removeClass('is-active');
				});
			});

			// Heartbeat Logic
			$(document).on('heartbeat-send', function(e, data) {
				data.global_tl_check = true;
				data.global_tl_editing = isEditing;
			});

			$(document).on('heartbeat-tick', function(e, data) {
				if (!data.global_tl_status) return;

				if (data.global_tl_status === 'locked') {
					// Lock UI
					$list.sortable('disable');
					$wrapper.find('input, button').prop('disabled', true);
					$overlay.removeClass('hidden');
					$ownerLabel.text(data.global_tl_owner);
					
					// Sync content (optional - simple reload of list)
					// Implementing full DOM sync is complex, simply locking prevents overwrite.
				} else {
					// Unlock UI
					$list.sortable('enable');
					$wrapper.find('input, button').not('.global-save-btn[disabled]').prop('disabled', false);
					$overlay.addClass('hidden');
				}
			});
			
			// Initial check
			if (wp && wp.heartbeat) wp.heartbeat.connectNow();
		}

		// Initialize calculations
		calculateTotalDuration();
		updateYouTubePlaylistLink();
	}
});