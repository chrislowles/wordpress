jQuery(document).ready(function($) {
    var container = $('#tracklist-container');

    // 1. Enable Drag and Drop Sorting using WordPress built-in jQuery UI
    container.sortable({
        handle: '.drag-handle',
        placeholder: 'placeholder-highlight',
        axis: 'y'
    });

    // 2. Function to add a new row
    function addRow(type) {
        var index = container.find('.track-row').length;
        
        // Define placeholders based on type
        var titlePlaceholder = (type === 'spacer') ? '[In The Cinema/The Pin Drop/Walking On Thin Ice/One Up]' : 'Artist/Group - Track Title';

        // Hide URL input if it is a spacer
        var urlStyle = (type === 'spacer') ? 'display:none;' : '';
        var rowClass = (type === 'spacer') ? 'track-row is-spacer' : 'track-row';

        var html = `
            <div class="${rowClass}">
                <span class="drag-handle" title="Drag to reorder">|||</span>
                <input type="hidden" name="tracklist[${index}][type]" value="${type}" />
                <input type="text" name="tracklist[${index}][track_title]" placeholder="${titlePlaceholder}" class="widefat" />
                <input type="url" name="tracklist[${index}][track_url]" placeholder="https://..." class="track-url-input" style="${urlStyle}" />
                <button type="button" class="remove-track button">Remove</button>
            </div>
        `;
        container.append(html);
    }

    // 3. Button Click Events
    $('.add-track').on('click', function() { addRow('track'); });
    $('.add-spacer').on('click', function() { addRow('spacer'); });

    // 4. Remove Button Event (Delegated for dynamically added items)
    container.on('click', '.remove-track', function() {
        $(this).closest('.track-row').remove();
    });
});