jQuery(document).ready(function($) {
	
	// Initialize tracklist editor
	var $wrapper = $('.tracklist-wrapper');
	if ($wrapper.length === 0) return;
	
	initTracklist($wrapper);

	function initTracklist($wrapper) {
		var postId = $wrapper.data('post-id');
		var $list = $wrapper.find('.tracklist-items');
		var $durationDisplay = $wrapper.find('.total-duration-display');
		var $youtubeContainer = $wrapper.find('.youtube-playlist-container');

		// 1. Initialize Sortable
		$list.sortable({
			handle: '.drag-handle',
			placeholder: 'placeholder-highlight',
			axis: 'y',
			update: function(event, ui) {
				calculateTotalDuration();
				updateYouTubePlaylistLink();
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

		// 6. HELPER: Add Row
		function addRow(type) {
			var isSpacer = (type === 'spacer');
			
			var html = `
				<div class="track-row ${isSpacer ? 'is-spacer' : ''}">
					<span class="drag-handle" title="Drag">|||</span>
					<input type="hidden" name="tracklist[9999][type]" value="${type}" class="track-type" />
					<input type="text" name="tracklist[9999][track_title]" class="track-title-input" 
						   placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" />
					<input type="url" name="tracklist[9999][track_url]" class="track-url-input" 
						   placeholder="https://..." style="${isSpacer ? 'display:none' : ''}" />
					<input type="text" name="tracklist[9999][duration]" class="track-duration-input" 
						   placeholder="3:45" style="width:60px; ${isSpacer ? 'display:none' : ''}" />
					<label class="link-checkbox-label" style="${isSpacer ? '' : 'display:none'}" title="Link this spacer to a section in the body content">
						<input type="checkbox" name="tracklist[9999][link_to_section]" class="link-to-section-checkbox" value="1" />
						Link
					</label>
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Fetch</button>
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
			updateYouTubePlaylistLink();
			refreshInputNames();
		});

		// 7. INPUT HANDLING
		$wrapper.on('input change', 'input', function() {
			calculateTotalDuration();
			updateYouTubePlaylistLink();
		});

		// 8. HELPER: Re-index inputs
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

		// 9. COPY TO SHOW FUNCTIONALITY
		var $modal = null;
		
		$wrapper.on('click', '.copy-to-show-btn', function(e) {
			e.preventDefault();
			showCopyToShowModal();
		});

		function showCopyToShowModal() {
			// Create modal if it doesn't exist
			if (!$modal) {
				createModal();
			}
			
			// Load show posts
			loadShowPosts();
			
			// Show modal
			$modal.show();
		}

		function createModal() {
			var modalHtml = `
				<div id="copy-to-show-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
					<div style="background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 700px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
							<h2 style="margin: 0;">Copy Tracks to Show</h2>
							<button type="button" class="modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
						</div>
						
						<div style="margin-bottom: 20px;">
							<label style="display: block; margin-bottom: 10px; font-weight: 600;">
								<input type="checkbox" id="select-all-tracks" style="margin-right: 5px;">
								Select All Tracks
							</label>
							<div id="track-selection" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;">
								<p style="color: #666; font-style: italic;">Loading tracks...</p>
							</div>
						</div>
						
						<div style="margin-bottom: 20px;">
							<label style="display: block; margin-bottom: 5px; font-weight: 600;">Target Show:</label>
							<select id="target-show-select" class="widefat" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<option value="">Loading shows...</option>
							</select>
						</div>
						
						<div style="display: flex; gap: 10px; justify-content: flex-end;">
							<button type="button" class="button modal-close">Cancel</button>
							<button type="button" id="copy-tracks-btn" class="button button-primary">Copy Selected Tracks</button>
						</div>
						
						<div id="copy-status" style="margin-top: 15px; padding: 10px; border-radius: 4px; display: none;"></div>
					</div>
				</div>
			`;
			
			$modal = $(modalHtml);
			$('body').append($modal);
			
			// Close modal handlers
			$modal.on('click', '.modal-close', function() {
				$modal.hide();
			});
			
			$modal.on('click', function(e) {
				if (e.target.id === 'copy-to-show-modal') {
					$modal.hide();
				}
			});
			
			// Select all functionality
			$modal.on('change', '#select-all-tracks', function() {
				$modal.find('#track-selection input[type="checkbox"]').prop('checked', $(this).is(':checked'));
			});
			
			// Copy button handler
			$modal.on('click', '#copy-tracks-btn', function() {
				copySelectedTracks();
			});
		}

		function loadShowPosts() {
			var $select = $modal.find('#target-show-select');
			$select.html('<option value="">Loading...</option>');
			
			$.post(tracklistSettings.ajax_url, {
				action: 'get_show_posts',
				nonce: tracklistSettings.nonce,
				current_post_id: postId
			}).done(function(response) {
				if (response.success) {
					var options = '<option value="">-- Select a show --</option>';
					$.each(response.data, function(i, show) {
						var statusBadge = show.status === 'draft' ? ' (Draft)' : '';
						options += `<option value="${show.id}">${show.title} - ${show.date}${statusBadge}</option>`;
					});
					$select.html(options);
					
					// Load current tracks for selection
					loadCurrentTracks();
				} else {
					$select.html('<option value="">Error loading shows</option>');
				}
			}).fail(function() {
				$select.html('<option value="">Error loading shows</option>');
			});
		}

		function loadCurrentTracks() {
			var $trackSelection = $modal.find('#track-selection');
			var html = '';
			var trackIndex = 0;
			
			$list.find('.track-row').each(function() {
				var $row = $(this);
				var type = $row.find('.track-type').val();
				var title = $row.find('.track-title-input').val();
				var url = $row.find('.track-url-input').val();
				var duration = $row.find('.track-duration-input').val();
				
				if (!title && type !== 'spacer') return; // Skip empty tracks
				
				var displayText = title || '(Untitled)';
				if (duration) displayText += ' [' + duration + ']';
				if (type === 'spacer') displayText = '📌 ' + displayText;
				
				html += `
					<label style="display: block; padding: 5px; cursor: pointer; border-bottom: 1px solid #eee;">
						<input type="checkbox" class="track-checkbox" data-index="${trackIndex}" style="margin-right: 8px;">
						<span>${escapeHtml(displayText)}</span>
					</label>
				`;
				trackIndex++;
			});
			
			if (html === '') {
				html = '<p style="color: #666; font-style: italic;">No tracks to copy</p>';
			}
			
			$trackSelection.html(html);
		}

		function copySelectedTracks() {
			var targetPostId = $modal.find('#target-show-select').val();
			if (!targetPostId) {
				showStatus('Please select a target show', 'error');
				return;
			}
			
			var selectedTracks = [];
			$modal.find('#track-selection input[type="checkbox"]:checked').each(function() {
				var index = $(this).data('index');
				var $row = $list.find('.track-row').eq(index);
				
				selectedTracks.push({
					type: $row.find('.track-type').val(),
					track_title: $row.find('.track-title-input').val(),
					track_url: $row.find('.track-url-input').val(),
					duration: $row.find('.track-duration-input').val(),
					link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
				});
			});
			
			if (selectedTracks.length === 0) {
				showStatus('Please select at least one track', 'error');
				return;
			}
			
			var $btn = $modal.find('#copy-tracks-btn');
			$btn.prop('disabled', true).text('Copying...');
			
			$.post(tracklistSettings.ajax_url, {
				action: 'copy_tracks_to_show',
				nonce: tracklistSettings.nonce,
				target_post_id: targetPostId,
				tracks: selectedTracks
			}).done(function(response) {
				if (response.success) {
					showStatus(`Successfully copied ${response.data.count} track(s)!`, 'success');
					setTimeout(function() {
						$modal.hide();
					}, 2000);
				} else {
					showStatus('Error: ' + response.data.message, 'error');
				}
			}).fail(function() {
				showStatus('Request failed. Please try again.', 'error');
			}).always(function() {
				$btn.prop('disabled', false).text('Copy Selected Tracks');
			});
		}

		function showStatus(message, type) {
			var $status = $modal.find('#copy-status');
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
				}, 3000);
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

		// Initialize calculations
		calculateTotalDuration();
		updateYouTubePlaylistLink();
	}
});