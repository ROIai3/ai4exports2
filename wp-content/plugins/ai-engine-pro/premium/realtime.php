<?php

class MeowPro_MWAI_Realtime {
  private $core = null;
  private $namespace = 'mwai-ui/v1';

  public function __construct( $core ) {
    $this->core = $core;
    add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
  }

  /**
  * Initialize REST API routes for real-time OpenAI interactions.
  */
  public function rest_api_init() {
    register_rest_route( $this->namespace, '/openai/realtime/start', [
      'methods' => 'POST',
      'permission_callback' => [ $this->core, 'check_rest_nonce' ],
      'callback' => [ $this, 'rest_realtime_start' ],
    ] );

    register_rest_route( $this->namespace, '/openai/realtime/call', [
      'methods' => 'POST',
      'permission_callback' => [ $this->core, 'check_rest_nonce' ],
      'callback' => [ $this, 'rest_realtime_call' ],
    ] );

    register_rest_route( $this->namespace, '/openai/realtime/stats', [
      'methods' => 'POST',
      'permission_callback' => [ $this->core, 'check_rest_nonce' ],
      'callback' => [ $this, 'rest_realtime_stats' ],
    ] );

    register_rest_route( $this->namespace, '/openai/realtime/discussions', [
      'methods' => 'POST',
      'permission_callback' => [ $this->core, 'check_rest_nonce' ],
      'callback' => [ $this, 'rest_realtime_discussions' ],
    ] );

    register_rest_route( $this->namespace, '/openai/realtime/image', [
      'methods' => 'POST',
      'permission_callback' => [ $this->core, 'check_rest_nonce' ],
      'callback' => [ $this, 'rest_realtime_image' ],
    ] );
  }

  /**
  * Find Azure deployment name for a given model
  */
  private function find_azure_deployment( $env, $model ) {
    if ( !isset( $env['deployments'] ) || !is_array( $env['deployments'] ) ) {
      return null;
    }

    foreach ( $env['deployments'] as $deployment ) {
      if ( $deployment['model'] === $model && !empty( $deployment['name'] ) ) {
        return trim( $deployment['name'] );
      }
    }

    return null;
  }

  /**
  * Extract Azure region from endpoint URL
  */
  private function extract_azure_region( $endpoint ) {
    // Check if region is explicitly set in environment
    // (This would need to be added as a field in the future)

    // Try to extract from region-based endpoint format
    if ( preg_match( '/([a-z0-9]+)\.api\.cognitive\.microsoft\.com/', $endpoint, $matches ) ) {
      return $matches[1];
    }

    // For custom subdomain endpoints (.openai.azure.com), default to eastus2
    if ( preg_match( '/\.openai\.azure\.com/', $endpoint ) ) {
      return 'eastus2';
    }

    // Fallback default
    return 'eastus2';
  }

  /**
  * Build Azure WebRTC realtime URL
  */
  private function build_azure_realtime_url( $env, $model ) {
    $endpoint = isset( $env['endpoint'] ) ? rtrim( $env['endpoint'], '/' ) : null;
    if ( empty( $endpoint ) ) {
      return null;
    }

    // Extract region from endpoint
    $region = $this->extract_azure_region( $endpoint );

    // Find deployment for this model
    $deployment_name = $this->find_azure_deployment( $env, $model );
    if ( !$deployment_name ) {
      return null;
    }

    // Azure WebRTC URL format: https://{region}.realtimeapi-preview.ai.azure.com/v1/realtimertc?model={deployment}
    return "https://{$region}.realtimeapi-preview.ai.azure.com/v1/realtimertc?model=" . urlencode( $deployment_name );
  }

  /**
  * Check if an array is associative.
  */
  public function isAssoc( array $arr ): bool {
    return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
  }

  /**
  * Format a float to a string with reduced trailing zeros.
  */
  public function formatFloat( float $num ): string {
    $formatted = sprintf( '%.2f', $num );
    $formatted = rtrim( $formatted, '0' );
    return rtrim( $formatted, '.' );
  }

  /**
  * Custom JSON encoding to handle special cases like floats and associative arrays.
  */
  public function custom_json_encode( $value ): string {
    if ( $value === null ) {
      return 'null';
    }
    if ( is_bool( $value ) ) {
      return $value ? 'true' : 'false';
    }
    if ( is_float( $value ) ) {
      return $this->formatFloat( $value );
    }
    if ( is_int( $value ) ) {
      return (string) $value;
    }
    if ( is_string( $value ) ) {
      return '"' . str_replace( [ '\\', '"', "\n", "\r", "\t" ], [ '\\\\', '\\"', '\\n', '\\r', '\\t' ], $value ) . '"';
    }
    if ( is_array( $value ) || is_object( $value ) ) {
      $value = is_object( $value ) ? (array) $value : $value;
      if ( $this->isAssoc( $value ) ) {
        $pairs = [];
        foreach ( $value as $k => $v ) {
          $pairs[] = '"' . str_replace( [ '\\', '"', "\n", "\r", "\t" ], [ '\\\\', '\\"', '\\n', '\\r', '\\t' ], (string) $k ) . '":' . $this->custom_json_encode( $v );
        }
        return '{' . implode( ',', $pairs ) . '}';
      }
      else {
        $elements = array_map( [ $this, 'custom_json_encode' ], $value );
        return '[' . implode( ',', $elements ) . ']';
      }
    }
    return '"' . str_replace( [ '\\', '"', "\n", "\r", "\t" ], [ '\\\\', '\\"', '\\n', '\\r', '\\t' ], (string) $value ) . '"';
  }

  /**
  * Start a real-time OpenAI session.
  */
  public function rest_realtime_start( WP_REST_Request $request ) {
    try {
      $botId = $request->get_param( 'botId' );
      $customId = $request->get_param( 'customId' );

      if ( empty( $botId ) && empty( $customId ) ) {
        throw new Exception( 'Missing botId.' );
      }

      $bot = null;
      if ( !empty( $customId ) ) {
        $bot = get_transient( 'mwai_custom_chatbot_' . $customId );
      }
      if ( !$bot && !empty( $botId ) ) {
        $bot = $this->core->get_chatbot( $botId );
      }
      if ( empty( $bot ) ) {
        throw new Exception( 'Chatbot not found.' );
      }

      // Check if the user is allowed to start a realtime session (limits check)
      $limits = $this->core->get_option( 'limits' );
      $mockQuery = new Meow_MWAI_Query_Text( 'Realtime session check' );
      $mockQuery->scope = 'chatbot';
      $mockQuery->feature = 'realtime';
      $allowed = apply_filters( 'mwai_ai_allowed', true, $mockQuery, $limits );
      if ( $allowed !== true ) {
        $message = is_string( $allowed ) ? $allowed : 'You have reached your usage limit.';
        throw new Exception( $message );
      }

      $envId = !empty( $bot['envId'] ) ? $bot['envId'] : null;
      
      // If no envId is specified, try to use the default environment
      if ( empty( $envId ) ) {
        $defaultEnv = $this->core->get_option( 'ai_default_env' );
        if ( !empty( $defaultEnv ) ) {
          $envId = $defaultEnv;
        } else {
          throw new Exception( 'No environment ID found for this bot. Please select a specific AI environment in the chatbot settings.' );
        }
      }

      $model = $bot['model'];
      $voice = !empty( $bot['voice'] ) ? $bot['voice'] : null;
      $maxTokens = (int) $bot['maxTokens'] ?? 2048;
      $instructions = $bot['instructions'];
      $temperature = (float) $bot['temperature'] ?? 0.8;
      if ( $temperature < 0.6 || $temperature > 1.2 ) {
        $temperature = 0.8;
      }

      // Get talkMode from request
      $talkMode = $request->get_param( 'talkMode' );
      if ( empty( $talkMode ) ) {
        $talkMode = 'hands-free';
      }

      // Check if file upload is enabled in bot settings AND model supports it
      $visionEnabled = !empty( $bot['fileUpload'] ) && $bot['fileUpload'] === true;
      // Check if model supports vision - be more inclusive with model names
      $modelSupportsVision = ( 
        $model === 'gpt-realtime' || 
        strpos( $model, 'gpt-realtime' ) !== false || 
        strpos( $model, 'gpt-4o' ) !== false ||
        strpos( $model, 'gpt-4o-realtime' ) !== false ||
        $model === 'gpt-4o-realtime-preview' ||
        $model === 'gpt-4o-realtime-preview-2024-10-01'
      );
      $supportsVision = $visionEnabled && $modelSupportsVision;
      
      $body = [
        'model' => $model,
        'modalities' => [ 'text', 'audio' ],
        'instructions' => $instructions,
        'temperature' => round( $temperature, 2 ),
        'input_audio_transcription' => [ 'model' => 'whisper-1' ],
        'max_response_output_tokens' => $maxTokens,
      ];

      // Configure turn detection - keep VAD enabled for both modes
      // In hold-to-talk, we control input via microphone muting instead
      $body['turn_detection'] = [
        'type' => 'server_vad',
        'threshold' => 0.5,
        'prefix_padding_ms' => 300,
        'silence_duration_ms' => 200,
      ];
      
      // Note: Image modality is handled via conversation items, not session modalities

      if ( !empty( $voice ) ) {
        $body['voice'] = $voice;
      }

      $toolsArr = [];
      $callbacks = [];
      if ( !empty( $bot['functions'] ) && is_array( $bot['functions'] ) ) {
        foreach ( $bot['functions'] as $funcDef ) {
          $funcObj = MeowPro_MWAI_FunctionAware::get_function( $funcDef['type'], $funcDef['id'] );
          if ( $funcObj ) {
            $serialized = $funcObj->serializeForOpenAI();

            // Realtime API: if there are no parameters, remove the parameters field entirely
            // Check if properties is stdClass (empty object) or has no elements
            $hasNoProperties = false;
            if ( isset( $serialized['parameters']['properties'] ) ) {
              $props = $serialized['parameters']['properties'];
              $hasNoProperties = ( $props instanceof stdClass && empty( (array) $props ) ) ||
                                 ( is_array( $props ) && empty( $props ) );
            }

            if ( $hasNoProperties && empty( $serialized['parameters']['required'] ) ) {
              unset( $serialized['parameters'] );
            } else if ( isset( $serialized['parameters']['properties'] ) ) {
              // Convert properties array to object for non-empty parameters
              if ( !( $serialized['parameters']['properties'] instanceof stdClass ) ) {
                $serialized['parameters']['properties'] = (object) $serialized['parameters']['properties'];
              }
            }

            // Realtime API expects flat structure with type + function fields at same level
            $toolsArr[] = array_merge( [ 'type' => 'function' ], $serialized );
            $callbacks[] = [
              'name' => $serialized['name'],
              'id' => $funcDef['id'],
              'type' => $funcDef['type'],
              'target' => $funcObj->target,
            ];
          }
        }
      }

      if ( !empty( $toolsArr ) ) {
        $body['tools'] = $toolsArr;
        $body['tool_choice'] = 'auto';
      }

      $jsonBody = $this->custom_json_encode( $body );
      $openai = Meow_MWAI_Engines_Factory::get_openai( $this->core, $envId );
      $res = $openai->execute( 'POST', '/realtime/sessions', $jsonBody, null, true );

      // Determine the realtime URL based on environment type
      $env = $this->core->get_ai_env( $envId );
      $realtimeUrl = 'https://api.openai.com/v1/realtime'; // Default OpenAI URL

      if ( !empty( $env ) && $env['type'] === 'azure' ) {
        // For Azure, construct the WebRTC URL using the region-specific domain
        $realtimeUrl = $this->build_azure_realtime_url( $env, $model );
      }

      return new WP_REST_Response( [
        'success' => true,
        'session_id' => $res['id'],
        'model' => $res['model'],
        'client_secret' => $res['client_secret']['value'],
        'client_secret_expires_at' => $res['client_secret']['expires_at'],
        'function_callbacks' => $callbacks,
        'supports_vision' => $supportsVision,
        'realtime_url' => $realtimeUrl
      ], 200 );
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Realtime Start error: ' . $e->getMessage() );
      return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
    }
  }

  /**
  * Handle real-time OpenAI function calls.
  */
  public function rest_realtime_call( WP_REST_Request $request ) {
    try {
      $functionId = $request->get_param( 'functionId' );
      $functionType = $request->get_param( 'functionType' );
      $functionName = $request->get_param( 'functionName' );
      $args = $request->get_param( 'arguments' );

      if ( empty( $functionId ) || empty( $functionType ) || empty( $functionName ) ) {
        throw new Exception( 'Missing function metadata (functionId, functionType, functionName).' );
      }

      $func = MeowPro_MWAI_FunctionAware::get_function( $functionType, $functionId );
      if ( !$func ) {
        throw new Exception( "Function {$functionId} not found or not registered." );
      }

      $value = apply_filters( 'mwai_ai_feedback', null, [
        'toolId' => null,
        'type' => 'tool_call',
        'name' => $functionName,
        'arguments' => $args,
        'function' => $func,
      ], $args );

      return new WP_REST_Response( [ 'success' => true, 'data' => $value ], 200 );
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Realtime Call error: ' . $e->getMessage() );
      return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
    }
  }

  public function rest_realtime_stats( WP_REST_Request $request ) {
    try {
      $botId = $request->get_param( 'botId' );
      $customId = $request->get_param( 'customId' );
      if ( empty( $botId ) && empty( $customId ) ) {
        throw new Exception( 'Missing botId.' );
      }
      $bot = null;
      if ( !empty( $customId ) ) {
        $bot = get_transient( 'mwai_custom_chatbot_' . $customId );
      }
      if ( !$bot && !empty( $botId ) ) {
        $bot = $this->core->get_chatbot( $botId );
      }
      if ( empty( $bot ) ) {
        throw new Exception( 'Chatbot not found.' );
      }
      $envId = !empty( $bot['envId'] ) ? $bot['envId'] : null;
      
      // If no envId is specified, try to use the default environment
      if ( empty( $envId ) ) {
        $defaultEnv = $this->core->get_option( 'ai_default_env' );
        if ( !empty( $defaultEnv ) ) {
          $envId = $defaultEnv;
        } else {
          throw new Exception( 'No environment ID found for this bot. Please select a specific AI environment in the chatbot settings.' );
        }
      }
      $model = $bot['model'];
      $scope = $bot['scope'];
      $session = $request->get_param( 'session' );
      $refId = $request->get_param( 'refId' );

      // The stats array that includes all tokens, audio, text, etc.
      $statsData = $request->get_param( 'stats' );
      if ( !is_array( $statsData ) ) {
        throw new Exception( 'No valid stats array provided.' );
      }

      // Build the MeowPro_MWAI_Stats object
      $statsObject = new MeowPro_MWAI_Stats();
      $statsObject->model = $model;
      $statsObject->envId = $envId;
      $statsObject->scope = $scope;
      $statsObject->feature = 'realtime';
      $statsObject->session = $session;
      $statsObject->refId = $refId;
      $statsObject->stats = $statsData;

      global $mwai_stats;
      $success = $mwai_stats->commit_stats_from_realtime( $statsObject );
      if ( !$success ) {
        throw new Exception( 'Could not commit realtime stats.' );
      }
      
      // Additionally, log each exchange as a separate query if configured
      $limits = $this->core->get_option( 'limits' );
      $countRealtimeExchanges = apply_filters( 'mwai_realtime_count_exchanges', false, $limits );
      
      if ( $countRealtimeExchanges && !empty( $statsData['exchange_count'] ) ) {
        // Create a new log entry for this exchange
        $exchangeStats = new MeowPro_MWAI_Stats();
        $exchangeStats->model = $model;
        $exchangeStats->envId = $envId;
        $exchangeStats->scope = $scope;
        $exchangeStats->feature = 'realtime';
        $exchangeStats->session = $session;
        $exchangeStats->type = 'queries';
        $exchangeStats->units = 1; // Count as 1 query
        $exchangeStats->price = 0; // Price is already tracked in the main stats
        $exchangeStats->stats = [
          'event' => 'exchange',
          'exchange_number' => $statsData['exchange_count']
        ];
        
        $mwai_stats->commit_stats( $exchangeStats );
      }

      // Check if the user has exceeded limits after this usage
      $limits = $this->core->get_option( 'limits' );
      $overLimit = false;
      $limitMessage = null;
      
      if ( $limits && isset( $limits['enabled'] ) && $limits['enabled'] ) {
        // Create a mock query to check limits
        $mockQuery = new Meow_MWAI_Query_Text( 'Realtime usage check' );
        $mockQuery->scope = $scope;
        $mockQuery->feature = 'realtime';
        $mockQuery->session = $session;
        
        $allowed = apply_filters( 'mwai_ai_allowed', true, $mockQuery, $limits );
        if ( $allowed !== true ) {
          $overLimit = true;
          $limitMessage = is_string( $allowed ) ? $allowed : 'You have reached your usage limit.';
        }
      }

      return new WP_REST_Response( [ 
        'success' => true, 
        'message' => 'Stats committed successfully.',
        'overLimit' => $overLimit,
        'limitMessage' => $limitMessage
      ], 200 );
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Realtime Stats error: ' . $e->getMessage() );
      return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
    }
  }

  public function rest_realtime_discussions( WP_REST_Request $request ) {
    try {
      $botId = $request->get_param( 'botId' );
      $customId = $request->get_param( 'customId' );
      $chatId = $request->get_param( 'chatId' );
      $messages = $request->get_param( 'messages' );

      // Basic checks
      if ( empty( $chatId ) ) {
        throw new Exception( 'Missing chatId.' );
      }
      if ( empty( $botId ) && empty( $customId ) ) {
        throw new Exception( 'Missing botId.' );
      }
      if ( !is_array( $messages ) ) {
        throw new Exception( 'messages must be an array.' );
      }
      $bot = null;
      if ( !empty( $customId ) ) {
        $bot = get_transient( 'mwai_custom_chatbot_' . $customId );
      }
      if ( !$bot && !empty( $botId ) ) {
        $bot = $this->core->get_chatbot( $botId );
      }
      if ( empty( $bot ) ) {
        throw new Exception( 'Chatbot not found.' );
      }

      $discussion = new Meow_MWAI_Discussion();
      $discussion->chatId = $chatId;
      $discussion->botId = $botId ?: $customId;
      $discussion->messages = $messages;
      $discussion->extra = [
        'model' => $bot['model'],
        'temperature' => $bot['temperature'],
        'session' => $request->get_param( 'session' ),
      ];

      // If you track the user ID, set it:
      // $discussion->userId = get_current_user_id() ?: null;

      // 4. Commit the discussion
      if ( empty( $this->core->discussions ) ) {
        return new WP_REST_Response( [ 'success' => true, 'message' => 'Discussions module is not enabled.' ], 200 );
      }
      $ok = $this->core->discussions->commit_discussion( $discussion );
      if ( !$ok ) {
        throw new Exception( 'Could not commit the discussion to DB.' );
      }
      return new WP_REST_Response( [ 'success' => true, 'message' => 'Discussion committed successfully.' ], 200 );

    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Realtime Discussions error: ' . $e->getMessage() );
      return new WP_REST_Response( [  'success' => false,  'message' => $e->getMessage() ], 500 );
    }
  }

  /**
   * Handle image upload for real-time session.
   * This endpoint receives an image from the client and sends it to the OpenAI Realtime API.
   */
  public function rest_realtime_image( WP_REST_Request $request ) {
    try {
      $sessionId = $request->get_param( 'sessionId' );
      $imageData = $request->get_param( 'imageData' );
      $botId = $request->get_param( 'botId' );
      $customId = $request->get_param( 'customId' );
      
      if ( empty( $sessionId ) ) {
        throw new Exception( 'Missing sessionId.' );
      }
      
      if ( empty( $imageData ) ) {
        throw new Exception( 'Missing imageData.' );
      }
      
      // Get bot configuration
      $bot = null;
      if ( !empty( $customId ) ) {
        $bot = get_transient( 'mwai_custom_chatbot_' . $customId );
      }
      if ( !$bot && !empty( $botId ) ) {
        $bot = $this->core->get_chatbot( $botId );
      }
      if ( empty( $bot ) ) {
        throw new Exception( 'Chatbot not found.' );
      }
      
      $envId = !empty( $bot['envId'] ) ? $bot['envId'] : null;
      if ( empty( $envId ) ) {
        $defaultEnv = $this->core->get_option( 'ai_default_env' );
        if ( !empty( $defaultEnv ) ) {
          $envId = $defaultEnv;
        }
      }
      
      // Remove data URL prefix if present
      if ( strpos( $imageData, 'data:' ) === 0 ) {
        $imageData = substr( $imageData, strpos( $imageData, ',' ) + 1 );
      }
      
      // TODO: In the future, we could:
      // 1. Validate image size and format
      // 2. Compress/resize large images
      // 3. Store image temporarily for retry logic
      
      // For now, we'll relay the instruction to send the image via the data channel
      // Since the WebRTC connection is direct browser-to-OpenAI, we can't inject messages server-side
      // The best we can do is validate and preprocess the image before sending it back to the client
      
      // Validate base64 format
      if ( !base64_decode( $imageData, true ) ) {
        throw new Exception( 'Invalid base64 image data.' );
      }
      
      // Check size (max 20MB for base64)
      $maxSize = 20 * 1024 * 1024;
      if ( strlen( $imageData ) > $maxSize ) {
        throw new Exception( 'Image size exceeds 20MB limit.' );
      }
      
      // Return processed image for client to send
      return new WP_REST_Response( [
        'success' => true,
        'imageData' => $imageData,
        'message' => 'Image validated and ready to send.'
      ], 200 );
      
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Realtime Image error: ' . $e->getMessage() );
      return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
    }
  }

}
