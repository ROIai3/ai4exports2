<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
// ======================================================
// Avada Child – bootstrap minimo
// ======================================================
function theme_enqueue_styles() {
    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        []
    );
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 20);

function avada_lang_setup() {
    $lang = get_stylesheet_directory() . '/languages';
    load_child_theme_textdomain('Avada', $lang);
}
add_action('after_setup_theme', 'avada_lang_setup');

// ======================================================
// Helper – permessi su “clienti” (CPT)
// ======================================================
function ai4x_current_user_can_edit_cliente($cliente_id) {
    if (current_user_can('edit_post', $cliente_id)) {
        return true;
    }
    $post = get_post($cliente_id);
    return $post && get_current_user_id() === intval($post->post_author);
}

// ======================================================
// Helper – recupera TUTTI gli ID file da ACF documenti_cliente
// ======================================================
function ai4x_get_cliente_file_ids($cliente_id) {
    $rows = function_exists('get_field')
        ? get_field('documenti_cliente', $cliente_id, false)  // RAW
        : [];

    if (empty($rows) || !is_array($rows)) {
        return [];
    }

    $file_ids = [];

    foreach ($rows as $row) {
        if (is_numeric($row)) {
            $file_ids[] = intval($row);
            continue;
        }
        if (is_array($row)) {
            foreach ($row as $maybe_id) {
                if (is_numeric($maybe_id)) {
                    $file_ids[] = intval($maybe_id);
                }
            }
        }
    }

    $file_ids = array_values(array_unique($file_ids));
    return $file_ids;
}

// ======================================================
// Helper – rimuove un file da ACF documenti_cliente
// (gestisce sia repeater ACF che campi “semplici” tipo gallery/file)
// ======================================================
function ai4x_remove_file_from_documenti($cliente_id, $file_id) {
    $cliente_id = intval($cliente_id);
    $file_id    = intval($file_id);

    if ($cliente_id <= 0 || $file_id <= 0) {
        return false;
    }

    $removed = false;

    // Provo prima a capire se il campo è un repeater ACF
    $field = null;
    if (function_exists('acf_get_field')) {
        $field = acf_get_field('documenti_cliente');
    }

    // Caso 1: campo repeater
    if (
        $field
        && isset($field['type'])
        && $field['type'] === 'repeater'
        && function_exists('get_field')
        && function_exists('delete_row')
    ) {
        // Uso la chiave del campo per operare in modo più affidabile
        $field_key = $field['key'];

        $rows = get_field($field_key, $cliente_id);
        if (is_array($rows)) {
            $index = 0;
            foreach ($rows as $row) {
                $index++;
                $has_file = false;

                if (is_array($row)) {
                    foreach ($row as $value) {
                        if (is_numeric($value) && intval($value) === $file_id) {
                            $has_file = true;
                            break;
                        }
                    }
                }

                if ($has_file) {
                    delete_row($field_key, $index, $cliente_id);
                    $removed = true;
                }
            }
        }

        return $removed;
    }

    // Caso 2: campo NON repeater (es. gallery / file multiplo)
    if (function_exists('get_field') && function_exists('update_field')) {
        $rows = get_field('documenti_cliente', $cliente_id, false);

        if (empty($rows) || !is_array($rows)) {
            return false;
        }

        $changed = false;
        $new     = [];

        foreach ($rows as $row) {
            $keep = true;

            if (is_numeric($row)) {
                if (intval($row) === $file_id) {
                    $keep    = false;
                    $changed = true;
                }
            } elseif (is_array($row)) {
                foreach ($row as $maybe_id) {
                    if (is_numeric($maybe_id) && intval($maybe_id) === $file_id) {
                        $keep    = false;
                        $changed = true;
                        break;
                    }
                }
            }

            if ($keep) {
                $new[] = $row;
            }
        }

        if ($changed) {
            update_field('documenti_cliente', $new, $cliente_id);
        }

        return $changed || $removed;
    }

    // Se ACF non è disponibile, non posso gestire il campo
    return false;
}

// ======================================================
// ENDPOINT AJAX: upload_documento_cliente
// (usato dal nostro form personalizzato, NON da Avada)
// ======================================================
add_action('wp_ajax_upload_documento_cliente', 'ai4x_handle_upload_documento_cliente');
add_action('wp_ajax_nopriv_upload_documento_cliente', 'ai4x_handle_upload_documento_cliente');

function ai4x_handle_upload_documento_cliente() {
    // Controllo base sul file
    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => 'Nessun file ricevuto.']);
    }

    // ID cliente
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    if ($cliente_id <= 0) {
        wp_send_json_error(['message' => 'ID cliente mancante/errato.']);
    }

    // Permessi: se utente loggato, controllo che possa modificare il cliente
    if (is_user_logged_in() && !ai4x_current_user_can_edit_cliente($cliente_id)) {
        wp_send_json_error(['message' => 'Permessi insufficienti.']);
    }

    // Upload file “grezzo” in WordPress
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $overrides = [
        'test_form' => false,   // permette upload anche fuori da form classico WP
        // 'mimes'   => [...]    // se vuoi limitare tipi MIME
    ];

    $upload = wp_handle_upload($_FILES['file'], $overrides);

    if (isset($upload['error'])) {
        wp_send_json_error(['message' => 'Errore upload: ' . $upload['error']]);
    }

    // Nome documento (campo opzionale)
    $nome_documento = isset($_POST['nome_documento'])
        ? sanitize_text_field($_POST['nome_documento'])
        : '';

    if ($nome_documento === '') {
        $nome_documento = sanitize_file_name(basename($upload['file']));
    }

    // Creo attachment collegato al cliente
    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title'     => $nome_documento,
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_parent'    => $cliente_id,
    ];

    $attachment_id = wp_insert_attachment($attachment, $upload['file']);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        wp_send_json_error(['message' => 'Errore nella creazione dell’attachment.']);
    }

    // Metadati (thumb ecc.)
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    wp_update_attachment_metadata($attachment_id, $meta);

    // ==================================================
    // Collego il file al campo ACF "documenti_cliente"
    // - Se è un repeater: uso add_row()
    // - Altrimenti: aggiorno array di ID (gallery / file multiplo)
    // ==================================================
    if (function_exists('acf_get_field')) {
        $field = acf_get_field('documenti_cliente');
    } else {
        $field = null;
    }

    if (
        $field
        && isset($field['type'])
        && $field['type'] === 'repeater'
        && !empty($field['sub_fields'])
        && is_array($field['sub_fields'])
        && function_exists('add_row')
    ) {
        // Caso repeater: prendo il primo sub_field e ci metto l’ID del file
        $sub      = $field['sub_fields'][0];
        $sub_name = $sub['name'];

        $row = [
            $sub_name => $attachment_id,
        ];

        // Uso la chiave del campo per sicurezza
        add_row($field['key'], $row, $cliente_id);
    } elseif (function_exists('get_field') && function_exists('update_field')) {
        // Caso non repeater (gallery, file multiplo, ecc.)
        $raw = get_field('documenti_cliente', $cliente_id, false);

        if ($raw === null || $raw === '') {
            $raw = [];
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $id_str = (string) $attachment_id;

        // Evito duplicati sia come stringa che come intero
        if (!in_array($id_str, $raw, true) && !in_array($attachment_id, $raw, true)) {
            $raw[] = $id_str;
        }

        update_field('documenti_cliente', $raw, $cliente_id);
    }

    wp_send_json_success([
        'message'       => 'File caricato e collegato al cliente.',
        'attachment_id' => $attachment_id,
        'cliente_id'    => $cliente_id,
        'url'           => wp_get_attachment_url($attachment_id),
    ]);
}

// ======================================================
// SHORTCODE: [ai4x_documenti_cliente]
// Lista documenti con RINOMINA + RIMUOVI
// ======================================================
function ai4x_render_documenti_cliente_shortcode($atts = []) {
    $cliente_id = get_the_ID();
    if (!$cliente_id) {
        return '<p>Nessun cliente selezionato.</p>';
    }

    $ids = ai4x_get_cliente_file_ids($cliente_id);
    if (empty($ids)) {
        return '<p>Nessun documento caricato.</p>';
    }

    $nonce_delete = wp_create_nonce('ai4x_delete_documento_' . $cliente_id);
    $nonce_rename = wp_create_nonce('ai4x_rename_documento_' . $cliente_id);

    $out = '<ul class="ai4x-doc-list" style="list-style:none;padding-left:0;margin:0;">';

    foreach ($ids as $id) {
        $url = wp_get_attachment_url($id);
        if (!$url) {
            continue;
        }

        $name = get_the_title($id);
        if (!$name) {
            $name = basename($url);
        }
        $date = get_the_date('d/m/Y', $id);

        $out .= '<li style="margin:6px 0;display:flex;align-items:center;gap:10px;">';

        // Blocco nome documento
        $out .= '<span class="ai4x-doc-name" data-id="' . esc_attr($id) . '">';
        $out .= '📄 <a href="' . esc_url($url) . '" target="_blank" rel="noopener">'
             . esc_html($name) . '</a>';
        $out .= ' <span style="color:#666;font-size:12px;">(' . esc_html($date) . ')</span>';
        $out .= '</span>';

        // Wrapper pulsanti a destra
        $out .= '<span style="margin-left:auto;display:flex;gap:6px;align-items:center;">';

        // Pulsante mostra campo rinomina
        $out .= '<button type="button"
                        class="fusion-button button-default ai4x-rename-btn"
                        style="padding:3px 8px;font-size:12px;"
                        data-file-id="' . esc_attr($id) . '"
                        data-cliente-id="' . esc_attr($cliente_id) . '"
                        data-nonce="' . esc_attr($nonce_rename) . '"
                        onclick="ai4xShowRenameField(this);">Rinomina</button>';

        // Campo input e conferma rinomina (nascosti inizialmente)
        $out .= '<input type="text"
                        class="ai4x-rename-field"
                        style="display:none;font-size:12px;padding:3px;"
                        placeholder="Nuovo nome..."
                        data-file-id="' . esc_attr($id) . '"
                        data-cliente-id="' . esc_attr($cliente_id) . '"
                        data-nonce="' . esc_attr($nonce_rename) . '">';

        $out .= '<button type="button"
                        class="fusion-button button-default ai4x-rename-confirm"
                        style="display:none;padding:3px 8px;font-size:12px;"
                        onclick="ai4xRenameDocumento(this);">OK</button>';

        // Pulsante RIMUOVI
        $out .= '<button type="button"
                        class="fusion-button button-default ai4x-delete-doc"
                        style="padding:3px 8px;font-size:12px;"
                        data-file-id="' . esc_attr($id) . '"
                        data-cliente-id="' . esc_attr($cliente_id) . '"
                        data-nonce="' . esc_attr($nonce_delete) . '"
                        onclick="ai4xDeleteDocumento(this);">Rimuovi</button>';

        $out .= '</span>'; // fine wrapper pulsanti
        $out .= '</li>';
    }

    $out .= '</ul>';

    // Script JS: DELETE + RENAME
    $out .= '<script>
    function ai4xDeleteDocumento(button){
        var $btn = jQuery(button);
        if (!confirm("Sei sicuro di voler rimuovere questo documento?")) {
            return;
        }
        var fileId    = $btn.data("file-id");
        var clienteId = $btn.data("cliente-id");
        var nonce     = $btn.data("nonce");

        $btn.prop("disabled", true).text("Rimozione...");

        jQuery.ajax({
            url: "' . esc_js(admin_url('admin-ajax.php')) . '",
            type: "POST",
            dataType: "json",
            data: {
                action: "ai4x_delete_documento_cliente",
                cliente_id: clienteId,
                file_id: fileId,
                nonce: nonce
            },
            success: function(resp){
                if(resp && resp.success){
                    var $li = $btn.closest("li");
                    $li.fadeOut(200, function(){
                        jQuery(this).remove();
                        if (jQuery(".ai4x-doc-list li").length === 0) {
                            jQuery(".ai4x-doc-list").replaceWith("<p>Nessun documento caricato.</p>");
                        }
                    });
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Errore sconosciuto.";
                    alert("Errore nella rimozione: " + msg);
                    $btn.prop("disabled", false).text("Rimuovi");
                }
            },
            error: function(jqXHR){
                var msg = "Errore AJAX durante la rimozione.";
                if (jqXHR && jqXHR.responseText) {
                    msg += " Dettaglio: " + jqXHR.responseText;
                }
                alert(msg);
                $btn.prop("disabled", false).text("Rimuovi");
            }
        });
    }

    function ai4xShowRenameField(button){
        var $btn = jQuery(button);
        var $li = $btn.closest("li");
        $li.find(".ai4x-rename-field").show().focus();
        $li.find(".ai4x-rename-confirm").show();
    }

    function ai4xRenameDocumento(button){
        var $btn = jQuery(button);
        var $li = $btn.closest("li");
        var $input = $li.find(".ai4x-rename-field");

        var newName  = $input.val();
        var fileId   = $input.data("file-id");
        var clienteId= $input.data("cliente-id");
        var nonce    = $input.data("nonce");

        if(!newName || newName.trim() === ""){
            alert("Inserisci un nome valido.");
            return;
        }

        $btn.prop("disabled", true).text("Salvataggio...");

        jQuery.ajax({
            url: "' . esc_js(admin_url('admin-ajax.php')) . '",
            type: "POST",
            dataType: "json",
            data: {
                action: "ai4x_rename_documento_cliente",
                cliente_id: clienteId,
                file_id: fileId,
                new_name: newName,
                nonce: nonce
            },
            success: function(resp){
                if(resp && resp.success){
                    $li.find(".ai4x-doc-name a").text(newName);
                    $input.hide();
                    $li.find(".ai4x-rename-confirm").hide();
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : "Errore sconosciuto.";
                    alert("Errore nella rinomina: " + msg);
                }
                $btn.prop("disabled", false).text("OK");
            },
            error: function(){
                alert("Errore AJAX durante la rinomina.");
                $btn.prop("disabled", false).text("OK");
            }
        });
    }
    </script>';

    return $out;
}
add_shortcode("ai4x_documenti_cliente", "ai4x_render_documenti_cliente_shortcode");

// ======================================================
// SHORTCODE DEBUG: [ai4x_documenti_debug]
// ======================================================
function ai4x_debug_documenti_cliente_shortcode($atts = []) {
    $cliente_id = get_the_ID();
    if (!$cliente_id) {
        return '<pre>DEBUG: nessun cliente_id (get_the_ID() è vuoto).</pre>';
    }

    $raw = function_exists('get_field')
        ? get_field('documenti_cliente', $cliente_id, false)
        : null;

    return '<pre style="background:#111;color:#0f0;padding:10px;font-size:12px;white-space:pre-wrap;">'
         . 'DEBUG cliente_id=' . intval($cliente_id) . "\n\n"
         . esc_html(print_r($raw, true))
         . '</pre>';
}
add_shortcode('ai4x_documenti_debug', 'ai4x_debug_documenti_cliente_shortcode');

// ======================================================
// SHORTCODE TEST: [ai4x_test]
// ======================================================
function ai4x_test_shortcode() {
    return '<p style="color:red;font-weight:bold;">SHORTCODE FUNZIONA</p>';
}
add_shortcode('ai4x_test', 'ai4x_test_shortcode');

// ======================================================
// SHORTCODE FORM UPLOAD PERSONALIZZATO: [ai4x_upload_form]
// (sostituisce il form Avada per l’upload dei documenti)
// ======================================================
function ai4x_upload_form_shortcode($atts = []) {
    $cliente_id = get_the_ID();
    if (!$cliente_id) {
        return '<p>Errore: ID cliente non trovato.</p>';
    }

    ob_start();
    ?>
    <div id="ai4x-upload-wrapper">
        <form id="ai4x-upload-form" enctype="multipart/form-data" method="post">
            <input type="hidden" name="action" value="upload_documento_cliente">
            <input type="hidden" name="cliente_id" value="<?php echo esc_attr($cliente_id); ?>">

            <div class="ai4x-field" style="margin-bottom:10px;">
                <label for="ai4x-doc-name">Nome documento</label>
                <input type="text"
                       id="ai4x-doc-name"
                       name="nome_documento"
                       class="fusion-form-input"
                       style="width:100%;max-width:400px;">
            </div>

            <div class="ai4x-field" style="margin-bottom:10px;">
                <label for="ai4x-file">File *</label>
                <input type="file"
                       id="ai4x-file"
                       name="file"
                       required
                       style="display:block;">
            </div>

            <button type="submit"
                    class="fusion-button button-default"
                    style="margin-top:5px;">
                Upload
            </button>

            <div id="ai4x-upload-msg"
                 style="margin-top:10px;font-size:14px;"></div>
        </form>
    </div>

    <script>
    (function($){
        $(document).on('submit', '#ai4x-upload-form', function(e){
            e.preventDefault();

            var $form = $(this);
            var formData = new FormData(this);

            $('#ai4x-upload-msg').text('Caricamento in corso...');

            $.ajax({
                url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(resp){
                    if (resp && resp.success) {
                        $('#ai4x-upload-msg').text(resp.data.message || 'File caricato.');
                        // Ricarico per aggiornare la lista documenti
                        window.location.reload();
                    } else {
                        var msg = (resp && resp.data && resp.data.message)
                            ? resp.data.message
                            : 'Errore sconosciuto.';
                        $('#ai4x-upload-msg').text('Errore: ' + msg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){
                    var msg = 'Errore AJAX durante il caricamento.';
                    if (jqXHR && jqXHR.responseText) {
                        msg += ' Dettaglio: ' + jqXHR.responseText;
                    }
                    $('#ai4x-upload-msg').text(msg);
                }
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ai4x_upload_form', 'ai4x_upload_form_shortcode');

// ======================================================
// ENDPOINT AJAX: ai4x_delete_documento_cliente
// Rimuove il file dal campo ACF E cancella l'attachment
// ======================================================
add_action('wp_ajax_ai4x_delete_documento_cliente', 'ai4x_handle_delete_documento_cliente');

function ai4x_handle_delete_documento_cliente() {
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $file_id    = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    $nonce      = isset($_POST['nonce']) ? $_POST['nonce'] : '';

    if ($cliente_id <= 0 || $file_id <= 0) {
        wp_send_json_error(['message' => 'Parametri mancanti o non validi.']);
    }

    // Verifica nonce
    if (!wp_verify_nonce($nonce, 'ai4x_delete_documento_' . $cliente_id)) {
        wp_send_json_error(['message' => 'Nonce non valido. Ricarica la pagina.']);
    }

    // Permessi
    if (!is_user_logged_in() || !ai4x_current_user_can_edit_cliente($cliente_id)) {
        wp_send_json_error(['message' => 'Permessi insufficienti.']);
    }

    // 1) rimuovo dal campo ACF
    $ok_acf = ai4x_remove_file_from_documenti($cliente_id, $file_id);

    // 2) cancello l'attachment dalla media library
    $deleted = wp_delete_attachment($file_id, true); // true = forza delete, non nel cestino

    if (!$ok_acf && !$deleted) {
        wp_send_json_error(['message' => 'Impossibile rimuovere il documento.']);
    }

    wp_send_json_success([
        'message'    => 'Documento rimosso correttamente.',
        'cliente_id' => $cliente_id,
        'file_id'    => $file_id,
    ]);
}

// ======================================================
// ENDPOINT AJAX: ai4x_rename_documento_cliente
// Rinomina l'attachment collegato al cliente
// ======================================================
add_action('wp_ajax_ai4x_rename_documento_cliente', 'ai4x_handle_rename_documento_cliente');

function ai4x_handle_rename_documento_cliente() {
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $file_id    = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    $new_name   = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';
    $nonce      = isset($_POST['nonce']) ? $_POST['nonce'] : '';

    if ($cliente_id <= 0 || $file_id <= 0 || $new_name === '') {
        wp_send_json_error(['message' => 'Parametri mancanti.']);
    }

    if (!wp_verify_nonce($nonce, 'ai4x_rename_documento_' . $cliente_id)) {
        wp_send_json_error(['message' => 'Nonce non valido.']);
    }

    if (!is_user_logged_in() || !ai4x_current_user_can_edit_cliente($cliente_id)) {
        wp_send_json_error(['message' => 'Permessi insufficienti.']);
    }

    $update = wp_update_post([
        'ID'         => $file_id,
        'post_title' => $new_name,
    ], true);

    if (is_wp_error($update)) {
        wp_send_json_error(['message' => 'Errore durante la rinomina.']);
    }

    wp_send_json_success([
        'message' => 'Nome aggiornato correttamente.',
        'new'     => $new_name,
        'file_id' => $file_id,
    ]);
}

// ======================================================
// Helper: chiamata ad OpenAI (Responses API)
// ======================================================
if (!function_exists('ai4x_call_openai_agent')) {
    
function ai4x_call_openai_agent($cliente_id, $prompt_utente) {
    $cliente_id    = intval($cliente_id);
    $prompt_utente = trim((string) $prompt_utente);

    // Controlli base su configurazione
    if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
        return new WP_Error('ai4x_no_api_key', 'API key OpenAI non configurata.');
    }

    if ($cliente_id <= 0 || $prompt_utente === '') {
        return new WP_Error('ai4x_bad_params', 'Parametri mancanti per la chiamata all’AI.');
    }

    // Contesto dettagliato sui documenti del cliente (metadati + contenuto estratto)
    $doc_ids         = ai4x_get_cliente_file_ids($cliente_id);
    $docs_for_prompt = [];

    if (!empty($doc_ids)) {
        foreach ($doc_ids as $id) {
            $id      = intval($id);
            $title   = get_the_title($id);
            $date    = get_the_date('d/m/Y', $id);
            $mime    = get_post_mime_type($id);
            $content = ai4x_get_attachment_text($id);

            // Limita la lunghezza del contenuto per documento
            if ($content !== '') {
                $content_snippet = mb_substr($content, 0, 4000);
            } else {
                $content_snippet = '';
            }

            $docs_for_prompt[] =
                "Documento ID {$id} | Titolo: {$title} | Data: {$date} | Tipo: {$mime}\n" .
                ($content_snippet !== ''
                    ? "Contenuto (inizio):\n{$content_snippet}\n"
                    : "Contenuto non disponibile o non leggibile dal sistema.\n");
        }
    }

    $contesto_documenti = empty($docs_for_prompt)
        ? 'Nessun documento registrato per questo cliente.'
        : implode("\n-----------------------------\n", $docs_for_prompt);

    // Istruzioni di sistema (replicano il ruolo dell’agent)
    $system_instructions = "Sei l’assistente AI del sistema AI4Exports. " .
        "Ricevi il contesto di un cliente (ID, lista documenti e contenuto estratto) e una domanda dell’operatore. " .
        "Rispondi sempre in italiano, in modo chiaro, pratico e orientato al lavoro amministrativo. " .
        "Non inventare documenti o dati non presenti nel contesto fornito. " .
        "Se le informazioni non bastano, spiega cosa manca e che tipo di documento o dato servirebbe.";

    // Testo che descrive il contesto specifico di questo cliente
    $user_input =
        "Contesto gestionale per il cliente.\n" .
        "ID cliente: {$cliente_id}\n" .
        "Documenti collegati (metadati e contenuto estratto):\n{$contesto_documenti}\n\n" .
        "Domanda dell’operatore:\n" .
        $prompt_utente;

    // Limite di sicurezza sulla lunghezza del prompt complessivo
    $max_len = 120000;
    if (mb_strlen($user_input) > $max_len) {
        $user_input = mb_substr($user_input, 0, $max_len)
            . "\n\n[AVVISO: il contenuto dei documenti è stato troncato per motivi tecnici.]";
    }

    // Endpoint Responses API
    $endpoint = 'https://api.openai.com/v1/responses';

    // Body della richiesta
    $body = [
        'model' => 'gpt-4.1-mini',
        'input' => [
            [
                'role'    => 'system',
                'content' => $system_instructions,
            ],
            [
                'role'    => 'user',
                'content' => $user_input,
            ],
        ],
        'max_output_tokens' => 800,
    ];

    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode($body),
        'timeout' => 60,
    ];

    $response = wp_remote_post($endpoint, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    if ($code < 200 || $code >= 300) {
        return new WP_Error('ai4x_openai_status', 'OpenAI ha restituito stato HTTP ' . $code . '. Body: ' . $raw);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return new WP_Error('ai4x_openai_json', 'Risposta OpenAI non valida.');
    }

    // Parsing compatibile con Responses API
    if (isset($data['output_text']) && is_string($data['output_text'])) {
        $text = $data['output_text'];
    } else {
        $chunks = [];

        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                if (!isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $part) {
                    if (
                        isset($part['type'], $part['text']) &&
                        $part['type'] === 'output_text'
                    ) {
                        $chunks[] = (string) $part['text'];
                    }
                }
            }
        }

        $text = !empty($chunks) ? implode("\n\n", $chunks) : $raw;
    }

    return trim($text);
}


}
// ======================================================
// ENDPOINT AJAX: ai4x_query_ai
// Ponte tra frontend e OpenAI
// ======================================================
add_action('wp_ajax_ai4x_query_ai', 'ai4x_handle_query_ai');

if (!function_exists('ai4x_handle_query_ai')) {
    function ai4x_handle_query_ai() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Devi essere autenticato per usare l’AI.']);
        }

        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $prompt     = isset($_POST['prompt']) ? (string) $_POST['prompt'] : '';
        $nonce      = isset($_POST['nonce']) ? $_POST['nonce'] : '';

        if ($cliente_id <= 0 || trim($prompt) === '') {
            wp_send_json_error(['message' => 'ID cliente o domanda mancanti.']);
        }

        if (!wp_verify_nonce($nonce, 'ai4x_ai_query_' . $cliente_id)) {
            wp_send_json_error(['message' => 'Sicurezza non valida. Ricarica la pagina.']);
        }

        $result = ai4x_call_openai_agent($cliente_id, $prompt);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Risposta ricevuta.',
            'answer'  => $result,
        ]);
    }
}

// ======================================================
// SHORTCODE: [ai4x_ai_box]
// Box testo per interrogare l'AI sul cliente corrente
// ======================================================
if (!function_exists('ai4x_ai_box_shortcode')) {
    function ai4x_ai_box_shortcode($atts = []) {
        $cliente_id = get_the_ID();
        if (!$cliente_id) {
            return '<p>Errore: ID cliente non trovato.</p>';
        }

        $nonce = wp_create_nonce('ai4x_ai_query_' . $cliente_id);

        ob_start();
        ?>
        <div id="ai4x-ai-box" style="margin-top:25px;padding:15px;border:1px solid #ddd;border-radius:6px;">
            <h3 style="margin-top:0;">Assistente AI sui documenti</h3>
            <p style="font-size:14px;color:#555;">
                Scrivi una domanda relativa a questo cliente e ai documenti caricati
                (ad esempio: "Quali documenti mancano per la pratica X?").
            </p>

            <textarea id="ai4x-ai-prompt"
                      rows="4"
                      style="width:100%;max-width:100%;padding:8px;margin-bottom:10px;"></textarea>

            <button type="button"
                    id="ai4x-ai-send"
                    class="fusion-button button-default"
                    style="margin-bottom:10px;">
                Chiedi all'AI
            </button>

            <div id="ai4x-ai-status" style="font-size:13px;color:#666;margin-bottom:8px;"></div>

            <div id="ai4x-ai-response"
                 style="font-size:14px;line-height:1.5;border-top:1px solid #eee;padding-top:10px;"></div>

            <script>
            (function($){
                $(document).on('click', '#ai4x-ai-send', function(e){
                    e.preventDefault();

                    var prompt = $('#ai4x-ai-prompt').val();
                    if (!prompt || prompt.trim() === '') {
                        $('#ai4x-ai-status').text('Inserisci una domanda prima di inviare.');
                        return;
                    }

                    $('#ai4x-ai-status').text('Interrogazione in corso...');
                    $('#ai4x-ai-response').text('');

                    $.ajax({
                        url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: "ai4x_query_ai",
                            cliente_id: "<?php echo esc_js($cliente_id); ?>",
                            prompt: prompt,
                            nonce: "<?php echo esc_js($nonce); ?>"
                        },
                        success: function(resp){
                            if (resp && resp.success && resp.data && resp.data.answer) {
                                $('#ai4x-ai-status').text('');
                                $('#ai4x-ai-response').text(resp.data.answer);
                            } else {
                                var msg = (resp && resp.data && resp.data.message)
                                    ? resp.data.message
                                    : 'Errore sconosciuto.';
                                $('#ai4x-ai-status').text('Errore: ' + msg);
                            }
                        },
                        error: function(jqXHR){
                            var msg = 'Errore AJAX durante la chiamata all’AI.';
                            if (jqXHR && jqXHR.responseText) {
                                msg += ' Dettaglio: ' + jqXHR.responseText;
                            }
                            $('#ai4x-ai-status').text(msg);
                        }
                    });
                });
            })(jQuery);
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('ai4x_ai_box', 'ai4x_ai_box_shortcode');
add_action('init', function() {
    if (!current_user_can('manage_options')) return;

    $test_id = 396; // ID del file txt che hai caricato
    $path = get_attached_file($test_id);

    error_log("AI4X TEST PATH: " . print_r($path, true));

    if ($path && file_exists($path)) {
        $content = file_get_contents($path);
        error_log("AI4X TEST CONTENT: " . mb_substr($content, 0, 200));
    } else {
        error_log("AI4X TEST: FILE NON ESISTE");
    }
});
/**
 * Estrae testo leggibile da un allegato WordPress.
 * Per ora gestiamo bene: TXT, CSV, JSON, semplice HTML.
 * PDF/XLSX richiedono librerie aggiuntive (da aggiungere dopo).
 */
function ai4x_get_attachment_text( $attachment_id ) {
    $path = get_attached_file( $attachment_id );

    if ( ! $path || ! file_exists( $path ) ) {
        error_log( 'AI4X: file non trovato per attachment ' . $attachment_id . ' (path: ' . print_r( $path, true ) . ')' );
        return '';
    }

    $mime = get_post_mime_type( $attachment_id );

    // DEBUG: vedi che tipo di file stiamo processando
    error_log( 'AI4X: lettura contenuto attachment ' . $attachment_id . ' - mime: ' . $mime );

    // Testo semplice
    if ( strpos( $mime, 'text/' ) === 0 ) {
        $content = @file_get_contents( $path );
        if ( $content === false ) {
            error_log( 'AI4X: file_get_contents fallita per attachment ' . $attachment_id );
            return '';
        }

        // Limite di sicurezza per non esplodere il prompt
        $content = trim( $content );
        $content = mb_substr( $content, 0, 50000 ); // 50k caratteri bastano per ora

        return $content;
    }

    // PDF: placeholder – da implementare con libreria apposita
    if ( $mime === 'application/pdf' ) {
        // TODO: integrare parser PDF (es. spatie/pdf-to-text) se serve
        return '';
    }

    // Excel (xlsx/xls): usa PhpSpreadsheet
    if ( in_array( $mime, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
    ], true ) ) {

        // Carico l'autoload di Composer on-demand, se serve
        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
            $autoload = ABSPATH . 'vendor/autoload.php';

            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            } else {
                error_log( 'AI4X: autoload Composer non trovato per Excel: ' . $autoload );
                return '';
            }
        }

        try {
            $spreadsheet = IOFactory::load( $path );
        } catch ( \Throwable $e ) {
            error_log( 'AI4X: errore lettura Excel: ' . $e->getMessage() );
            return '';
        }

        $out = [];

        foreach ( $spreadsheet->getAllSheets() as $sheet ) {
            $sheetTitle = $sheet->getTitle();
            $out[] = "FOGLIO: {$sheetTitle}";

            $highestRow    = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            // Limitiamo il numero di righe per foglio (per non esplodere il prompt)
            $rowLimit = min( $highestRow, 300 );

            for ( $row = 1; $row <= $rowLimit; $row++ ) {
                $rowValues = [];

                for ( $col = 'A'; $col <= $highestColumn; $col++ ) {
                    $cell  = $sheet->getCell( $col . $row );
                    $value = $cell->getCalculatedValue();
                    $value = is_null( $value ) ? '' : (string) $value;
                    $value = trim( $value );
                    // niente newline strani
                    $value = preg_replace( '/\s+/', ' ', $value );
                    $rowValues[] = $value;
                }

                $joined = trim( implode( ' | ', $rowValues ) );

                // Saltiamo le righe completamente vuote
                if ( $joined !== '' ) {
                    $out[] = "RIGA {$row}: {$joined}";
                }
            }

            if ( $highestRow > $rowLimit ) {
                $out[] = "[...tagliate " . ( $highestRow - $rowLimit ) . " righe per motivi tecnici...]";
            }

            $out[] = ""; // riga vuota tra un foglio e l'altro
        }

        $text = implode( "\n", $out );

        // Limite di sicurezza globale
        $text = mb_substr( $text, 0, 100000 );

        return $text;
    }

    // Altri tipi non gestiti: per ora restituiamo vuoto
    return '';
}
/**
 * Legge un file Excel e lo trasforma in testo tabellare.
 * Il testo è pensato per essere letto dall'AI (per analisi e calcoli).
 */
function ai4x_extract_excel_text( string $path, int $max_rows_per_sheet = 300 ): string {
    try {
        $spreadsheet = IOFactory::load( $path );
    } catch ( \Throwable $e ) {
        error_log( 'AI4X: errore lettura Excel: ' . $e->getMessage() );
        return '';
    }

    $out = [];

    foreach ( $spreadsheet->getAllSheets() as $sheet ) {
        $sheetTitle = $sheet->getTitle();
        $out[] = "FOGLIO: {$sheetTitle}";

        $highestRow    = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $rowLimit = min( $highestRow, $max_rows_per_sheet );

        for ( $row = 1; $row <= $rowLimit; $row++ ) {
            $rowValues = [];
            for ( $col = 'A'; $col <= $highestColumn; $col++ ) {
                $cell   = $sheet->getCell( $col . $row );
                $value  = $cell->getCalculatedValue();
                $value  = is_null( $value ) ? '' : (string) $value;
                $value  = trim( $value );

                // Sostituisco eventuali newline per non rompere il formato
                $value  = preg_replace( '/\s+/', ' ', $value );
                $rowValues[] = $value;
            }

            // Salta righe completamente vuote
            $joined = trim( implode( ' | ', $rowValues ) );
            if ( $joined !== '' ) {
                $out[] = "RIGA {$row}: {$joined}";
            }
        }

        if ( $highestRow > $rowLimit ) {
            $out[] = "[...tagliate " . ( $highestRow - $rowLimit ) . " righe per motivi tecnici...]";
        }

        $out[] = ""; // riga vuota tra i fogli
    }

    $text = implode( "\n", $out );

    // Limite di sicurezza per tutto il file
    $text = mb_substr( $text, 0, 100000 );

    return $text;
}