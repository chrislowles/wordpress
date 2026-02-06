jQuery(document).ready(function($) {

	// Initialize tracklist editor
	var $wrapper = $('.tracklist-wrapper');
	if ($wrapper.length === 0) return;

	initTracklist($wrapper);

	function initTracklist($wrapper) {
		var postId = $wrapper.data('post-id');
		var $list = $wrapper.find('.tracklist-items');
		var $durationDisplay = $wrapper.find('.total-duration-display');

		// 1. Initialize Sortable
		$list.sortable({
			handle: '.drag-handle',
			placeholder: 'placeholder-highlight',
			axis: 'y',
			update: function(event, ui) {
				calculateTotalDuration();
				refreshInputNames();
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

		// 4. HELPER: Fetch Duration (API)
		$wrapper.on('click', '.fetch-duration', function() {
			var btn = $(this);
			var row = btn.closest('.track-row');
			var url = row.find('.track-url-input').val();
			var durInput = row.find('.track-duration-input');
			var titleInput = row.find('.track-title-input');

			if (!url) return alert('Enter URL first');
			
			btn.prop('disabled', true).text('...');

			$.ajax({
				url: 'https://noembed.com/embed',
				data: { url: url },
				dataType: 'json',
				success: function(data) {
					if (data.duration) {
						durInput.val(formatDuration(data.duration));
						calculateTotalDuration();
					}
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

		// 5. HELPER: Add Row
		function addRow(type) {
			var isSpacer = (type === 'spacer');
			var html = `
				<div class="track-row ${isSpacer ? 'is-spacer' : ''}">
					<span class="drag-handle" title="Drag">|||</span>
					<input type="hidden" name="tracklist[9999][type]" value="${type}" class="track-type" />
					<input type="text" name="tracklist[9999][track_title]" class="track-title-input" placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" />
					<input type="url" name="tracklist[9999][track_url]" class="track-url-input" placeholder="https://..." style="${isSpacer ? 'display:none' : ''}" />
					<input type="text" name="tracklist[9999][duration]" class="track-duration-input" placeholder="3:45" style="width:60px; ${isSpacer ? 'display:none' : ''}" />
					<label class="link-checkbox-label" style="${isSpacer ? '' : 'display:none'}" title="Link this spacer to a section in the body content">
						<input type="checkbox" name="tracklist[9999][link_to_section]" class="link-to-section-checkbox" value="1" />
						Link
					</label>
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Fetch</button>
					<button type="button" class="add-to-show-btn button">Add to Show</button>
					<button type="button" class="remove-track button">Delete</button>
				</div>
			`;
			$list.append(html);
			refreshInputNames();
			// Focus the title input of the newly added row
			$list.children().last().find('.track-title-input').focus();
		}

		$wrapper.find('.add-track').click(function() { addRow('track'); });
		$wrapper.find('.add-spacer').click(function() { addRow('spacer'); });

		$wrapper.on('click', '.remove-track', function() {
			$(this).closest('.track-row').remove();
			calculateTotalDuration();
			refreshInputNames();
		});

		// 6. INPUT HANDLING
		$wrapper.on('input change', 'input', function() {
			calculateTotalDuration();
		});

		// 7. HELPER: Re-index inputs
		function refreshInputNames() {
			$list.find('.track-row').each(function(index) {
				var row = $(this);
				row.find('input, select, textarea').each(function() {
					var name = $(this).attr('name');
					if (name) {
						var newName = name.replace(/\[\d+\]/, '[' + index + ']');
						$(this).attr('name', newName);
					}
				});
			});
		}

		// =========================================================================
		// MODAL SYSTEM - MOVED TO TOP LEVEL SCOPE
		// =========================================================================

		var $modal = null;
		var showsList = null;

		function createModal() {
			var modalHtml = `
				<div id="add-to-show-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
					<div style="background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; max-height: 70vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
							<h2 id="modal-title" style="margin: 0;">Add to Show</h2>
							<button type="button" class="modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
						</div>

						<div style="margin-bottom: 20px;">
							<label style="display: block; margin-bottom: 5px; font-weight: 600;">Select Show:</label>
							<select id="target-show-select" class="widefat" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<option value="">Loading shows...</option>
							</select>
						</div>

						<div style="display: flex; gap: 10px; justify-content: flex-end;">
							<a class="button modal-close">Cancel</a>
							<a class="button modal-close button-primary" id="confirm-add-btn">Add</a>
							<a class="button modal-close button-primary" href="/wp-admin/post-new.php?post_type=show" target="_blank">Create New Show</a>
						</div>

						<div id="add-status" style="margin-top: 15px; padding: 10px; border-radius: 4px; display: none;"></div>
					</div>
				</div>
			`;

			$modal = $(modalHtml);
			$('body').append($modal);

			// Close modal handlers
			$modal.on('click', '.modal-close', function(e) {
				e.preventDefault();
				hideModal();
			});

			// Close on background click
			$modal.on('click', function(e) {
				if (e.target.id === 'add-to-show-modal') {
					hideModal();
				}
			});

			// Handle confirm button click
			$modal.on('click', '#confirm-add-btn', function(e) {
				e.preventDefault();
				var mode = $modal.data('mode');
				if (mode === 'single') {
					addSingleTrackToShow();
				} else if (mode === 'all') {
					copyAllTracksToShow();
				}
			});
		}

		function hideModal() {
			if ($modal) {
				$modal.hide();
				$modal.find('#add-status').hide();
				$modal.removeData('row');
				$modal.removeData('mode');
			}
		}

		function showAddToShowModal($row) {
			if (!$modal) createModal();

			// Reset status
			$modal.find('#add-status').hide();

			// Update modal title
			$modal.find('#modal-title').text('Add Track to Show');
			$modal.find('#confirm-add-btn').text('Add Track').prop('disabled', false);

			// Store reference to the row
			$modal.data('row', $row);
			$modal.data('mode', 'single');

			// Load shows and display modal
			loadShowsList(function() {
				$modal.show();
			});
		}

		function showCopyAllToShowModal() {
			if (!$modal) createModal();

			// Reset status
			$modal.find('#add-status').hide();

			// Update modal title
			$modal.find('#modal-title').text('Copy All Tracks to Show');
			$modal.find('#confirm-add-btn').text('Copy All').prop('disabled', false);

			// Clear row reference
			$modal.removeData('row');
			$modal.data('mode', 'all');

			// Load shows and display modal
			loadShowsList(function() {
				$modal.show();
			});
		}

		function loadShowsList(callback) {
			var $select = $modal.find('#target-show-select');
			$select.html('<option value="">Loading...</option>');

			// Return cached list if available
			if (showsList !== null) {
				populateShowsDropdown(showsList);
				if (callback) callback();
				return;
			}
			
			$.post(tracklistSettings.ajax_url, {
				action: 'get_show_posts',
				nonce: tracklistSettings.nonce,
				current_post_id: postId
			}).done(function(response) {
				if (response.success) {
					showsList = response.data;
					populateShowsDropdown(showsList);
					if (callback) callback();
				} else {
					$select.html('<option value="">Error loading shows</option>');
					console.error('Failed to load shows:', response);
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				$select.html('<option value="">Error loading shows</option>');
				console.error('AJAX error loading shows:', textStatus, errorThrown);
			});
		}

		function populateShowsDropdown(shows) {
			var $select = $modal.find('#target-show-select');
			var options = '<option value="">Select a show...</option>';
			
			$.each(shows, function(i, show) {
				var statusBadge = show.status === 'draft' ? ' (Draft)' : '';
				options += `<option value="${show.id}">${escapeHtml(show.title)} - ${show.date}${statusBadge}</option>`;
			});
			
			$select.html(options);
		}

		function addSingleTrackToShow() {
			var targetPostId = $modal.find('#target-show-select').val();
			if (!targetPostId) {
				showModalStatus('Please select a target show', 'error');
				return;
			}
			
			var $row = $modal.data('row');
			if (!$row || $row.length === 0) {
				showModalStatus('Error: Track row not found', 'error');
				return;
			}
			
			var track = {
				type: $row.find('.track-type').val() || 'track',
				track_title: $row.find('.track-title-input').val() || '',
				track_url: $row.find('.track-url-input').val() || '',
				duration: $row.find('.track-duration-input').val() || '',
				link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
			};
			
			if (!track.track_title) {
				showModalStatus('Track title is required, give me something to work with at least.', 'error');
				return;
			}
			
			var $btn = $modal.find('#confirm-add-btn');
			$btn.prop('disabled', true).text('Adding...');

			$.post(tracklistSettings.ajax_url, {
				action: 'add_single_track_to_show',
				nonce: tracklistSettings.nonce,
				target_post_id: targetPostId,
				track: track
			}).done(function(response) {
				if (response.success) {
					showModalStatus('Track added successfully!', 'success');
					setTimeout(function() {
						hideModal();
					}, 1100);
				} else {
					showModalStatus('Error: ' + (response.data.message || 'Unknown error'), 'error');
					$btn.prop('disabled', false).text('Add Track');
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				showModalStatus('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).text('Add Track');
				console.error('AJAX error adding track:', textStatus, errorThrown);
			});
		}

		function copyAllTracksToShow() {
			var targetPostId = $modal.find('#target-show-select').val();
			if (!targetPostId) {
				showModalStatus('Please select a target show', 'error');
				return;
			}

			var allTracks = [];
			$list.find('.track-row').each(function() {
				var $row = $(this);
				var title = $row.find('.track-title-input').val();

				// Only add tracks that have a title
				if (title) {
					allTracks.push({
						type: $row.find('.track-type').val() || 'track',
						track_title: title,
						track_url: $row.find('.track-url-input').val() || '',
						duration: $row.find('.track-duration-input').val() || '',
						link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
					});
				}
			});

			if (allTracks.length === 0) {
				showModalStatus('No tracks to copy, what are you trying to do here, exactly?', 'error');
				return;
			}

			var $btn = $modal.find('#confirm-add-btn');
			$btn.prop('disabled', true).text('Copying...');

			$.post(tracklistSettings.ajax_url, {
				action: 'copy_tracks_to_show',
				nonce: tracklistSettings.nonce,
				target_post_id: targetPostId,
				tracks: allTracks
			}).done(function(response) {
				if (response.success) {
					showModalStatus(`Successfully copied ${response.data.count} track(s)!`, 'success');
					setTimeout(function() {
						hideModal();
					}, 1100);
				} else {
					showModalStatus('Error: ' + (response.data.message || 'Unknown error'), 'error');
					$btn.prop('disabled', false).text('Copy All');
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				showModalStatus('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).text('Copy All');
				console.error('AJAX error copying tracks:', textStatus, errorThrown);
			});
		}

		function showModalStatus(message, type) {
			var $status = $modal.find('#add-status');
			var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
			var textColor = type === 'success' ? '#155724' : '#721c24';
			var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
			
			$status.css({
				'background-color': bgColor,
				'color': textColor,
				'border': '1px solid ' + borderColor
			}).html(message).show();
			
			if (type === 'success') {
				setTimeout(function() {
					$status.fadeOut();
				}, 1100);
			}
		}

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

		// =========================================================================
		// BUTTON CLICK HANDLERS - ATTACHED WITH DELEGATION
		// =========================================================================

		// Individual "Add to Show" button
		$wrapper.on('click', '.add-to-show-btn', function(e) {
			e.preventDefault();
			var $row = $(this).closest('.track-row');
			showAddToShowModal($row);
		});

		// Bulk "Copy All to Show" button
		$wrapper.on('click', '.copy-all-to-show-btn', function(e) {
			e.preventDefault();
			showCopyAllToShowModal();
		});

		// Initialize calculations
		calculateTotalDuration();
	}
});