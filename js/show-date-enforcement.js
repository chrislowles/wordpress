/**
 * show-date-enforcement.js  v3.0.0
 *
 * Hard split between confirmed and unconfirmed posts.
 *
 * CONFIRMED (dateConfirmed === true)
 *   Button starts unlocked. MutationObserver re-asserts the unlock after every
 *   DOM re-render but can never lock the button. The unconfirmed code path is
 *   never executed (early return).
 *
 * UNCONFIRMED (new post / "Publish immediately" / legacy draft without meta)
 *   Button starts locked. Only clicking OK in the date picker sets
 *   dateExplicitlySet = true and unlocks the button.
 */

/* global showDateEnforcement, jQuery */
jQuery( function ( $ ) {
    'use strict';

    var HIGHLIGHT_CLS     = 'show-date-picker-required';
    var HIDDEN_FIELD_NAME = 'show_date_explicitly_set';
    var isConfirmed       = showDateEnforcement.dateConfirmed === true;

    // =========================================================================
    // SHARED HELPERS
    // =========================================================================

    function syncHiddenField( value ) {
        var $field = $( 'input[name="' + HIDDEN_FIELD_NAME + '"]' );
        if ( ! $field.length ) {
            $field = $( '<input>', { type: 'hidden', name: HIDDEN_FIELD_NAME } );
            $( '#post' ).append( $field );
        }
        $field.val( value ? '1' : '0' );
    }

    function dateFieldsAreSet() {
        var aa = parseInt( $( '#aa' ).val(), 10 );
        var mm = parseInt( $( '#mm' ).val(), 10 );
        var jj = parseInt( $( '#jj' ).val(), 10 );
        return aa > 0 && mm > 0 && jj > 0;
    }

    function isPublishImmediately() {
        var text = ( $( '#timestamp' ).text() || '' ).toLowerCase();
        return text.indexOf( 'immediately' ) !== -1;
    }

    function highlightDatePicker( on ) {
        $( '#timestampdiv' ).toggleClass( HIGHLIGHT_CLS, on );
        $( '#edit-timestamp' ).closest( '.misc-pub-section' ).toggleClass( HIGHLIGHT_CLS, on );
    }

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

    function cleanupInvalidStatuses() {
        $( '#post_status option[value="pending"]' ).remove();
        $( '#save-post' ).hide();
        $( '#post-status-display' ).each( function () {
            if ( $( this ).text().trim() === 'Pending Review' ) {
                $( this ).text( 'Draft' );
            }
        } );
    }

    // =========================================================================
    // CONFIRMED PATH
    // The button is unlocked on load and stays unlocked. MutationObserver
    // re-asserts the unlock after every DOM re-render (autosave indicator,
    // word count, CodeMirror activity, etc.) but NEVER locks.
    // =========================================================================

    if ( isConfirmed ) {
        syncHiddenField( true );

        var confirmTimer;
        var submitDivConfirmed = document.getElementById( 'submitdiv' );

        if ( submitDivConfirmed && typeof MutationObserver !== 'undefined' ) {
            new MutationObserver( function () {
                clearTimeout( confirmTimer );
                confirmTimer = setTimeout( function () {
                    // Re-assert unlock — mutations never lock a confirmed post.
                    unlockPublishButton();
                    highlightDatePicker( false );
                    cleanupInvalidStatuses();
                }, 50 );
            } ).observe( submitDivConfirmed, { childList: true, subtree: true } );
        }

        setTimeout( function () {
            // Safety valve: if somehow the picker shows "Publish immediately"
            // despite the confirmation meta existing, treat it as needing a
            // re-pick (without revoking the meta server-side).
            if ( isPublishImmediately() ) {
                lockPublishButton();
                highlightDatePicker( true );
            } else {
                unlockPublishButton();
                highlightDatePicker( false );
            }
            cleanupInvalidStatuses();
        }, 0 );

        return; // ← confirmed posts never reach the unconfirmed code below
    }

    // =========================================================================
    // UNCONFIRMED PATH
    // dateConfirmed is false: new post, "Publish immediately", or legacy draft.
    // Button stays locked until the user clicks OK in the date picker.
    // =========================================================================

    var dateExplicitlySet = false;

    function hasValidDate() {
        return dateExplicitlySet && dateFieldsAreSet();
    }

    function update() {
        syncHiddenField( dateExplicitlySet );
        if ( hasValidDate() ) {
            unlockPublishButton();
            highlightDatePicker( false );
        } else {
            lockPublishButton();
            highlightDatePicker( true );
        }
        cleanupInvalidStatuses();
    }

    // OK button — the only action that constitutes an intentional date choice.
    $( document ).on( 'click', '.save-timestamp', function () {
        setTimeout( function () {
            if ( dateFieldsAreSet() ) {
                dateExplicitlySet = true;
            }
            update();
        }, 50 );
    } );

    // Cancel button — revoke if the picker lands back on "Publish immediately".
    $( document ).on( 'click', '.cancel-timestamp', function () {
        setTimeout( function () {
            if ( isPublishImmediately() ) {
                dateExplicitlySet = false;
            }
            update();
        }, 50 );
    } );

    // MutationObserver — re-runs update() after DOM settles.
    // For unconfirmed posts this is fine: update() may lock or unlock
    // based on dateExplicitlySet, which only changes via picker events.
    var observerTimer;
    var submitDivUnconfirmed = document.getElementById( 'submitdiv' );
    if ( submitDivUnconfirmed && typeof MutationObserver !== 'undefined' ) {
        new MutationObserver( function () {
            clearTimeout( observerTimer );
            observerTimer = setTimeout( update, 50 );
        } ).observe( submitDivUnconfirmed, { childList: true, subtree: true } );
    }

    // Initial run — queue after WP's own post.js populates #timestamp.
    setTimeout( function () {
        if ( isPublishImmediately() ) {
            dateExplicitlySet = false;
        }
        update();
    }, 0 );

} );