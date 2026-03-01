/**
 * show-date-enforcement.js
 *
 * Client-side date enforcement for the Show post type.
 *
 * Rules enforced here (mirroring the server-side policy in shows.php):
 *
 *  1. The Publish / Update / Schedule button is disabled until a real date is
 *     chosen in the date picker. A visible callout below the button explains
 *     why it is disabled.
 *
 *  2. The "Pending Review" option is removed from the post status dropdown.
 *     Pending is not a valid state for show posts — scheduling a future date
 *     is the intended interim workflow.
 *
 *  3. "Save Draft" is hidden for show posts. The Pending option removal and
 *     draft-save removal are both cosmetic guards; the real enforcement lives
 *     server-side, but removing them from the UI prevents confusion.
 *
 *  4. On new posts (and legacy drafts with no date), the date-picker row
 *     receives a visual highlight so it is immediately obvious what action
 *     is needed.
 *
 * The script relies on:
 *  - showDateEnforcement.hasDate   (bool)  — whether the post already has a
 *                                           real date stored in the DB.
 *  - showDateEnforcement.isLegacyDraft (bool) — legacy draft needing a date.
 *
 * WordPress's own date picker markup (Classic Editor / Gutenberg-classic meta-
 * box) targets:
 *  - #publish / #save-post          — the main action buttons
 *  - #post-status-select            — the status dropdown container
 *  - #timestampdiv                  — the date/time picker wrapper
 *  - input#aa, #mm, #jj, #hh, #mn  — the individual date/time fields
 *  - a#edit-timestamp               — the "Edit" link that opens the picker
 *  - #timestamp                     — the display span showing the chosen date
 */

/* global showDateEnforcement, jQuery */
jQuery( function ( $ ) {
    'use strict';

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    var CALLOUT_ID    = 'show-date-required-callout';
    var HIGHLIGHT_CLS = 'show-date-picker-required';

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Returns true when all four date fields (year/month/day + at least one
     * time field) contain non-zero values, meaning the user has picked a date.
     *
     * We intentionally do not validate whether the date is "real" (e.g. Feb 30)
     * because WordPress itself will normalise it on save.
     */
    function dateFieldsAreSet() {
        var aa = parseInt( $( '#aa' ).val(), 10 );
        var mm = parseInt( $( '#mm' ).val(), 10 );
        var jj = parseInt( $( '#jj' ).val(), 10 );
        return aa > 0 && mm > 0 && jj > 0;
    }

    /**
     * Returns true once a real date is known — either it was already stored in
     * the database (hasDate flag from PHP) or the user has just filled in the
     * date picker during this editing session.
     */
    function hasValidDate() {
        // If the PHP layer already confirmed a date exists, trust it until the
        // user clears the picker fields (which would be unusual but possible).
        if ( showDateEnforcement.hasDate && dateFieldsAreSet() ) return true;

        // New post or legacy draft — require the picker to be filled in.
        return dateFieldsAreSet();
    }

    // =========================================================================
    // DOM TARGETS
    // =========================================================================

    // Publish / Schedule / Update button (Classic Editor meta-box).
    // WordPress renders one of several IDs depending on current post status.
    function $publishBtn() {
        return $( '#publish' );
    }

    // "Save Draft" link/button.
    function $saveDraftBtn() {
        return $( '#save-post' );
    }

    // The submit wrapper that contains both buttons + the status row.
    function $submitBox() {
        return $( '#submitdiv' );
    }

    // =========================================================================
    // CALLOUT
    // Inserted directly below the publish button so it is impossible to miss.
    // =========================================================================

    function ensureCallout() {
        if ( $( '#' + CALLOUT_ID ).length ) return;

        var $callout = $( '<p>', {
            id:    CALLOUT_ID,
            class: 'show-date-callout',
            html:  '<span class="dashicons dashicons-calendar-alt"></span> '
                 + 'Set a <strong>publish date</strong> above before saving.'
        } );

        // Insert after the publish button's wrapper div.
        var $after = $publishBtn().closest( '#publishing-action' );
        if ( $after.length ) {
            $after.after( $callout );
        } else {
            $submitBox().append( $callout );
        }
    }

    function removeCallout() {
        $( '#' + CALLOUT_ID ).remove();
    }

    // =========================================================================
    // DATE PICKER HIGHLIGHT
    // =========================================================================

    function highlightDatePicker( on ) {
        $( '#timestampdiv' ).toggleClass( HIGHLIGHT_CLS, on );
        // Also highlight the collapsed display link so it's obvious even
        // when the picker is in its closed/summary state.
        $( '#edit-timestamp' ).closest( '.misc-pub-section' ).toggleClass( HIGHLIGHT_CLS, on );
    }

    // =========================================================================
    // PUBLISH BUTTON STATE
    // =========================================================================

    function lockPublishButton() {
        $publishBtn()
            .prop( 'disabled', true )
            .addClass( 'show-date-btn-locked' )
            .attr( 'title', 'Set a publish date first' );
    }

    function unlockPublishButton() {
        $publishBtn()
            .prop( 'disabled', false )
            .removeClass( 'show-date-btn-locked' )
            .removeAttr( 'title' );
    }

    // =========================================================================
    // DRAFT / PENDING CLEANUP
    // =========================================================================

    /**
     * Remove "Pending Review" from the post-status <select> and hide the
     * "Save Draft" button. Both are not valid workflows for show posts.
     *
     * This is called once on DOMReady and again whenever WordPress re-renders
     * the submit box (which can happen after the status dropdown changes).
     */
    function cleanupInvalidStatuses() {
        // Remove "Pending Review" option from the status dropdown.
        $( '#post_status option[value="pending"]' ).remove();

        // Hide the "Save Draft" link entirely.
        $saveDraftBtn().hide();

        // If WordPress shows a "Status:" row with the current status text,
        // also remove any pending-related labels from it.
        $( '#post-status-display' ).each( function () {
            if ( $( this ).text().trim() === 'Pending Review' ) {
                $( this ).text( 'Draft' );
            }
        } );
    }

    // =========================================================================
    // MAIN UPDATE FUNCTION
    // Called on load and on every relevant change event.
    // =========================================================================

    function update() {
        var valid = hasValidDate();

        if ( valid ) {
            unlockPublishButton();
            removeCallout();
            highlightDatePicker( false );
        } else {
            lockPublishButton();
            ensureCallout();
            // Only highlight the picker on new/legacy posts — if the post
            // already has a date but the user somehow cleared the fields,
            // the callout is enough of a signal without the red border.
            highlightDatePicker( ! showDateEnforcement.hasDate || showDateEnforcement.isLegacyDraft );
        }

        cleanupInvalidStatuses();
    }

    // =========================================================================
    // EVENT BINDING
    // =========================================================================

    // Watch every individual date/time field for changes.
    $( document ).on( 'change input', '#aa, #mm, #jj, #hh, #mn', update );

    // WordPress re-renders parts of the submit box when the user clicks the
    // "Edit" link next to the date, or when the status changes. Re-run cleanup
    // after those mutations so our changes survive the re-render.
    var $submitDivTarget = document.getElementById( 'submitdiv' );
    if ( $submitDivTarget && typeof MutationObserver !== 'undefined' ) {
        var observer = new MutationObserver( function () {
            cleanupInvalidStatuses();
            // Re-lock if the publish button was re-rendered and is now enabled
            // when it shouldn't be.
            if ( ! hasValidDate() ) {
                lockPublishButton();
                ensureCallout();
            }
        } );
        observer.observe( $submitDivTarget, { childList: true, subtree: true } );
    }

    // Also watch for when the timestamp picker opens/closes (WordPress toggles
    // the display via inline JS that doesn't fire a standard event).
    $( document ).on( 'click', '#edit-timestamp, .cancel-timestamp, .save-timestamp', function () {
        // Small delay to let WordPress update the #timestamp display span
        // before we read the field values.
        setTimeout( update, 50 );
    } );

    // =========================================================================
    // INITIAL RUN
    // =========================================================================

    // Give WordPress a tick to finish rendering the submit meta-box before we
    // read the DOM for the first time.
    setTimeout( update, 0 );
} );