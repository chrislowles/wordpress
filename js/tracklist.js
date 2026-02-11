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

		// 2. HELPER: Parse Duration (supports HH:MM:SS, MM:SS, and plain seconds)
		function parseToSeconds(duration) {
			if (!duration) return 0;
			duration = duration.toString().trim();
			
			if (duration.includes(':')) {
				var parts = duration.split(':').map(function(p) { return parseInt(p) || 0; });
				
				if (parts.length === 3) {
					// HH:MM:SS format
					return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
				} else if (parts.length === 2) {
					// MM:SS format
					return (parts[0] * 60) + parts[1];
				} else if (parts.length === 1) {
					// Just seconds
					return parts[0];
				}
			}
			
			// Plain number (seconds)
			return parseInt(duration) || 0;
		}

		function formatDuration(totalSeconds) {
			var hours = Math.floor(totalSeconds / 3600);
			var mins = Math.floor((totalSeconds % 3600) / 60);
			var secs = totalSeconds % 60;
			
			if (hours > 0) {
				// HH:MM:SS format
				return hours + ':' + 
					(mins < 10 ? '0' : '') + mins + ':' + 
					(secs < 10 ? '0' : '') + secs;
			} else {
				// MM:SS format
				return mins + ':' + (secs < 10 ? '0' : '') + secs;
			}
		}

		// 3. HELPER: Calculate Total
		function calculateTotalDuration() {
			var total = 0;
			$list.find('.tracklist-row:not(.is-spacer)').each(function() {
				var val = $(this).find('.item-duration-input').val();
				total += parseToSeconds(val);
			});
			$durationDisplay.text(formatDuration(total));
		}

		// 4. HELPER: Fetch Duration (API)
		$wrapper.on('click', '.fetch-duration', function() {
			var btn = $(this);
			var row = btn.closest('.tracklist-row');
			var url = row.find('.item-url-input').val();
			var durInput = row.find('.item-duration-input');
			var titleInput = row.find('.item-title-input');

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
				<div class="tracklist-row ${isSpacer ? 'is-spacer' : ''}">
					<span class="drag-handle" title="Drag">|||</span>
					<input type="hidden" name="tracklist[9999][type]" value="${type}" class="item-type" />
					<input type="text" name="tracklist[9999][title]" class="item-title-input" placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" />
					<input type="url" name="tracklist[9999][url]" class="item-url-input" placeholder="https://..." style="${isSpacer ? 'display:none' : ''}" />
					<input type="text" name="tracklist[9999][duration]" class="item-duration-input" placeholder="3:45" style="${isSpacer ? 'display:none' : ''}" />
					<label class="link-checkbox-label" style="${isSpacer ? '' : 'display:none'}" title="Link this spacer to a section in the body content">
						<input type="checkbox" name="tracklist[9999][link_to_section]" class="link-to-section-checkbox" value="1" />
						Link
					</label>
					<button type="button" class="fetch-duration button" style="${isSpacer ? 'display:none' : ''}">Fetch</button>
					<button type="button" class="add-to-show-btn button">Add to Show</button>
					<button type="button" class="remove-item button">Delete</button>
				</div>
			`;
			$list.append(html);
			refreshInputNames();
			// Focus the title input of the newly added row
			$list.children().last().find('.item-title-input').focus();
		}

		$wrapper.find('.add-track').click(function() { addRow('track'); });
		$wrapper.find('.add-spacer').click(function() { addRow('spacer'); });

		$wrapper.on('click', '.remove-item', function() {
			$(this).closest('.tracklist-row').remove();
			calculateTotalDuration();
			refreshInputNames();
		});

		// 6. INPUT HANDLING
		$wrapper.on('input change', 'input', function() {
			calculateTotalDuration();
		});

		// 7. HELPER: Re-index inputs
		function refreshInputNames() {
			$list.find('.tracklist-row').each(function(index) {
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
		// MODAL SYSTEM
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
					addSingleItemToShow();
				} else if (mode === 'all') {
					copyAllItemsToShow();
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

		function showAddItemModal($row) {
			if (!$modal) createModal();

			// Reset status
			$modal.find('#add-status').hide();

			// Update modal title
			$modal.find('#modal-title').text('Add Item to Show');
			$modal.find('#confirm-add-btn').text('Add Item').prop('disabled', false);

			// Store reference to the row
			$modal.data('row', $row);
			$modal.data('mode', 'single');

			// Load shows and display modal
			loadShowsList(function() {
				$modal.show();
			});
		}

		function showCopyAllModal() {
			if (!$modal) createModal();

			// Reset status
			$modal.find('#add-status').hide();

			// Update modal title
			$modal.find('#modal-title').text('Copy All Items to Show');
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

		function addSingleItemToShow() {
			var targetPostId = $modal.find('#target-show-select').val();
			if (!targetPostId) {
				showModalStatus('Please select a target show', 'error');
				return;
			}
			
			var $row = $modal.data('row');
			if (!$row || $row.length === 0) {
				showModalStatus('Error: Row not found', 'error');
				return;
			}
			
			// Use generic field names
			var item = {
				type: $row.find('.item-type').val() || 'track',
				title: $row.find('.item-title-input').val() || '',
				url: $row.find('.item-url-input').val() || '',
				duration: $row.find('.item-duration-input').val() || '',
				link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
			};
			
			if (!item.title) {
				showModalStatus('Item title is required, give me something to work with at least.', 'error');
				return;
			}
			
			var $btn = $modal.find('#confirm-add-btn');
			$btn.prop('disabled', true).text('Adding...');

			$.post(tracklistSettings.ajax_url, {
				action: 'add_single_item_to_show',
				nonce: tracklistSettings.nonce,
				target_post_id: targetPostId,
				item: item
			}).done(function(response) {
				if (response.success) {
					showModalStatus('Item added successfully!', 'success');
					setTimeout(function() {
						hideModal();
					}, 1100);
				} else {
					showModalStatus('Error: ' + (response.data.message || 'Unknown error'), 'error');
					$btn.prop('disabled', false).text('Add Item');
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				showModalStatus('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).text('Add Item');
				console.error('AJAX error adding item:', textStatus, errorThrown);
			});
		}

		function copyAllItemsToShow() {
			var targetPostId = $modal.find('#target-show-select').val();
			if (!targetPostId) {
				showModalStatus('Please select a target show', 'error');
				return;
			}

			var allItems = [];
			$list.find('.tracklist-row').each(function() {
				var $row = $(this);
				var title = $row.find('.item-title-input').val();

				// Only add items that have a title
				if (title) {
					// Use generic field names
					allItems.push({
						type: $row.find('.item-type').val() || 'track',
						title: title,
						url: $row.find('.item-url-input').val() || '',
						duration: $row.find('.item-duration-input').val() || '',
						link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
					});
				}
			});

			if (allItems.length === 0) {
				showModalStatus('No items to copy, what are you trying to do here, exactly?', 'error');
				return;
			}

			var $btn = $modal.find('#confirm-add-btn');
			$btn.prop('disabled', true).text('Copying...');

			$.post(tracklistSettings.ajax_url, {
				action: 'copy_items_to_show',
				nonce: tracklistSettings.nonce,
				target_post_id: targetPostId,
				items: allItems
			}).done(function(response) {
				if (response.success) {
					showModalStatus(`Successfully copied ${response.data.count} item(s)!`, 'success');
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
				console.error('AJAX error copying items:', textStatus, errorThrown);
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
		// BUTTON CLICK HANDLERS
		// =========================================================================

		// Individual "Add to Show" button
		$wrapper.on('click', '.add-to-show-btn', function(e) {
			e.preventDefault();
			var $row = $(this).closest('.tracklist-row');
			showAddItemModal($row);
		});

		// Bulk "Copy All to Show" button
		$wrapper.on('click', '.copy-all-to-show-btn', function(e) {
			e.preventDefault();
			showCopyAllModal(); // opens modal so user can select a target show first
		});

		// Initialize calculations
		calculateTotalDuration();
	}
});