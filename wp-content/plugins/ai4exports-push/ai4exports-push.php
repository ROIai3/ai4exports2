<?php
/*
Plugin Name: AI4Exports Push
Description: Spinge i documenti dei clienti verso il backend Hetzner per l'indicizzazione AI.
Version: 1.0
Author: Antonelli Academy
*/

if (!defined('ABSPATH')) exit;

define('AI4EXPORTS_BACKEND', 'https://api.antonelliacademy.it:8020'); // o http://188.34.184.133:8020
define('AI4EXPORTS_SECRET',  'AI4EXPORTS-SECURE-2025');

/**
 * Invia un singolo file al backend.
 */
function ai4exports_push_one($client_id, $attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) return new WP_Error('nofile', 'File non trovato');

    $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'application/octet-stream';
    $body = file_get_contents($file);
    $args = [
        'timeout' => 60,
        'headers' => [
            'Content-Type' => $mime,
            'X-Filename'   => basename($file),
        ],
        'body'    => $body,
    ];
    $url = AI4EXPORTS_BACKEND . '/ai/upload/' . intval($client_id) . '?key=' . rawurlencode(AI4EXPORTS_SECRET);
    $res = wp_remote_post($url, $args);
    if (is_wp_error($res)) return $res;

    $code = wp_remote_retrieve_response_code($res);
    if ($code !== 200) return new WP_Error('upload_failed', 'Upload fallito: HTTP ' . $code, ['response' => $res]);
    return json_decode(wp_remote_retrieve_body($res), true);
}

/**
 * Spinge tutti i documenti del cliente (array ACF documenti_cliente).
 */
function ai4exports_push_all($client_id) {
    if (!function_exists('get_fields')) return new WP_Error('acf_missing', 'ACF non disponibile');
    $acf = get_fields($client_id);
    $docs = isset($acf['documenti_cliente']) && is_array($acf['documenti_cliente']) ? $acf['documenti_cliente'] : [];

    $out = ['pushed' => 0, 'errors' => []];
    foreach ($docs as $row) {
        $aid = isset($row['documento_file']) ? intval($row['documento_file']) : 0;
        if ($aid) {
            $res = ai4exports_push_one($client_id, $aid);
            if (is_wp_error($res)) {
                $out['errors'][] = ['attachment' => $aid, 'error' => $res->get_error_message()];
            } else {
                $out['pushed']++;
            }
        }
    }
    return $out;
}

/**
 * Endpoint REST per avviare il push manualmente: /wp-json/ai4exports/v1/push/{id}?key=...
 */
add_action('rest_api_init', function () {
    register_rest_route('ai4exports/v1', '/push/(?P<id>\d+)', [
        'methods'  => 'POST',
        'callback' => function ($req) {
            $secret = $req->get_param('key');
            if ($secret !== AI4EXPORTS_SECRET) {
                return new WP_Error('forbidden', 'Accesso non autorizzato', ['status' => 403]);
            }
            $id = intval($req['id']);
            $result = ai4exports_push_all($id);
            return rest_ensure_response($result);
        },
        'permission_callback' => '__return_true',
    ]);
});

/**
 * (Opzionale) trigger automatico al salvataggio del CPT "clienti".
 * Decommenta se vuoi push automatico ad ogni salvataggio del cliente.
 */
// add_action('save_post_clienti', function ($post_id, $post, $update) {
//     if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
//     ai4exports_push_all($post_id);
// }, 10, 3);
