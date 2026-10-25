<?php
/**
 * api/public_config.php
 * GET -> { success: true, config: {...} }
 *
 * The kiosk UI (welcome screen, theme, idle timer, sounds) needs a few
 * settings before any session exists, but the *full* settings tree
 * (printer name, admin passcode hash, etc.) must stay admin-only. This
 * endpoint hand-picks only the safe subset.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

json_response([
    'success' => true,
    'config' => [
        'countdown_seconds' => (int)Settings::get('countdown.seconds', 3),
        'countdown_play_sound' => (bool)Settings::get('countdown.play_sound', true),
        'mirror_preview' => (bool)Settings::get('camera.mirror_preview', true),
        'countdown_sound' => Settings::get('audio.countdown_sound', 'assets/sounds/beep.wav'),
        'shutter_sound' => Settings::get('audio.shutter_sound', 'assets/sounds/shutter.wav'),
        'volume' => (float)Settings::get('audio.volume', 0.8),
        'dark_mode' => Settings::get('ui.dark_mode', 'auto'),
        'idle_return_seconds' => (int)Settings::get('ui.idle_return_seconds', 20),
        'default_template' => Settings::get('templates.default_template', null),
        'jpeg_quality' => max(0.1, min(1, (int)Settings::get('storage.jpeg_quality', 92) / 100)),
        'sharing_enabled' => ImageHost::enabled(),
        'auto_print' => (bool)Settings::get('printing.auto_print', false) && PrintManager::isConfigured(),
    ],
]);
