/**
 * show-date-enforcement.js
 *
 * Client-side date enforcement for the Show post type.
 *
 * Core problem being solved
 * -------------------------
 * When WordPress creates a new post or a post is saved with "Publish
 * immediately", it pre-fills the date picker fields with the current time.
 * This means the date fields always contain *a* date — we cannot use them
 * alone to detect whether the user has actually chosen one.
 *
 * The solution: track whether the user has explicitly clicked the "OK" button
 * inside the date picker (.save-timestamp). Only that action constitutes an
 * intentional date choice. Until it happens, the Publish button is locked.
 *
 * State model
 * -----------
 * dateExplicitlySet — starts true only for posts that already have a
 *   confirmed date from a previous save (_show_date_confirmed meta, passed
 *   via showDateEnforcement.dateConfirmed). For every other post — new,
 *   "Publish immediately", or legacy draft — it starts false and becomes
 *   true only when the user clicks OK in the picker.
 *
 * When dateExplicitlySet is true, the hidden field show_date_explicitly_set=1
 * is injected into the form so the PHP enforcement layer can verify it.
 *
 * Once a post is confirmed (dateConfirmed = true from PHP), subsequent edits
 * start with the button enabled. The user can save normally without having
 * to re-open the picker — they only need to open it again if they want to
 * actually change the date.
 *
 * Rules also enforced here
 * ------------------------
 *  - "Pending Review" option removed from the status dropdown (not a valid
 *    show state; the scheduled date serves as the pre-publish holding state).
 *  - "Save Draft" button hidden (no separate draft workflow for shows).
 *  - Date picker section highlighted with an amber outline until confirmed.
 *  - Callout inserted beneath the Publish button explaining what to do.
 *
 * WordPress Classic Editor DOM targets used
 * -----------------------------------------
 *  #publish              — main Publish / Schedule / Update button
 *  #save-post            — Save Draft link
 *  #timestampdiv         — date/time picker container
 *  .misc-pub-section     — the row in the publish box containing the date
 *  input#aa, #mm, #jj    — year / month / day fields (used for validation)
 *  .save-timestamp       — OK button inside the picker (date confirmed here)
 *  .cancel-timestamp     — Cancel button inside the picker
 *  #timestamp            — display span showing current date/status
 *  #submitdiv            — the whole publish meta-box (watched for re-renders)
 */

/* global showDateEnforcement, jQuery */
jQuery( function ( $ ) {
    'use strict';

    var CALLOUT_ID       = 'show-date-required-callout';
    var HIGHLIGHT_CLS    = 'show-date-picker-required';
    var HIDDEN_FIELD_NAME = 'show_date_explicitly_set';

    // =========================================================================
    // STATE
    // dateExplicitlySet — the single source of truth for whether saving is
    // allowed. Starts true only when PHP confirmed a proper date already exists.
    // =========================================================================

    var dateExplicitlySet = showDateEnforcement.dateConfirmed === true;

    // =========================================================================
    // HIDDEN FIELD
    // Keeps the form field in sync with dateExplicitlySet. PHP reads this to
    // distinguish an intentional date from a WordPress-auto-filled one.
    // =========================================================================

    function syncHiddenField() {
        var $field = $( 'input[name="' + HIDDEN_FIELD_NAME + '"]' );
        if ( ! $field.length ) {
            $field = $( '<input>', { type: 'hidden', name: HIDDEN_FIELD_NAME } );
            $( '#post' ).append( $field );
        }
        $field.val( dateExplicitlySet ? '1' : '0' );
    }

    // =========================================================================
    // DATE FIELD VALIDATION
    // Used as a secondary guard: even if dateExplicitlySet is true we won't
    // enable the button if the year/month/day fields are zeroed out.
    // =========================================================================

    function dateFieldsAreSet() {
        var aa = parseInt( $( '#aa' ).val(), 10 );
        var mm = parseInt( $( '#mm' ).val(), 10 );
        var jj = parseInt( $( '#jj' ).val(), 10 );
        return aa > 0 && mm > 0 && jj > 0;
    }

    function hasValidDate() {
        return dateExplicitlySet && dateFieldsAreSet();
    }

    // =========================================================================
    // PUBLISH IMMEDIATELY DETECTION
    // WordPress renders "Publish immediately" (or its localised equivalent) in
    // the #timestamp span when no explicit date has been set. Checking this on
    // load lets us catch posts that were saved in that state and reset the
    // explicit flag for them, even if dateConfirmed was somehow truthy.
    // =========================================================================

    function isPublishImmediately() {
        var text = ( $( '#timestamp' ).text() || '' ).toLowerCase();
        return text.indexOf( 'immediately' ) !== -1;
    }

    // =========================================================================
    // UI: CALLOUT
    // =========================================================================

    function ensureCallout() {
        if ( $( '#' + CALLOUT_ID ).length ) return;

        var $callout = $( '<p>', {
            id:    CALLOUT_ID,
            class: 'show-date-callout',
            html:  '<span class="dashicons dashicons-calendar-alt"></span> '
                 + 'Open the date picker above, choose a date, then click <strong>OK</strong>.'
        } );

        var $after = $( '#publish' ).closest( '#publishing-action' );
        if ( $after.length ) {
            $after.after( $callout );
        } else {
            $( '#submitdiv' ).append( $callout );
        }
    }

    function removeCallout() {
        $( '#' + CALLOUT_ID ).remove();
    }

    // =========================================================================
    // UI: DATE PICKER HIGHLIGHT
    // =========================================================================

    function highlightDatePicker( on ) {
        $( '#timestampdiv' ).toggleClass( HIGHLIGHT_CLS, on );
        $( '#edit-timestamp' ).closest( '.misc-pub-section' ).toggleClass( HIGHLIGHT_CLS, on );
    }

    // =========================================================================
    // UI: PUBLISH BUTTON
    // =========================================================================

    function lockPublishButton() {
        $( '#publish' )
            .prop( 'disabled', true )
            .addClass( 'show-date-btn-locked' )
            .attr( 'title', 'Set a publish date first' );
    }

    function unlockPublishButton() {
        $( '#publish' )
            .prop( 'disabled', false )
            .removeClass( 'show-date-btn-locked' )
            .removeAttr( 'title' );
    }

    // =========================================================================
    // UI: INVALID STATUS CLEANUP
    // Removes "Pending Review" from the dropdown and hides "Save Draft".
    // Called on load and after every submit-box mutation.
    // =========================================================================

    function cleanupInvalidStatuses() {
        $( '#post_status option[value="pending"]' ).remove();
        $( '#save-post' ).hide();

        // If WP is currently displaying "Pending Review" as the status label,
        // relabel it to Draft so the UI doesn't show a state that can't exist.
        $( '#post-status-display' ).each( function () {
            if ( $( this ).text().trim() === 'Pending Review' ) {
                $( this ).text( 'Draft' );
            }
        } );
    }

    // =========================================================================
    // MAIN UPDATE — called on load and after every relevant event
    // =========================================================================

    function update() {
        syncHiddenField();

        if ( hasValidDate() ) {
            unlockPublishButton();
            removeCallout();
            highlightDatePicker( false );
        } else {
            lockPublishButton();
            ensureCallout();
            highlightDatePicker( true );
        }

        cleanupInvalidStatuses();
    }

    // =========================================================================
    // EVENT: user clicked OK inside the date picker
    // This is the ONLY moment we treat as "date explicitly set".
    // =========================================================================

    $( document ).on( 'click', '.save-timestamp', function () {
        // Give WordPress a tick to write the new values into the fields and
        // update the #timestamp display span before we read them.
        setTimeout( function () {
            if ( dateFieldsAreSet() ) {
                dateExplicitlySet = true;
            }
            update();
        }, 50 );
    } );

    // =========================================================================
    // EVENT: user cancelled the date picker
    // If they land back on "Publish immediately", revoke explicit-set status.
    // =========================================================================

    $( document ).on( 'click', '.cancel-timestamp', function () {
        setTimeout( function () {
            if ( isPublishImmediately() ) {
                // Only revoke if the post hasn't been confirmed yet. A
                // confirmed post that the user happens to cancel on shouldn't
                // suddenly be blocked — they've already set a date previously.
                if ( ! showDateEnforcement.dateConfirmed ) {
                    dateExplicitlySet = false;
                }
            }
            update();
        }, 50 );
    } );

    // =========================================================================
    // MUTATION OBSERVER: watch #submitdiv for WordPress re-renders
    // WP can rebuild parts of the submit box when the status dropdown changes.
    // Re-run cleanup so our UI changes survive those re-renders.
    // =========================================================================

    var submitDiv = document.getElementById( 'submitdiv' );
    if ( submitDiv && typeof MutationObserver !== 'undefined' ) {
        var observer = new MutationObserver( function () {
            cleanupInvalidStatuses();
            // Re-lock if the button was re-rendered into an enabled state
            // while a date still hasn't been confirmed.
            if ( ! hasValidDate() ) {
                lockPublishButton();
                ensureCallout();
            }
        } );
        observer.observe( submitDiv, { childList: true, subtree: true } );
    }

    // =========================================================================
    // INITIAL RUN
    // Use setTimeout(0) to queue after WordPress's own post.js has run and
    // populated the #timestamp display span (needed for isPublishImmediately).
    // =========================================================================

    setTimeout( function () {
        // If the post was supposedly confirmed but the picker is still showing
        // "Publish immediately", something is off — revoke confirmation so the
        // user is prompted to set a real date.
        if ( showDateEnforcement.dateConfirmed && isPublishImmediately() ) {
            dateExplicitlySet = false;
        }
        update();
    }, 0 );

} );