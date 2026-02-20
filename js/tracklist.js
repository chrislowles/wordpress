jQuery(document).ready($ => {

    var $wrapper = $('.tracklist-wrapper');
    if ($wrapper.length === 0) return;

    // Shared utilities provided by utils.js
    var parseToSeconds  = ThemeUtils.parseToSeconds;
    var formatDuration  = ThemeUtils.formatDuration;
    var escapeHtml      = ThemeUtils.escapeHtml;

    initTracklist($wrapper);

    function initTracklist($wrapper) {
        var postId          = $wrapper.data('post-id');
        var $list           = $wrapper.find('.tracklist-items');
        var $durationDisplay = $wrapper.find('.total-duration-display');

        // =====================================================================
        // 1. SORTABLE
        // =====================================================================

        $list.sortable({
            handle: '.drag-handle',
            placeholder: 'placeholder-highlight',
            axis: 'y',
            update: () => {
                calculateTotalDuration();
                refreshInputNames();
            }
        });

        // =====================================================================
        // 2. DURATION HELPERS
        // =====================================================================

        function calculateTotalDuration() {
            var total = 0;
            $list.find('.tracklist-row:not(.is-spacer)').each(function() {
                total += parseToSeconds($(this).find('.item-duration-input').val());
            });
            $durationDisplay.text(formatDuration(total));
        }

        function refreshInputNames() {
            $list.find('.tracklist-row').each(function(index) {
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                });
            });
        }

        // Expose to show-template-button.js so it can trigger a re-index after
        // prepending spacer rows without duplicating this logic.
        window.TracklistRefresh = {
            inputs:   refreshInputNames,
            duration: calculateTotalDuration
        };

        // =====================================================================
        // 3. ADD / REMOVE ROWS
        // CSS in dashboard.css handles visibility of track-only and spacer-only
        // elements via the .is-spacer class; no inline styles needed here.
        // =====================================================================

        function addRow(type) {
            var isSpacer = (type === 'spacer');
            var html = `
                <div class="tracklist-row ${isSpacer ? 'is-spacer' : ''}">
                    <span class="drag-handle" title="Drag">|||</span>
                    <input type="hidden" name="tracklist[9999][type]" value="${type}" class="item-type" />
                    <input type="text" name="tracklist[9999][title]" class="item-title-input" placeholder="${isSpacer ? 'Segment Title...' : 'Artist - Track'}" />
                    <input type="url" name="tracklist[9999][url]" class="item-url-input" placeholder="https://..." />
                    <input type="text" name="tracklist[9999][duration]" class="item-duration-input" placeholder="3:45" />
                    <label class="link-checkbox-label" title="Link this spacer to a section in the body content">
                        <input type="checkbox" name="tracklist[9999][link_to_section]" class="link-to-section-checkbox" value="1" />
                        Link
                    </label>
                    <button type="button" class="add-to-show-btn button">Add to Show</button>
                    <button type="button" class="remove-item button">Delete</button>
                </div>
            `;
            $list.append(html);
            refreshInputNames();
            $list.children().last().find('.item-title-input').focus();
        }

        $wrapper.find('.add-track').on('click', () => { addRow('track'); });
        $wrapper.find('.add-spacer').on('click', () => { addRow('spacer'); });

        $wrapper.on('click', '.remove-item', function() {
            $(this).closest('.tracklist-row').remove();
            calculateTotalDuration();
            refreshInputNames();
        });

        $wrapper.on('input change', 'input', () => {
            calculateTotalDuration();
        });

        // =====================================================================
        // 4. MODAL
        // =====================================================================

        var $modal              = null;
        var showsList           = null;
        var selectedShowId      = null;
        var selectedShowTitle   = null;

        // ---------------------------------------------------------------------
        // Modal HTML builder — separated from wiring so createModal() is readable
        // ---------------------------------------------------------------------

        function buildModalHtml() {
            return `
                <div id="add-to-show-modal" style="display:none; position:fixed; z-index:100000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div style="background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:80%; max-width:600px; max-height:70vh; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);">

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #ddd; padding-bottom:10px;">
                            <h2 id="modal-title" style="margin:0;">Add to Show</h2>
                            <button type="button" class="modal-close" style="background:none; border:none; font-size:24px; cursor:pointer; color:#666;">&times;</button>
                        </div>

                        <div style="margin-bottom:20px; position:relative;">
                            <label style="display:block; margin-bottom:5px; font-weight:600;">Search Shows:</label>
                            <input type="text" id="show-search-input" class="widefat" placeholder="Type to search..." autocomplete="off"
                                style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" />
                            <ul id="show-search-results"
                                style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #ddd; border-top:none; border-radius:0 0 4px 4px; margin:0; padding:0; list-style:none; max-height:220px; overflow-y:auto; z-index:10; box-shadow:0 4px 6px rgba(0,0,0,0.08);">
                            </ul>
                        </div>

                        <div id="show-selected-display"
                            style="display:none; margin-bottom:16px; padding:8px 12px; background:#f0f6fc; border:1px solid #72aee6; border-radius:4px; font-size:13px; align-items:center; justify-content:space-between;">
                            <span id="show-selected-label" style="font-weight:600; color:#2271b1;"></span>
                            <button type="button" id="show-selected-clear" style="background:none; border:none; cursor:pointer; color:#999; font-size:16px; padding:0 0 0 8px;" title="Clear selection">&times;</button>
                        </div>

                        <div style="display:flex; gap:10px; justify-content:flex-end;">
                            <a class="button modal-close">Cancel</a>
                            <a class="button button-primary" id="confirm-add-btn" style="pointer-events:none; opacity:0.5;">Add</a>
                            <a class="button button-primary" href="/wp-admin/post-new.php?post_type=show" target="_blank">Create New Show</a>
                        </div>

                        <div id="add-status" style="margin-top:15px; padding:10px; border-radius:4px; display:none;"></div>
                    </div>
                </div>
            `;
        }

        // ---------------------------------------------------------------------
        // createModal — wires up events; HTML comes from buildModalHtml()
        // ---------------------------------------------------------------------

        function createModal() {
            $modal = $(buildModalHtml());
            $('body').append($modal);

            $modal.on('click', '.modal-close', e => {
                e.preventDefault();
                hideModal();
            });
            $modal.on('click', e => {
                if (e.target.id === 'add-to-show-modal') {
                    hideModal();
                }
            });

            $modal.on('input', '#show-search-input', function() {
                renderSearchResults($(this).val().trim().toLowerCase());
            });

            $(document).on('click.showSearch', e => {
                if (!$(e.target).closest('#show-search-input, #show-search-results').length) {
                    $modal.find('#show-search-results').hide();
                }
            });

            $modal.on('focus', '#show-search-input', function() {
                var q = $(this).val().trim().toLowerCase();
                if (q.length > 0) renderSearchResults(q);
            });

            $modal.on('click', '#show-selected-clear', () => {
                clearSelection();
                $modal.find('#show-search-input').val('').focus();
            });

            $modal.on('click', '#confirm-add-btn', e => {
                e.preventDefault();
                if (!selectedShowId) return;
                ($modal.data('mode') === 'single') ? addSingleItemToShow() : copyAllItemsToShow();
            });
        }

        // ---------------------------------------------------------------------
        // Search results rendering
        // ---------------------------------------------------------------------

        function renderSearchResults(query) {
            if (!showsList) return;
            var $results = $modal.find('#show-search-results').empty();

            if (!query) { $results.hide(); return; }

            var filtered = showsList.filter(show => {
                return show.title.toLowerCase().includes(query) || show.date.includes(query);
            });

            if (filtered.length === 0) {
                $results.append(
                    $('<li>').text('No shows found').css({
                        padding: '8px 12px',
                        color: '#999',
                        fontStyle: 'italic',
                        fontSize: '13px'
                    })
                );
            } else {
                filtered.forEach(show => {
                    var badge = show.status === 'draft' ? ' — Draft' : '';
                    $('<li>').css({
                        padding: '8px 12px',
                        cursor: 'pointer',
                        fontSize: '13px',
                        borderBottom: '1px solid #F0F0F0'
                    })
                    .html('<strong>' + escapeHtml(show.title) + '</strong><span style="color:#999; margin-left:6px;">' + escapeHtml(show.date + badge) + '</span>')
                    .on('mouseenter', function() { $(this).css('background', '#f0f6fc'); })
                    .on('mouseleave', function() { $(this).css('background', ''); })
                    .on('click',      function() { selectShow(show.id, show.title); })
                    .appendTo($results);
                });
            }
            $results.show();
        }

        function selectShow(id, title) {
            selectedShowId    = id;
            selectedShowTitle = title;
            $modal.find('#show-search-input').val('');
            $modal.find('#show-search-results').hide();
            $modal.find('#show-selected-label').text(title);
            $modal.find('#show-selected-display').css('display', 'flex');
            $modal.find('#confirm-add-btn').css({
                'pointer-events': '',
                opacity: ''
            });
        }

        function clearSelection() {
            selectedShowId = selectedShowTitle = null;
            $modal.find('#show-selected-display').hide();
            $modal.find('#confirm-add-btn').css({
                'pointer-events': 'none',
                opacity: '0.5'
            });
        }

        function hideModal() {
            if (!$modal) return;
            $modal.hide();
            $modal.find('#add-status').hide();
            $modal.find('#show-search-input').val('');
            $modal.find('#show-search-results').hide();
            $modal.removeData('row').removeData('mode');
            clearSelection();
        }

        function openModal(mode, $row) {
            if (!$modal) createModal();

            $modal.find('#add-status').hide();
            clearSelection();
            $modal.find('#show-search-input').val('');
            $modal.find('#show-search-results').hide();

            if (mode === 'single') {
                $modal.find('#modal-title').text('Add Item to Show');
                $modal.find('#confirm-add-btn').text('Add Item');
                $modal.data('row', $row);
            } else {
                $modal.find('#modal-title').text('Copy All Items to Show');
                $modal.find('#confirm-add-btn').text('Copy All');
                $modal.removeData('row');
            }
            $modal.data('mode', mode);

            loadShowsList(() => {
                $modal.show();
                setTimeout(() => { $modal.find('#show-search-input').focus(); }, 50);
            });
        }

        // ---------------------------------------------------------------------
        // AJAX helpers
        // ---------------------------------------------------------------------

        function loadShowsList(callback) {
            if (showsList !== null) { if (callback) callback(); return; }

            $.post(tracklistSettings.ajax_url, {
                action: 'get_show_posts',
                nonce:  tracklistSettings.nonce,
                current_post_id: postId
            }).done(response => {
                if (response.success) showsList = response.data;
                else console.error('Failed to load shows:', response);
                if (callback) callback();
            }).fail((xhr, status, err) => {
                console.error('AJAX error loading shows:', status, err);
                if (callback) callback();
            });
        }

        function addSingleItemToShow() {
            if (!selectedShowId) return showModalStatus('Please select a target show.', 'error');

            var $row = $modal.data('row');
            if (!$row || !$row.length) return showModalStatus('Error: Row not found.', 'error');

            var item = {
                type:            $row.find('.item-type').val() || 'track',
                title:           $row.find('.item-title-input').val() || '',
                url:             $row.find('.item-url-input').val() || '',
                duration:        $row.find('.item-duration-input').val() || '',
                link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
            };

            if (!item.title) return showModalStatus('Item title is required, give me something to work with at least.', 'error');

            var $btn = $modal.find('#confirm-add-btn').prop('disabled', true).text('Adding...');

            $.post(tracklistSettings.ajax_url, {
                action: 'add_single_item_to_show',
                nonce:  tracklistSettings.nonce,
                target_post_id: selectedShowId,
                item: item
            }).done(response => {
                if (response.success) { showModalStatus('Item added successfully!', 'success'); setTimeout(hideModal, 1100); }
                else { showModalStatus('Error: ' + (response.data.message || 'Unknown error'), 'error'); $btn.prop('disabled', false).text('Add Item'); }
            }).fail((xhr, status, err) => {
                showModalStatus('Request failed. Please try again.', 'error');
                $btn.prop('disabled', false).text('Add Item');
                console.error('AJAX error:', status, err);
            });
        }

        function copyAllItemsToShow() {
            if (!selectedShowId) return showModalStatus('Please select a target show.', 'error');

            var allItems = [];
            $list.find('.tracklist-row').each(function() {
                var $row  = $(this);
                var title = $row.find('.item-title-input').val();
                if (!title) return;
                allItems.push({
                    type:            $row.find('.item-type').val() || 'track',
                    title:           title,
                    url:             $row.find('.item-url-input').val() || '',
                    duration:        $row.find('.item-duration-input').val() || '',
                    link_to_section: $row.find('.link-to-section-checkbox').is(':checked') ? '1' : '0'
                });
            });

            if (!allItems.length) return showModalStatus('No items to copy, what are you trying to do here, exactly?', 'error');

            var $btn = $modal.find('#confirm-add-btn').prop('disabled', true).text('Copying...');

            $.post(tracklistSettings.ajax_url, {
                action: 'copy_items_to_show',
                nonce:  tracklistSettings.nonce,
                target_post_id: selectedShowId,
                items: allItems
            }).done(response => {
                if (response.success) { showModalStatus(`Successfully copied ${response.data.count} item(s)!`, 'success'); setTimeout(hideModal, 1100); }
                else { showModalStatus('Error: ' + (response.data.message || 'Unknown error'), 'error'); $btn.prop('disabled', false).text('Copy All'); }
            }).fail((xhr, status, err) => {
                showModalStatus('Request failed. Please try again.', 'error');
                $btn.prop('disabled', false).text('Copy All');
                console.error('AJAX error:', status, err);
            });
        }

        function showModalStatus(message, type) {
            var isSuccess = type === 'success';
            $modal.find('#add-status').css({
                'background-color': isSuccess ? '#D4EDDA' : '#F8D7DA',
                'color':            isSuccess ? '#155724' : '#721C24',
                'border':           '1px solid ' + (isSuccess ? '#C3E6CB' : '#F5C6CB')
            }).html(message).show();
            if (isSuccess) setTimeout(() => $modal.find('#add-status').fadeOut(), 1100);
        }

        // =====================================================================
        // 5. BUTTON HANDLERS
        // =====================================================================

        $wrapper.on('click', '.add-to-show-btn',    e => { e.preventDefault(); openModal('single', $(e.currentTarget).closest('.tracklist-row')); });
        $wrapper.on('click', '.copy-all-to-show-btn', e => { e.preventDefault(); openModal('all'); });

        // =====================================================================
        // 6. INIT
        // =====================================================================

        calculateTotalDuration();
    }
});