<?php
/*
Plugin Name: AI4Exports File Proxy
Description: Endpoint REST per servire i file WordPress al backend AI4Exports.
Version: 1.0
Author: Antonelli Academy
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('ai4exports/v1', '/file/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => function ($request) {
            $secret = $request->get_param('key');
            $allowed_key = 'AI4EXPORTS-SECURE-2025'; // deve coincidere con wp_sync.py

            if ($secret !== $allowed_key) {
                return new WP_Error('forbidden', 'Accesso non autorizzato', ['status' => 403]);
            }

            $id = intval($request['id']);
            $file = get_attached_file($id);

            if (!$file || !file_exists($file)) {
                return new WP_Error('not_found', 'File non trovato', ['status' => 404]);
            }

            $mime = mime_content_type($file);
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            readfile($file);
            exit;
        },
        'permission_callback' => '__return_true',
    ]);
});