jQuery(document).ready(function($) {
	
	// Iterate over every tracklist wrapper (could be Local or Global)
	$('.tracklist-wrapper').each(function() {
		initTracklist($(this));
	});

	function initTracklist($wrapper) {
		var scope = $wrapper.data('scope'); // 'post' or 'global'
		// Check the data attribute from PHP to see if we should render transfer buttons
		var allowTransfer = $wrapper.data('allow-transfer') == 1;

		var $list = $wrapper.find('.tracklist-items');
		var $durationDisplay = $wrapper.find('.total-duration-display');
		var $youtubeContainer = $wrapper.find('.youtube-playlist-container');
		
		// Locking Elements (Global only)
		var $overlay = $wrapper.find('.tracklist-lock-overlay');
		var $ownerLabel = $wrapper.find('.lock-owner-name');
		var isEditing = false;
		var idleTimer;

		// 1. Initialize Sortable (within-list only, no cross-list dragging)
		$list.sortable({
			handle: '.drag-handle',
			placeholder: 'placeholder-highlight',
			axis: 'y',
			update: function(event, ui) {
				calculateTotalDuration();
				updateYouTubePlaylistLink();
				triggerEdit();
				refreshInputNames($list, scope);
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
			var titleInput = row.find('.track-title-input');

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
						titleInput.val(data.title);
					}

					btn.prop('disabled', false).text('Fetch');
				},
				error: function() {
					btn.prop('disabled', false).text('Err');
				}
			});
		});

		// 6. HELPER: Transfer Track Between Lists
		$wrapper.on('click', '.transfer-track', function() {
			var btn = $(this);
			var row = btn.closest('.track-row');
			var targetScope = btn.data('target-scope'); // 'post' or 'global'
			
			// Find the target list
			// Map 'post' scope to 'local' class selector to ensure Global -> Local transfer finds the wrapper
			var cssScope = (targetScope === 'post') ? 'local' : targetScope;
			var $targetWrapper = $('.tracklist-wrapper.is-' + cssScope);

			if ($targetWrapper.length === 0) {
				alert('Target tracklist not found');
				return;
			}
			
			var $targetList = $targetWrapper.find('.tracklist-items');
			
			// Get the track data
			var trackData = {
				type: row.find('.track-type').val(),
				track_title: row.find('.track-title-input').val(),
				track_url: row.find('.track-url-input').val(),
				duration: row.find('.track-duration-input').val(),
				link_to_section: row.find('.link-to-section-checkbox').is(':checked')
			};
			
			// Create new row in target list
			addRowToList($targetList, targetScope, trackData);
			
			// Update both lists
			calculateTotalDuration();
			updateYouTubePlaylistLink();
			$targetWrapper.find('.total-duration-display').text(
				formatDuration(calculateDurationForList($targetList))
			);
			
			// Trigger edit on target if it's global
			if (targetScope === 'global') {
				var $targetWrapperInstance = $targetWrapper;
				// Need to trigger edit on the target wrapper's context
				// We'll just set a flag that heartbeat will pick up
				isEditing = true;
				clearTimeout(idleTimer);
				idleTimer = setTimeout(() => isEditing = false, 30000);
			}
			
			// Visual feedback
			btn.text('Success!').prop('disabled', true);
			setTimeout(() => btn.text(scope === 'global' ? 'To Local' : 'To Global').prop('disabled', false), 1000);
		});

		// 7. HELPER: Copy All Local Tracks to Global
		$wrapper.on('click', '.copy-all-to-global', function() {
			var btn = $(this);
			
			if (!confirm('Copy all local tracks to global tracklist?')) {
				return;
			}
			
			// Find the global list
			var $globalWrapper = $('.tracklist-wrapper.is-global');
			if ($globalWrapper.length === 0) {
				alert('Global tracklist not found');
				return;
			}
			
			var $globalList = $globalWrapper.find('.tracklist-items');
			
			// Get all local tracks
			var tracksAdded = 0;
			$list.find('.track-row').each(function() {
				var row = $(this);
				var trackData = {
					type: row.find('.track-type').val(),
					track_title: row.find('.track-title-input').val(),
					track_url: row.find('.track-url-input').val(),
					duration: row.find('.track-duration-input').val(),
					link_to_section: row.find('.link-to-section-checkbox').is(':checked')
				};
				
				// Add tracks and spacers (even if empty title/url)
				if (trackData.track_title || trackData.track_url || trackData.type === 'spacer') {
					addRowToList($globalList, 'global', trackData);
					tracksAdded++;
				}
			});
			
			// Update global list display
			$globalWrapper.find('.total-duration-display').text(
				formatDuration(calculateDurationForList($globalList))
			);
			
			// Visual feedback
			btn.text(`${tracksAdded} copied`).prop('disabled', true);
			setTimeout(() => btn.text('All to Global').prop('disabled', false), 2000);
			
			// Trigger edit on global
			isEditing = true;
			clearTimeout(idleTimer);
			idleTimer = setTimeout(() => isEditing = false, 30000);
		});

		// 8. HELPER: Copy All Global Tracks to Local
		$wrapper.on('click', '.copy-all-to-local', function() {
			var btn = $(this);
			
			if (!confirm('Copy all global tracks to local tracklist?')) {
				return;
			}
			
			// Find the local list
			var $localWrapper = $('.tracklist-wrapper.is-local');
			if ($localWrapper.length === 0) {
				alert('Local tracklist not found');
				return;
			}
			
			var $localList = $localWrapper.find('.tracklist-items');
			
			// Get all global tracks
			var tracksAdded = 0;
			$list.find('.track-row').each(function() {
				var row = $(this);
				var trackData = {
					type: row.find('.track-type').val(),
					track_title: row.find('.track-title-input').val(),
					track_url: row.find('.track-url-input').val(),
					duration: row.find('.track-duration-input').val(),
					link_to_section: row.find('.link-to-section-checkbox').is(':checked')
				};
				
				// Add tracks and spacers (even if empty title/url)
				if (trackData.track_title || trackData.track_url || trackData.type === 'spacer') {
					addRowToList($localList, 'post', trackData);
					tracksAdded++;
				}
			});
			
			// Update local list display
			$localWrapper.find('.total-duration-display').text(
				formatDuration(calculateDurationForList($localList))
			);
			
			// Visual feedback
			btn.text(`${tracksAdded} copied`).prop('disabled', true);
			setTimeout(() => btn.text('All to Local').prop('disabled', false), 2000);
		});

		// 9. HELPER: Calculate duration for a specific list
		function calculateDurationForList($targetList) {
			var total = 0;
			$targetList.find('.track-row:not(.is-spacer)').each(function() {
				var val = $(this).find('.track-duration-input').val();
				total += parseToSeconds(val);
			});
			return total;
		}

		// 10. HELPER: Add Row to a specific list
		function addRowToList($targetList, targetScope, trackData) {
			var isSpacer = (trackData.type === 'spacer');
			var namePrefix = (targetScope === 'global') ? 'global_tracklist' : 'tracklist';
			
			// Check if target is locked (global only)
			var $targetWrapper = $targetList.closest('.tracklist-wrapper');
			if (targetScope === 'global') {
				var $targetOverlay = $targetWrapper.find('.tracklist-lock-overlay');
				if (!$targetOverlay.hasClass('hidden')) {
					alert('Global tracklist is locked by another user');
					return;
				}
			}
			
			// Check if target wrapper allows transfers (to avoid rendering broken buttons on Dashboard)
			var targetAllowsTransfer = $targetWrapper.data('allow-transfer') == 1;

			var html = `
				<div class="track-row ${isSpacer ? 'is-spacer' : ''}">
					<span class="drag-handle" title="Drag">|||</span>
					<input type="hidden" name="${namePrefix}[9999][type]" value="${trackData.type}" class="track-type" />
					<input type="text" name="${namePrefix}[9999][track_title]" class="track-title-input" 
						   placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" 
						   value="${escapeHtml(trackData.track_title)}" />
					<input type="url" name="${namePrefix}[9999][track_url]" class="track-url-input" 
						   placeholder="https://..." 
						   value="${escapeHtml(trackData.track_url)}"
						   style="${isSpacer ? 'display:none' : ''}" />
					<input type="text" name="${namePrefix}[9999][duration]" class="track-duration-input" 
						   placeholder="3:45" 
						   value="${escapeHtml(trackData.duration)}"
						   style="width:60px; ${isSpacer ? 'display:none' : ''}" />
					<label class="link-checkbox-label" style="${isSpacer ? '' : 'display:none'}" title="Link this spacer to a section in the body content">
						<input type="checkbox" name="${namePrefix}[9999][link_to_section]" class="link-to-section-checkbox" value="1" ${trackData.link_to_section ? 'checked' : ''} />
						Link
					</label>
					${targetAllowsTransfer ? `
					<button type="button" class="transfer-track button" 
							title="${targetScope === 'global' ? 'Copy to Local Tracklist' : 'Copy to Global Tracklist'}"
							data-target-scope="${targetScope === 'global' ? 'post' : 'global'}"
							style="">
						${targetScope === 'global' ? 'To Local' : 'To Global'}
					</button>
					` : ''}
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Fetch</button>
					<button type="button" class="remove-track button">Delete</button>
				</div>
			`;
			$targetList.append(html);
			refreshInputNames($targetList, targetScope);
		}

		// 11. HELPER: Escape HTML for safety
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

		// 12. HELPER: Add Row (original function)
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
					<label class="link-checkbox-label" style="${isSpacer ? '' : 'display:none'}" title="Link this spacer to a section in the body content">
						<input type="checkbox" name="${namePrefix}[9999][link_to_section]" class="link-to-section-checkbox" value="1" />
						Link
					</label>
					${allowTransfer ? `
					<button type="button" class="transfer-track button" 
							title="${scope === 'global' ? 'Copy to Local Tracklist' : 'Copy to Global Tracklist'}"
							data-target-scope="${scope === 'global' ? 'post' : 'global'}"
							style="">
						${scope === 'global' ? 'To Local' : 'To Global'}
					</button>
					` : ''}
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Fetch</button>
					<button type="button" class="remove-track button">Delete</button>
				</div>
			`;
			$list.append(html);
			refreshInputNames($list, scope);
			triggerEdit();
			
			// Focus the title input of the newly added row
			$list.children().last().find('.track-title-input').focus();
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

		// 13. INPUT HANDLING & LOCKING (Global Only)
		$wrapper.on('input change', 'input', function() {
			calculateTotalDuration();
			updateYouTubePlaylistLink();
			triggerEdit();
		});

		function triggerEdit() {
			if (scope === 'global') {
				isEditing = true;
				clearTimeout(idleTimer);
				idleTimer = setTimeout(() => isEditing = false, 30000); // 30s idle
			}
		}

		// 14. HELPER: Re-index inputs (simplified - no cross-list handling needed)
		function refreshInputNames($container, currentScope) {
			var prefix = (currentScope === 'global') ? 'global_tracklist' : 'tracklist';
			
			$container.find('.track-row').each(function(index) {
				var row = $(this);
				row.find('input, select, textarea').each(function() {
					var name = $(this).attr('name');
					if (name) {
						// Replace [number] with the current index
						var newName = name.replace(/\[\d+\]/, '[' + index + ']');
						$(this).attr('name', newName);
					}
				});
			});
		}

		// 15. GLOBAL SAVE & HEARTBEAT
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
						duration: row.find('.track-duration-input').val(),
						link_to_section: row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
					});
				});

				$.post(tracklistSettings.ajax_url, {
					action: 'save_global_tracklist',
					nonce: tracklistSettings.nonce,
					data: data
				}).done(function(res) {
					if(res.success) {
						btn.text('Saved!');
						setTimeout(() => btn.text('Save').prop('disabled', false), 2000);
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