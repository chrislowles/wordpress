jQuery(document).ready(function ($) {

    var formSubmitting = false;

    // =========================================================================
    // URL DETECTION HELPERS
    // =========================================================================

    /** True if content contains at least one bare URL (not inside a markdown link). */
    function hasBareUrls(content) {
        if (!content) return false;
        return /(?<!\]\()\b(https?:\/\/[^\s\)\]<>"']+)/gi.test(content);
    }

    /** Count bare URLs in content. */
    function countBareUrls(content) {
        if (!content) return 0;
        var matches = content.match(/(?<!\]\()\b(https?:\/\/[^\s\)\]<>"']+)/gi);
        return matches ? matches.length : 0;
    }

    // =========================================================================
    // FORM SUBMISSION INTERCEPT
    // =========================================================================

    $('#post').on('submit', function (e) {
        if (formSubmitting) return true;

        var content = ThemeUtils.getEditorContent();
        if (!hasBareUrls(content)) return true;

        e.preventDefault();

        var urlCount = countBareUrls(content);
        var message  = 'Found ' + urlCount + ' bare URL' + (urlCount !== 1 ? 's' : '') + ' in the content.\n\n'
            + 'Do you want to fetch page titles for these URLs?\n\n'
            + '• Yes = Fetch titles and convert to [Title](URL) format\n'
            + '• No = Save without fetching titles';

        var $field = $('input[name="fetch_link_titles"]');
        if (!$field.length) {
            $field = $('<input type="hidden" name="fetch_link_titles" />');
            $('#post').append($field);
        }
        $field.val(confirm(message) ? '1' : '0');

        formSubmitting = true;
        $('#post').submit();
        return false;
    });
});