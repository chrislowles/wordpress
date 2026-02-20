jQuery(document).ready(function ($) {

    if (typeof showTemplate === 'undefined') return;

    // Utilities provided by utils.js / tracklist.js
    var escapeHtml = ThemeUtils.escapeHtml;

    var $templateButton = $('<button>', {
        type:  'button',
        class: 'button button-secondary',
        id:    'load-show-template',
        text:  'Load Template'
    });

    var $titleWrap = $('#titlewrap');
    if ($titleWrap.length) $titleWrap.append($templateButton);

    $templateButton.on('click', function (e) {
        e.preventDefault();

        var $titleField  = $('#title');
        var currentBody  = ThemeUtils.getEditorContent();
        var hasContent   = $titleField.val().trim() !== '' || currentBody.trim() !== '';

        if (hasContent && !confirm('This will replace the current title and content. Continue?')) return;

        $titleField.val(showTemplate.title).trigger('input');
        setEditorContent(showTemplate.body);
        addTemplateSpacers();

        $templateButton.text('Template Loaded').prop('disabled', true);
        setTimeout(function () { $templateButton.text('Load Template').prop('disabled', false); }, 2000);
    });

    // =========================================================================
    // EDITOR WRITE
    // =========================================================================

    function setEditorContent(content) {
        if (typeof window.easyMDE  !== 'undefined' && typeof window.easyMDE.value  === 'function') { window.easyMDE.value(content);  return; }
        if (typeof window.simpleMDE !== 'undefined' && typeof window.simpleMDE.value === 'function') { window.simpleMDE.value(content); return; }

        const mmd = document.querySelector('.mmd-running');
        if (mmd) {
            if (mmd.EasyMDE)    { mmd.EasyMDE.value(content);              return; }
            if (mmd.codemirror) { mmd.codemirror.setValue(content); if (typeof mmd.codemirror.refresh === 'function') mmd.codemirror.refresh(); return; }
        }

        if (typeof window.mmd_editor !== 'undefined') {
            if (typeof window.mmd_editor.value    === 'function') { window.mmd_editor.value(content);    return; }
            if (typeof window.mmd_editor.setValue === 'function') { window.mmd_editor.setValue(content); if (typeof window.mmd_editor.refresh === 'function') window.mmd_editor.refresh(); return; }
        }

        const cm = document.querySelector('.CodeMirror');
        if (cm && cm.CodeMirror) { cm.CodeMirror.setValue(content); if (typeof cm.CodeMirror.refresh === 'function') cm.CodeMirror.refresh(); return; }

        $('#content').val(content).trigger('change').trigger('input');
    }

    // =========================================================================
    // SPACER INJECTION
    // =========================================================================

    function addTemplateSpacers() {
        var $tracklistItems = $('.tracklist-wrapper .tracklist-items');
        if (!$tracklistItems.length) { console.warn('Tracklist wrapper not found'); return; }

        if (!showTemplate.spacers || !showTemplate.spacers.length) return;

        // Prepend in reverse order so the first spacer ends up at the top
        for (var i = showTemplate.spacers.length - 1; i >= 0; i--) {
            $tracklistItems.prepend(buildSpacerRowHtml(showTemplate.spacers[i]));
        }

        // Delegate re-indexing and duration recalculation to tracklist.js
        if (window.TracklistRefresh) {
            window.TracklistRefresh.inputs();
            window.TracklistRefresh.duration();
        }
    }

    /**
     * Build the HTML for a single linked spacer row.
     * CSS in dashboard.css controls which fields are visible for spacers;
     * no inline styles needed here.
     */
    function buildSpacerRowHtml(title) {
        return `
            <div class="tracklist-row is-spacer">
                <span class="drag-handle" title="Drag">|||</span>
                <input type="hidden" name="tracklist[0][type]" value="spacer" class="item-type" />
                <input type="text" name="tracklist[0][title]" class="item-title-input" placeholder="Segment Title..." value="${escapeHtml(title)}" />
                <input type="url" name="tracklist[0][url]" class="item-url-input" placeholder="https://..." />
                <input type="text" name="tracklist[0][duration]" class="item-duration-input" placeholder="3:45" />
                <label class="link-checkbox-label" title="Link this spacer to a section in the body content">
                    <input type="checkbox" name="tracklist[0][link_to_section]" class="link-to-section-checkbox" value="1" checked />
                    Link
                </label>
                <button type="button" class="add-to-show-btn button">Add to Show</button>
                <button type="button" class="remove-item button">Delete</button>
            </div>
        `;
    }
});