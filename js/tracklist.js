jQuery(document).ready($ => {

    var $wrapper = $('.tracklist-wrapper');
    if ($wrapper.length === 0) return;

    // Shared utilities provided by utils.js
    var parseToSeconds  = ThemeUtils.parseToSeconds;
    var formatDuration  = ThemeUtils.formatDuration;
    var escapeHtml      = ThemeUtils.escapeHtml;

    initTracklist($wrapper);

    function initTracklist($wrapper) {
        var postId           = $wrapper.data('post-id');
        var $list            = $wrapper.find('.tracklist-items');
        var $durationDisplay = $wrapper.find('.total-duration-display');

        // =====================================================================
        // 1. DIRTY TRACKING
        // =====================================================================

        var isDirty = false;
        function markDirty() { isDirty = true; }
        function markClean() { isDirty = false; }

        $('#post').on('submit', markClean);

        window.addEventListener('beforeunload', function (e) {
            if (!isDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });

        // =====================================================================
        // 2. SORTABLE
        // =====================================================================

        $list.sortable({
            handle: '.drag-handle',
            placeholder: 'placeholder-highlight',
            axis: 'y',
            update: () => {
                calculateTotalDuration();
                refreshInputNames();
                markDirty();
            }
        });

        // =====================================================================
        // 3. DURATION HELPERS
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
        // 4. ADD / REMOVE ROWS
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
            markDirty();
        }

        $wrapper.find('.add-track').on('click', () => { addRow('track'); });
        $wrapper.find('.add-spacer').on('click', () => { addRow('spacer'); });

        $wrapper.on('click', '.remove-item', function() {
            $(this).closest('.tracklist-row').remove();
            calculateTotalDuration();
            refreshInputNames();
            markDirty();
        });

        $wrapper.on('input change', 'input', () => {
            calculateTotalDuration();
            markDirty();
        });

        // =====================================================================
        // 5. MODAL
        // =====================================================================

        var $modal              = null;
        var showsList           = null;
        var selectedShowId      = null;
        var selectedShowTitle   = null;

        // ---------------------------------------------------------------------
        // Modal HTML builder (Using HTML5 <dialog>)
        // ---------------------------------------------------------------------

        function buildModalHtml() {
            return `
                <dialog id="add-to-show-modal" class="tracklist-modal">
                    <div class="tracklist-modal-header">
                        <h2 id="modal-title">Add to Show</h2>
                        <button type="button" class="tracklist-modal-close modal-close" title="Close">&times;</button>
                    </div>

                    <div class="tracklist-modal-search">
                        <input type="text" id="show-search-input" class="widefat" placeholder="Search shows..." autocomplete="off" />
                        <ul id="show-search-results"></ul>
                    </div>

                    <div id="show-selected-display">
                        <span id="show-selected-label"></span>
                        <button type="button" id="show-selected-clear" title="Clear selection">&times;</button>
                    </div>

                    <div class="tracklist-modal-actions">
                        <a class="button modal-close">Cancel</a>
                        <a class="button button-primary disabled-btn" id="confirm-add-btn">Add</a>
                        <a class="button button-primary" href="/wp-admin/post-new.php?post_type=show" target="_blank">Create New Show</a>
                    </div>

                    <div id="add-status"></div>
                </dialog>
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
            
            // Native dialog click-away handler (clicking the backdrop targets the dialog itself)
            $modal.on('click', e => {
                if (e.target === $modal[0]) {
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
                $results.append($('<li>').text('No shows found').addClass('show-search-no-results'));
            } else {
                filtered.forEach(show => {
                    var badge = show.status === 'draft' ? ' — Draft' : '';
                    $('<li>')
                        .addClass('show-search-result-item')
                        .html('<strong>' + escapeHtml(show.title) + '</strong><span>' + escapeHtml(show.date + badge) + '</span>')
                        .on('click', function() { selectShow(show.id, show.title); })
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
            $modal.find('#confirm-add-btn').removeClass('disabled-btn');
        }

        function clearSelection() {
            selectedShowId = selectedShowTitle = null;
            $modal.find('#show-selected-display').hide();
            $modal.find('#confirm-add-btn').addClass('disabled-btn');
        }

        function hideModal() {
            if (!$modal) return;
            $modal[0].close();
            $modal.find('#add-status').hide().removeClass('status-success status-error');
            $modal.find('#show-search-input').val('');
            $modal.find('#show-search-results').hide();
            $modal.removeData('row').removeData('mode');
            clearSelection();
        }

        function openModal(mode, $row) {
            if (!$modal) createModal();

            $modal.find('#add-status').hide().removeClass('status-success status-error');
            clearSelection();
            $modal.find('#show-search-input').val('');
            $modal.find('#show-search-results').hide();

            if (mode === 'single') {
                $modal.find('#modal-title').text('Add Item to Show');
                $modal.find('#confirm-add-btn').text('Add Item').prop('disabled', false);
                $modal.data('row', $row);
            } else {
                $modal.find('#modal-title').text('Copy All Items to Show');
                $modal.find('#confirm-add-btn').text('Copy All').prop('disabled', false);
                $modal.removeData('row');
            }
            $modal.data('mode', mode);

            loadShowsList(() => {
                $modal[0].showModal();
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
            $modal.find('#add-status')
                .removeClass('status-success status-error')
                .addClass(isSuccess ? 'status-success' : 'status-error')
                .html(message)
                .show();
            if (isSuccess) setTimeout(() => $modal.find('#add-status').fadeOut(), 1100);
        }

        // =====================================================================
        // 6. BUTTON HANDLERS
        // =====================================================================

        $wrapper.on('click', '.add-to-show-btn',      e => { e.preventDefault(); openModal('single', $(e.currentTarget).closest('.tracklist-row')); });
        $wrapper.on('click', '.copy-all-to-show-btn', e => { e.preventDefault(); openModal('all'); });

        // =====================================================================
        // 7. INIT
        // =====================================================================

        calculateTotalDuration();
    }
});