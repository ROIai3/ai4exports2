<?php

define( 'MWAI_FORMS_FRONT_PARAMS', [ 'id', 'label', 'type', 'name', 'options', 'copyButton', 'localMemory',
  'required', 'placeholder', 'default', 'maxlength', 'rows', 'outputElement', 'accept', 'customAccept', 'multiple',
  'conditionField', 'conditionValue', 'conditions', 'logic' ] );
define( 'MWAI_FORMS_SERVER_PARAMS', [ 'model', 'temperature', 'maxTokens', 'prompt', 'message',
  'envId', 'scope', 'resolution', 'message', 'assistantId', 'scope',
  'embeddingsIndex', 'embeddingsEnv', 'embeddingsEnvId', 'embeddingsNamespace', 'mcpServers'
] );

class MeowPro_MWAI_Forms {
  private $core = null;
  private $namespace = 'mwai-ui/v1';

  public function __construct() {
    global $mwai_core;
    $this->core = $mwai_core;
    add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
    if ( MeowCommon_Helpers::is_asynchronous_request() ) {
      return;
    }
    add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
    add_action( 'wp_enqueue_scripts', [ $this, 'check_and_enqueue_container_assets' ], 20 );
    add_shortcode( 'mwai-form-field', [ $this, 'shortcode_mwai_form_field' ] );
    add_shortcode( 'mwai-form-upload', [ $this, 'shortcode_mwai_form_upload' ] );
    add_shortcode( 'mwai-form-submit', [ $this, 'shortcode_mwai_form_submit' ] );
    add_shortcode( 'mwai-form-reset', [ $this, 'shortcode_mwai_form_reset' ] );
    add_shortcode( 'mwai-form-output', [ $this, 'shortcode_mwai_form_output' ] );
    add_shortcode( 'mwai-form-container', [ $this, 'shortcode_mwai_form_container' ] );
    add_shortcode( 'mwai-form-conditional', [ $this, 'shortcode_mwai_form_conditional' ] );
  }

  public function register_scripts() {
    $physical_file = trailingslashit( MWAI_PATH ) . 'premium/forms.js';
    $cache_buster = file_exists( $physical_file ) ? filemtime( $physical_file ) : MWAI_VERSION;
    wp_register_script(
      'mwai_forms',
      trailingslashit( MWAI_URL ) . 'premium/forms.js',
      [ 'wp-element' ],
      $cache_buster,
      false
    );
  }

  public function enqueue_scripts( $themeId = null ) {
    wp_enqueue_script( 'mwai_forms' );
    if ( $themeId ) {
      $this->core->enqueue_theme( $themeId );
    }
  }

  public function check_and_enqueue_container_assets() {
    global $post;
    if ( ! $post ) {
      return;
    }
    
    // Check if the post content has our new form container data attribute
    if ( strpos( $post->post_content, 'data-mwai-form-container' ) !== false ) {
      // Check if any form fields are present which would trigger forms.js enqueue
      $has_form_fields = strpos( $post->post_content, 'mwai-form-field-container' ) !== false
        || strpos( $post->post_content, 'mwai-form-submit-container' ) !== false
        || strpos( $post->post_content, 'mwai-form-upload-container' ) !== false
        || strpos( $post->post_content, 'mwai-form-output-container' ) !== false
        || strpos( $post->post_content, 'mwai-form-reset-container' ) !== false
        || strpos( $post->post_content, 'mwai-form-conditional-container' ) !== false;
      
      // If there are form fields, forms.js will be enqueued by their shortcodes
      // We just need to handle theme CSS for containers without other form elements
      if ( ! $has_form_fields ) {
        // Extract theme from data-theme attribute
        if ( preg_match( '/data-theme="([^"]+)"/', $post->post_content, $matches ) ) {
          $theme = $matches[1];
          if ( $theme && $theme !== 'none' ) {
            $this->core->enqueue_theme( strtolower( $theme ) );
          }
        }
      }
    }

    // Also handle the case where a Forms post is embedded via the [mwai_form id="..."] shortcode.
    // In that case, the current $post->post_content will only contain the shortcode, not the actual
    // form content with the data-theme attribute. We proactively fetch the referenced form post
    // here so we can enqueue the theme CSS early enough (during wp_enqueue_scripts).
    if ( strpos( $post->post_content, '[mwai_form' ) !== false ) {
      if ( preg_match_all( '/\[mwai_form\s+[^\]]*id=\"?(\d+)\"?[^\]]*\]/', $post->post_content, $matches ) ) {
        $ids = isset( $matches[1] ) ? $matches[1] : [];
        foreach ( $ids as $form_id ) {
          $form_post = get_post( intval( $form_id ) );
          if ( $form_post && $form_post->post_type === 'mwai_form' ) {
            $form_content = $form_post->post_content;
            if ( preg_match( '/data-theme=\"([^\"]+)\"/', $form_content, $themeMatch ) ) {
              $theme = strtolower( $themeMatch[1] );
              if ( $theme && $theme !== 'none' ) {
                $this->core->enqueue_theme( $theme );
              }
            }
          }
        }
      }
    }
  }

  public function clean_params( &$params ) {
    foreach ( $params as $param => $value ) {
      if ( empty( $value ) || is_array( $value ) ) {
        continue;
      }
      $lowerCaseValue = strtolower( $value );
      if ( $lowerCaseValue === 'true' || $lowerCaseValue === 'false' || is_bool( $value ) ) {
        $params[$param] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
      }
      else if ( is_numeric( $value ) ) {
        $params[$param] = filter_var( $value, FILTER_VALIDATE_FLOAT );
      }
    }
    return $params;
  }

  public function fetch_system_params( $id ) {
    $frontSystem = [
      'id' => $id,
      'userData' => $this->core->get_user_data(),
      'sessionId' => $this->core->get_session_id(),
      'restNonce' => wp_create_nonce( 'wp_rest' ),
      'contextId' => get_the_ID(),
      'pluginUrl' => MWAI_URL,
      'restUrl' => untrailingslashit( get_rest_url() ),
      'debugMode' => $this->core->get_option( 'module_devtools' ) && $this->core->get_option( 'debug_mode' ),
      'stream' => $this->core->get_option( 'ai_streaming' ),
    ];
    return $frontSystem;
  }

  /**
   * Helper method to create REST responses with automatic token refresh
   * 
   * @param array $data The response data
   * @param int $status HTTP status code
   * @return WP_REST_Response
   */
  protected function create_rest_response( $data, $status = 200 ) {
    // Always check if we need to provide a new nonce
    $current_nonce = $this->core->get_nonce( true );
    $request_nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : null;
    
    // Check if nonce is approaching expiration (WordPress nonces last 12-24 hours)
    // We'll refresh if the nonce is older than 10 hours to be safe
    $should_refresh = false;
    
    if ( $request_nonce ) {
      // Try to determine the age of the nonce
      // WordPress uses a tick system where each tick is 12 hours
      // If we're in the second half of the nonce's life, refresh it
      $time = time();
      $nonce_tick = wp_nonce_tick();
      
      // Verify if the nonce is still valid but getting old
      $verify = wp_verify_nonce( $request_nonce, 'wp_rest' );
      if ( $verify === 2 ) {
        // Nonce is valid but was generated 12-24 hours ago
        $should_refresh = true;
        // Log will be written when token is included in response
      }
    }
    
    // If the nonce has changed or should be refreshed, include the new one
    if ( $should_refresh || ( $request_nonce && $current_nonce !== $request_nonce ) ) {
      $data['new_token'] = $current_nonce;
      
      // Log if server debug mode is enabled
      if ( $this->core->get_option( 'server_debug_mode' ) ) {
        error_log( '[AI Engine] Token refresh: Nonce refreshed (12-24 hours old)' );
      }
    }
    
    return new WP_REST_Response( $data, $status );
  }

  public function rest_api_init() {
    try {
      register_rest_route( $this->namespace, '/forms/submit', [
        'methods' => 'POST',
        'callback' => [ $this, 'rest_submit' ],
        'permission_callback' => '__return_true'
      ] );
    }
    catch ( Exception $e ) {
      var_dump( $e );
    }
  }

  public function rest_submit( $request ) {
    try {
      $params = $request->get_json_params();

      $context = null;
      $id = $params['id'] ?? null;
      $stream = $params['stream'] ?? false;
      $fields = $params['fields'] ?? [];
      $uploadFields = $params['uploadFields'] ?? [];

      // 1) Retrieve system params from the transient
      $systemParams = get_transient( 'mwai_custom_form_' . $id ) ?? [];
      $systemParams['prompt'] = $systemParams['prompt'] ?? '';
      $systemParams['message'] = $systemParams['message'] ?? '';
      $model = isset( $systemParams['model'] ) ? $systemParams['model'] : null;

      if ( !empty( $systemParams['prompt'] ) ) {
        Meow_MWAI_Logging::deprecated( 'The "prompt" parameter is deprecated. Please use the "message" parameter instead.' );
        $systemParams['message'] = $systemParams['prompt'];
      }
      if ( !empty( $params['prompt'] ) ) {
        Meow_MWAI_Logging::deprecated( 'The "prompt" parameter is deprecated. Please use the "message" parameter instead.' );
        $systemParams['message'] = $params['prompt'];
      }

      // 2) Prepare the message (based on the fields).
      $message = isset( $params['message'] ) ? $params['message'] : $systemParams['message'] ?? '';
      foreach ( $fields as $name => $value ) {
        if ( $value === null ) {
          continue;
        }
        if ( is_array( $value ) ) {
          $value = implode( ',', $value );
        }
        $name = '{' . $name . '}';
        $message = str_replace( '$' . $name, $value, $message );
        $message = str_replace( $name, $value, $message );
      }

      // Remove any remaining placeholders (upload fields)
      foreach ( $uploadFields as $name => $value ) {
        $name = '${' . $name . '}';
        $message = str_replace( $name, '', $message );
      }

      // 3) Finalize $systemParams => $params
      $systemParams['message'] = $message;
      $systemParams['scope'] = empty( $systemParams['scope'] ) ? 'form' : $systemParams['scope'];
      $newParams = [];
      foreach ( $systemParams as $key => $value ) {
        $newParams[$key] = $value;
      }
      foreach ( $params as $key => $value ) {
        $newParams[$key] = $value;
      }
      $params = apply_filters( 'mwai_forms_submit_params', $newParams );

      // 4) Retrieve model info
      $envId = $params['envId'] ?? null;
      $engine = Meow_MWAI_Engines_Factory::get( $this->core, $envId );
      $modelObj = $engine->retrieve_model_info( $model );
      if ( !empty( $envId ) && empty( $modelObj ) ) {
        return $this->create_rest_response( [ 'success' => false, 'message' => 'Model not found.' ], 500 );
      }
      $modelFeatures = isset( $modelObj['features'] ) ? $modelObj['features'] : [];
      $isTextToImage = in_array( 'text-to-image', $modelFeatures );
      $isSpeechToText = in_array( 'speech-to-text', $modelFeatures );

      // 5) Build the Query object
      $query = null;
      if ( $isTextToImage ) {
        $query = new Meow_MWAI_Query_Image( $message, $model );
        $query->inject_params( $params );
      }
      else if ( $isSpeechToText ) {
        $query = new Meow_MWAI_Query_Transcribe( $message );
        $query->inject_params( $params );
        $query->set_message( '' );
        $query->set_url( $message );
      }
      else {
        $query = !empty( $params['assistantId'] )
            ? new Meow_MWAI_Query_Assistant( $message )
              : new Meow_MWAI_Query_Text( $message, 4096 );
        $query->inject_params( $params );

        // If there's context from embeddings
        $context = $this->core->retrieve_context( $params, $query );
        if ( !empty( $context ) ) {
          $query->set_context( $context['content'] );
        }
      }

      // 6) Optional: If there's an uploaded image (or doc), feed it to the query
      // We'll loop over each field in $uploadFields. For each field => files array
      foreach ( $uploadFields as $fieldName => $fileArray ) {
        // Process all files in the array (supports both single and multi-file upload)
        foreach ( $fileArray as $fileInfo ) {
          $internalRefId = $fileInfo['id'];
          $url = $fileInfo['url'];

          // Create DroppedFile object - provider-agnostic approach (same as chatbot.php)
          $mimeType = $this->core->files->get_mime_type( $internalRefId );
          $isIMG = in_array( $mimeType, [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ] );
          $purposeType = $isIMG ? 'vision' : 'files';

          // Images use URL (can be sent as base64 or URL in messages)
          // PDFs use refId (engines will upload to their Files API as needed)
          if ( $isIMG ) {
            $droppedFile = Meow_MWAI_Query_DroppedFile::from_url( $url, $purposeType, $mimeType );
          } else {
            // For PDFs and documents, use refId so engines can access file data directly
            $droppedFile = Meow_MWAI_Query_DroppedFile::from_refId( $internalRefId, $purposeType, $mimeType );
          }

          // IMPORTANT: Always use add_file() to add to attachedFiles array
          // This is the unified approach for both single and multi-file uploads
          $query->add_file( $droppedFile );

          // Update metadata using the internal refId
          $fileId = $this->core->files->get_id_from_refId( $internalRefId );
          $this->core->files->update_envId( $fileId, $engine->envId );
          $this->core->files->update_purpose( $fileId, $purposeType );
          $this->core->files->add_metadata( $fileId, 'query_envId', $engine->envId );
          $this->core->files->add_metadata( $fileId, 'query_session', $query->session );
        }
      }

      // 7) Attach the 'fields' as an extra param for your own usage
      $query->setExtraParam( 'fields', $fields );

      // 8) Handle streaming callback
      $streamCallback = null;
      if ( $stream ) {
        $streamCallback = function ( $reply ) use ( $query ) {
          // Support both legacy string data and new Event objects
          if ( is_string( $reply ) ) {
            $this->core->stream_push( [ 'type' => 'live', 'data' => $reply ], $query );
          }
          else {
            $this->core->stream_push( $reply, $query );
          }
        };
        header( 'Cache-Control: no-cache' );
        header( 'Content-Type: text/event-stream' );
        header( 'X-Accel-Buffering: no' );
        ob_implicit_flush( true );
        ob_end_flush();
      }

      // 9) Possible takeover
      $takeoverAnswer = apply_filters( 'mwai_form_takeover', null, $query, $params );
      if ( !empty( $takeoverAnswer ) ) {
        return $this->create_rest_response( [
          'success' => true,
          'reply' => $takeoverAnswer,
          'images' => null,
          'usage' => null
        ], 200 );
      }

      // 10) Finally, query the AI
      $reply = $this->core->run_query( $query, $streamCallback, true );
      $rawText = $reply->result;
      $extra = [];
      if ( $context && isset( $context['embeddings'] ) ) {
        $extra = [ 'embeddings' => $context['embeddings'] ];
      }
      $rawText = apply_filters( 'mwai_form_reply', $rawText, $query, $params, $extra );

      $restRes = [
        'success' => true,
        'reply' => $rawText,
        'images' => $reply->get_type() === 'images' ? $reply->results : null,
        'usage' => $reply->usage
      ];

      if ( $stream ) {
        $this->core->stream_push( [ 'type' => 'end', 'data' => json_encode( $restRes ) ], $query );
        die();
      }
      else {
        return $this->create_rest_response( $restRes, 200 );
      }
    }
    catch ( Exception $e ) {
      $message = apply_filters( 'mwai_ai_exception', $e->getMessage() );
      if ( $stream ) {
        $this->core->stream_push( [ 'type' => 'error', 'data' => $message ], $query );
      }
      else {
        return $this->create_rest_response( [ 'success' => false, 'message' => $message ], 500 );
      }
    }
  }

  // Rename the keys of the atts into camelCase to match the internal params system.
  public function keys_to_camel_case( $atts ) {
    $atts = array_map( function ( $key, $value ) {
      $key = str_replace( '_', ' ', $key );
      $key = ucwords( $key );
      $key = str_replace( ' ', '', $key );
      $key = lcfirst( $key );
      return [ $key => $value ];
    }, array_keys( $atts ), $atts );
    $atts = array_merge( ...$atts );
    return $atts;
  }

  public function fetch_front_params( $atts ) {
    $frontParams = [];
    foreach ( MWAI_FORMS_FRONT_PARAMS as $param ) {
      if ( isset( $atts[$param] ) ) {
        $frontParams[$param] = $atts[$param];
      }
    }
    $frontParams = $this->clean_params( $frontParams );
    return $frontParams;
  }

  public function fetch_server_params( $atts ) {
    $serverParams = [];
    foreach ( MWAI_FORMS_SERVER_PARAMS as $param ) {
      if ( isset( $atts[$param] ) ) {
        $serverParams[$param] = $atts[$param];
        if ( $param === 'message' ) {
          $serverParams[$param] = urldecode( $serverParams[$param] );
        }
        // Handle mcpServers JSON string (URL decode first, then JSON decode)
        if ( $param === 'mcpServers' && is_string( $serverParams[$param] ) ) {
          $serverParams[$param] = urldecode( $serverParams[$param] );
          $decoded = json_decode( $serverParams[$param], true );
          if ( json_last_error() === JSON_ERROR_NONE ) {
            $serverParams[$param] = $decoded;
          }
        }
      }
    }
    $serverParams = $this->clean_params( $serverParams );
    return $serverParams;
  }

  public function encore_params_for_html( $params ) {
    $params = htmlspecialchars( json_encode( $params ), ENT_QUOTES, 'UTF-8' );
    return $params;
  }

  public function shortcode_mwai_form_upload( $atts ) {
    $atts = apply_filters( 'mwai_forms_upload_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );

    // If you want to handle server-side params for the upload field (e.g., store them),
    // you can do so here by calling $this->fetch_server_params( $atts ), similar to
    // how you do in shortcode_mwai_form_submit(). But most often for a simple file input,
    // front-end usage is enough.

    // If you support a custom theme, handle it like your other blocks
    $theme = isset( $frontParams['themeId'] )
        ? $this->core->get_theme( $frontParams['themeId'] )
            : null;

    // Encode as JSON for your forms.js or React code
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    // Ensure the forms.js script is loaded
    $this->enqueue_scripts( $frontParams['themeId'] ?? null );

    // Return a simple container div. JS will take that data-params and render <FormUpload />
    return "<div class='mwai-form-upload-container'
                                                                                                                                                                                                                                                                                                                                                                                                      data-params='{$jsonFrontParams}'
                                                                                                                                                                                                                                                                                                                                                                                                      data-theme='{$jsonFrontTheme}'></div>";
  }

  // Based on the id, label, type, name and options, it will return the HTML code for the field.
  public function shortcode_mwai_form_field( $atts ) {
    $atts = apply_filters( 'mwai_forms_field_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );

    // Client-side: Prepare JSON for Front Params and System Params
    $theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts( $frontParams['themeId'] ?? null );
    return "<div class='mwai-form-field-container' data-params='{$jsonFrontParams}'
                                                                                                                                                                                                                                                                                                                                                                                                                            data-theme='{$jsonFrontTheme}'></div>";
  }

  public function shortcode_mwai_form_submit( $atts ) {
    $id = 'mwai-' . uniqid();
    $atts = apply_filters( 'mwai_form_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );
    $systemParams = $this->fetch_system_params( $id ); // Overridable by $atts later
    $serverParams = $this->fetch_server_params( $atts );

    // Extract the fields and selectors from the message, and build the inputs object.
    $message = isset( $serverParams['message'] ) ? $serverParams['message'] : '';
    $inputs = [ 'fields' => [], 'selectors' => [] ];
    $matches = [];
    preg_match_all( '/{([A-Za-z0-9_-]+)}/', $message, $matches );
    foreach ( $matches[1] as $match ) {
      $inputs['fields'][] = $match;
    }
    $matches = [];
    preg_match_all( '/\$\{([^}]+)\}/', $message, $matches );
    foreach ( $matches[1] as $match ) {
      $inputs['selectors'][] = $match;
    }
    $frontParams['inputs'] = $inputs;

    // Server-side: Keep the System Params
    if ( count( $serverParams ) > 0 ) {
      $id = md5( json_encode( $serverParams ) );
      $systemParams['id'] = $id;
      $systemParams['inputs'] = $inputs;
      set_transient( 'mwai_custom_form_' . $id, $serverParams, 60 * 60 * 24 );
    }

    // Client-side: Prepare JSON for Front Params and System Params
    $theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontSystem = $this->encore_params_for_html( $systemParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts( $frontParams['themeId'] ?? null );
    return "<div class='mwai-form-submit-container'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        data-params='{$jsonFrontParams}' data-system='{$jsonFrontSystem}'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        data-theme='{$jsonFrontTheme}'>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        </div>";
  }

  public function shortcode_mwai_form_reset( $atts ) {
    // Generate a unique ID for this reset block, if not provided
    $resetId = 'mwai-reset-' . uniqid();

    // Let plugins/themes modify the atts if needed
    $atts = apply_filters( 'mwai_form_reset_params', $atts );

    // Convert keys like "local_memory" => "localMemory"
    $atts = $this->keys_to_camel_case( $atts );

    // For front-end display/usage
    $frontParams = $this->fetch_front_params( $atts );

    // For system usage. We'll default to using the same unique $resetId for both
    $systemParams = $this->fetch_system_params( $resetId, $resetId );

    // If you have any special serverParams to parse, do that:
    // (Likely not needed for a reset button, but you can adapt as needed)
    $serverParams = $this->fetch_server_params( $atts );

    // If you want to set a stable `id` in $systemParams:
    $systemParams['id'] = md5( json_encode( $serverParams ) );
    $systemParams['resetId'] = $resetId;

    // If you do NOT need to store anything server side, you can skip set_transient().
    // But here's an example if you do:
    // set_transient( 'mwai_custom_reset_' . $systemParams['id'], $serverParams, 60 * 60 * 24 );

    // Prepare JSON for front & system usage
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontSystem = $this->encore_params_for_html( $systemParams );

    // Enqueue your needed scripts/styles
    $this->enqueue_scripts( $frontParams['themeId'] ?? null );

    // Return a container with data attributes, similarly to your submit function
    return "<div class='mwai-form-reset-container'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    data-params='{$jsonFrontParams}' data-system='{$jsonFrontSystem}'>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </div>";
  }

  public function shortcode_mwai_form_output( $atts ) {
    $atts = apply_filters( 'mwai_forms_output_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );

    // Client-side: Prepare JSON for Front Params and System Params
    $theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts( $frontParams['themeId'] ?? null );
    return "<div class='mwai-form-output-container' data-params='{$jsonFrontParams}'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        data-theme='{$jsonFrontTheme}'></div>";
  }

  public function shortcode_mwai_form_conditional( $atts ) {
    $atts = $this->keys_to_camel_case( $atts );
    if ( empty( $atts['conditions'] ) && ( !empty( $atts['conditionField'] ) || !empty( $atts['conditionValue'] ) ) ) {
      $field = $atts['conditionField'] ?? '';
      $value = $atts['conditionValue'] ?? '';
      $atts['conditions'] = rawurlencode( json_encode( [ [ 'field' => $field, 'operator' => 'eq', 'value' => $value ] ] ) );
    }
    $frontParams = $this->fetch_front_params( $atts );
    $systemParams = $this->fetch_system_params( $frontParams['id'] ?? uniqid() );

    $theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;

    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontSystem = $this->encore_params_for_html( $systemParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts( $frontParams['themeId'] ?? null );

    return "<div class='mwai-form-conditional-container' data-params='{$jsonFrontParams}' data-system='{$jsonFrontSystem}' data-theme='{$jsonFrontTheme}'></div>";
  }

  public function shortcode_mwai_form_container( $atts ) {
    // This shortcode is kept for backward compatibility
    // New blocks output HTML directly and don't use this shortcode
    $theme = strtolower( $atts['theme'] );
    $this->core->enqueue_theme( $theme );
    $id = empty( $atts['id'] ) ? uniqid() : $atts['id'];
    
    // For backward compatibility, we still apply the theme via JavaScript
    // This handles cases where old content still uses the shortcode
    $style_content = "<script>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              document.addEventListener('DOMContentLoaded', function() {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    var containers = document.querySelectorAll('#mwai-form-container-{$id}');
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      if ( containers.length > 1 ) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          console.warn('Multiple form containers found.', { id: '{$id}' });
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          if ( containers.length === 0 ) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              console.warn('Form container not found.', { id: '{$id}' });
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              else {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                for ( var i = 0; i < containers.length; i++ ) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    var container = containers[i];
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      container.classList.add('mwai-" . $theme . "-theme');
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              });
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          </script>";
    $style_content = apply_filters( 'mwai_forms_style', $style_content, $id );
    return $style_content;
  }
}
