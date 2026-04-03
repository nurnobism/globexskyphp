/**
 * GlobexSky Notification Sounds
 *
 * Plays audio cues for new messages and notifications.
 * Respects user preferences (sound can be muted).
 */
const GlobexNotificationSounds = (function() {
    'use strict';

    let enabled = true;
    const audioCache = {};

    /**
     * Generate a simple beep tone using Web Audio API
     */
    function generateTone(frequency, duration, type) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = frequency || 520;
            osc.type = type || 'sine';
            gain.gain.value = 0.15;
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (duration || 0.3));
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + (duration || 0.3));
        } catch (e) {
            // Web Audio not available
        }
    }

    function play(soundType) {
        if (!enabled) return;

        switch (soundType) {
            case 'message':
                // Two-tone ascending for new messages
                generateTone(440, 0.15, 'sine');
                setTimeout(function() { generateTone(660, 0.2, 'sine'); }, 150);
                break;
            case 'notification':
                // Single higher tone for notifications
                generateTone(520, 0.25, 'triangle');
                break;
            case 'alert':
                // Urgent double-beep
                generateTone(800, 0.1, 'square');
                setTimeout(function() { generateTone(800, 0.1, 'square'); }, 200);
                break;
            default:
                generateTone(520, 0.2, 'sine');
        }
    }

    function setEnabled(value) {
        enabled = !!value;
        try {
            localStorage.setItem('globexsky_sound_enabled', enabled ? '1' : '0');
        } catch (e) {}
    }

    function isEnabled() { return enabled; }

    // Load preference
    try {
        var stored = localStorage.getItem('globexsky_sound_enabled');
        if (stored !== null) enabled = stored === '1';
    } catch (e) {}

    return {
        play: play,
        setEnabled: setEnabled,
        isEnabled: isEnabled
    };
})();
