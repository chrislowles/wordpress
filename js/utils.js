/**
 * utils.js — Shared admin utilities
 *
 * Exposes window.ThemeUtils so tracklist.js, show-template-button.js, and
 * fetch-link-titles.js share one copy instead of three.
 * Enqueued via the 'theme-utils' handle before all three consumers.
 */
(function (window) {
    'use strict';

    /**
     * Parse a duration string to total seconds.
     * Accepts HH:MM:SS, MM:SS, or a plain integer string / number.
     */
    function parseToSeconds(duration) {
        if (!duration) return 0;
        duration = duration.toString().trim();

        if (duration.includes(':')) {
            const parts = duration.split(':').map(p => parseInt(p) || 0);
            if (parts.length === 3) return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
            if (parts.length === 2) return (parts[0] * 60) + parts[1];
        }

        return parseInt(duration) || 0;
    }

    /**
     * Format total seconds as H:MM:SS (≥1 hr) or M:SS (<1 hr).
     */
    function formatDuration(totalSeconds) {
        const hours = Math.floor(totalSeconds / 3600);
        const mins  = Math.floor((totalSeconds % 3600) / 60);
        const secs  = totalSeconds % 60;

        if (hours > 0) {
            return `${hours}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    /**
     * Escape special HTML characters to prevent XSS in innerHTML assignments.
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Return the current content of the active Markdown editor.
     *
     * Checks (in order): EasyMDE / SimpleMDE globals → .mmd-running element
     * (Markup Markdown plugin) → mmd_editor global → bare CodeMirror element
     * → plain #content textarea.
     */
    function getEditorContent() {
        if (typeof window.easyMDE   !== 'undefined' && typeof window.easyMDE.value   === 'function') return window.easyMDE.value();
        if (typeof window.simpleMDE !== 'undefined' && typeof window.simpleMDE.value === 'function') return window.simpleMDE.value();

        const mmd = document.querySelector('.mmd-running');
        if (mmd) {
            if (mmd.EasyMDE)    return mmd.EasyMDE.value();
            if (mmd.codemirror) return mmd.codemirror.getValue();
        }

        if (typeof window.mmd_editor !== 'undefined') {
            if (typeof window.mmd_editor.value    === 'function') return window.mmd_editor.value();
            if (typeof window.mmd_editor.getValue === 'function') return window.mmd_editor.getValue();
        }

        const cm = document.querySelector('.CodeMirror');
        if (cm && cm.CodeMirror) return cm.CodeMirror.getValue();

        const textarea = document.getElementById('content');
        return textarea ? textarea.value : '';
    }

    window.ThemeUtils = { parseToSeconds, formatDuration, escapeHtml, getEditorContent };

}(window));