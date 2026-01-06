<?php

class MeowPro_MWAI_Addons_OaiVectorStore {
  private $core = null;

  // Current Vector DB
  private $env = null;
  private $openai_env_id = null;
  private $openai_env = null;
  private $apiKey = null;
  private $store_id = null;
  private $maxSelect = 10;

  public function __construct() {
    global $mwai_core;
    $this->core = $mwai_core;
    $this->init_settings();

    add_filter( 'mwai_embeddings_list_vectors', [ $this, 'list_vectors' ], 10, 2 );
    add_filter( 'mwai_embeddings_add_vector', [ $this, 'add_vector' ], 10, 3 );
    add_filter( 'mwai_embeddings_get_vector', [ $this, 'get_vector' ], 10, 4 );
    add_filter( 'mwai_embeddings_query_vectors', [ $this, 'query_vectors' ], 10, 4 );
    add_filter( 'mwai_embeddings_delete_vectors', [ $this, 'delete_vectors' ], 10, 2 );
  }

  public function init_settings( $envId = null ) {
    $envId = $envId ?? $this->core->get_option( 'embeddings_env' );
    $this->env = $this->core->get_embeddings_env( $envId );

    // This class only handles OpenAI Vector Store.
    if ( empty( $this->env ) || $this->env['type'] !== 'openai-vector-store' ) {
      return false;
    }

    $this->openai_env_id = isset( $this->env['openai_env_id'] ) ? $this->env['openai_env_id'] : null;
    $this->store_id = isset( $this->env['store_id'] ) ? $this->env['store_id'] : null;
    $this->maxSelect = isset( $this->env['max_select'] ) ? (int) $this->env['max_select'] : 10;

    // Validate store_id
    if ( empty( $this->store_id ) ) {
      Meow_MWAI_Logging::error( 'OpenAI Vector Store ID is not configured. Please set the Vector Store ID in the embeddings environment settings.' );
      $this->env = null; // Disable this environment
      return false;
    }
    
    // Ensure store_id starts with 'vs_'
    if ( strpos( $this->store_id, 'vs_' ) !== 0 ) {
      Meow_MWAI_Logging::error( "Invalid Vector Store ID: '{$this->store_id}'. OpenAI Vector Store IDs must start with 'vs_'." );
      $this->env = null; // Disable this environment
      return false;
    }

    // Get the OpenAI environment to retrieve the API key
    if ( !empty( $this->openai_env_id ) ) {
      $this->openai_env = $this->core->get_ai_env( $this->openai_env_id );
      if ( $this->openai_env && isset( $this->openai_env['apikey'] ) ) {
        $this->apiKey = $this->openai_env['apikey'];
      }
    }

    return true;
  }

  // Generic function to run a request to OpenAI.
  public function run( $method, $url, $query = null, $json = true ) {
    if ( empty( $this->apiKey ) ) {
      throw new Exception( 'OpenAI API key not found. Please configure the OpenAI environment.' );
    }

    $headers = "accept: application/json, charset=utf-8\r\ncontent-type: application/json\r\n" .
      'Authorization: Bearer ' . $this->apiKey . "\r\n" .
      'OpenAI-Beta: assistants=v2' . "\r\n";
    
    $body = $query ? json_encode( $query ) : null;
    $url = 'https://api.openai.com/v1' . $url;
    $url = untrailingslashit( esc_url_raw( $url ) );
    
    $options = [
      'headers' => $headers,
      'method' => $method,
      'timeout' => MWAI_TIMEOUT,
      'body' => $body,
      'sslverify' => false
    ];

    try {
      $response = wp_remote_request( $url, $options );
      if ( is_wp_error( $response ) ) {
        throw new Exception( $response->get_error_message() );
      }
      $response = wp_remote_retrieve_body( $response );
      $data = $response === '' ? true : ( $json ? json_decode( $response, true ) : $response );
      if ( !is_array( $data ) && empty( $data ) && is_string( $response ) ) {
        throw new Exception( $response );
      }
      if ( isset( $data['error'] ) ) {
        throw new Exception( $data['error']['message'] ?? 'Unknown OpenAI error' );
      }
      return $data;
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'OpenAI Vector Store: ' . $e->getMessage() );
      throw new Exception( $e->getMessage() . ' (OpenAI Vector Store)' );
    }
    return [];
  }

  // List all vectors from OpenAI Vector Store.
  public function list_vectors( $vectors, $options ) {
    if ( !empty( $vectors ) ) {
      return $vectors;
    }
    $envId = $options['envId'];
    $limit = $options['limit'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }

    // OpenAI Vector Store doesn't provide a direct way to list vector IDs like Pinecone
    // We'll need to list files in the vector store instead
    $res = $this->run( 'GET', "/vector_stores/{$this->store_id}/files?limit={$limit}" );
    
    if ( isset( $res['data'] ) ) {
      $vectors = array_map( function ( $file ) { 
        return $file['id']; 
      }, $res['data'] );
    }
    
    return $vectors;
  }

  // Delete vectors from OpenAI Vector Store.
  public function delete_vectors( $success, $options ) {
    // Already handled.
    if ( $success ) {
      return $success;
    }
    $envId = $options['envId'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    $ids = $options['ids'];
    $deleteAll = $options['deleteAll'];

    if ( $deleteAll ) {
      // OpenAI doesn't support deleting all files at once
      // We would need to list all files and delete them individually
      throw new Exception( 'Delete all is not supported for OpenAI Vector Store. Please delete files individually.' );
    }

    // Ensure $ids is an array
    if ( !is_array( $ids ) ) {
      $ids = [ $ids ];
    }

    // Delete individual files
    foreach ( $ids as $fileId ) {
      try {
        $this->run( 'DELETE', "/vector_stores/{$this->store_id}/files/{$fileId}" );
      } catch ( Exception $e ) {
        // Log the error but continue with other deletions
        Meow_MWAI_Logging::error( "Failed to delete file {$fileId}: " . $e->getMessage() );
      }
    }
    
    return true;
  }

  // Add a vector to OpenAI Vector Store.
  public function add_vector( $success, $vector, $options ) {
    if ( $success ) {
      return $success;
    }
    $envId = $options['envId'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }

    // Create a temporary file with the content
    $content = "Title: {$vector['title']}\n\n{$vector['content']}";
    $temp_file = tempnam( sys_get_temp_dir(), 'mwai_vector_' );
    file_put_contents( $temp_file, $content );

    try {
      // First, upload the file to OpenAI
      $file_upload_response = $this->upload_file( $temp_file, $vector['title'] );
      $file_id = $file_upload_response['id'];

      // Then, add the file to the vector store
      $body = [
        'file_id' => $file_id
      ];
      
      $res = $this->run( 'POST', "/vector_stores/{$this->store_id}/files", $body );
      
      // Clean up temp file
      unlink( $temp_file );
      
      if ( isset( $res['id'] ) ) {
        return $res['id'];
      }
      
      throw new Exception( 'Failed to add file to vector store' );
    } catch ( Exception $e ) {
      // Clean up temp file on error
      if ( file_exists( $temp_file ) ) {
        unlink( $temp_file );
      }
      throw $e;
    }
  }

  // Upload a file to OpenAI
  private function upload_file( $file_path, $filename ) {
    $boundary = wp_generate_password( 24 );
    $headers = "Content-Type: multipart/form-data; boundary={$boundary}\r\n" .
      'Authorization: Bearer ' . $this->apiKey . "\r\n";

    $file_contents = file_get_contents( $file_path );
    $body = "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
    $body .= "assistants\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}.txt\"\r\n";
    $body .= "Content-Type: text/plain\r\n\r\n";
    $body .= $file_contents . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $options = [
      'headers' => $headers,
      'method' => 'POST',
      'timeout' => MWAI_TIMEOUT,
      'body' => $body,
      'sslverify' => false
    ];

    $response = wp_remote_request( 'https://api.openai.com/v1/files', $options );
    if ( is_wp_error( $response ) ) {
      throw new Exception( $response->get_error_message() );
    }
    
    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );
    
    if ( isset( $data['error'] ) ) {
      throw new Exception( $data['error']['message'] ?? 'Unknown OpenAI error' );
    }
    
    return $data;
  }

  // Query vectors from OpenAI Vector Store using the search API.
  public function query_vectors( $vectors, $vector, $options ) {
    if ( !empty( $vectors ) ) {
      return $vectors;
    }
    $envId = $options['envId'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    
    // Check if direct integration will be used (file_search tool in Responses API)
    // This happens when the query's AI environment matches our OpenAI environment
    $query = isset( $options['query'] ) ? $options['query'] : null;
    
    if ( $query && $query instanceof Meow_MWAI_Query_Text && $query->envId === $this->openai_env_id ) {
      // Check if model supports Responses API using the engine's model info
      try {
        $engine = Meow_MWAI_Engines_Factory::get( $this->core, $query->envId );
        $modelInfo = $engine->retrieve_model_info( $query->model );
        
        $supportsResponsesApi = $modelInfo && !empty( $modelInfo['tags'] ) && in_array( 'responses', $modelInfo['tags'] );
        $useResponsesApi = $this->core->get_option( 'ai_responses_api', true );
        
        if ( $supportsResponsesApi && $useResponsesApi ) {
          // Direct integration will be used - return empty to skip local embeddings
          return [];
        }
      } catch ( Exception $e ) {
        // If we can't get the engine or model info, fall back to local embeddings
        Meow_MWAI_Logging::error( 'Failed to check model capabilities: ' . $e->getMessage() );
      }
    }

    // Use the OpenAI Vector Store search API to find relevant vectors
    try {
      // For Vector Store, we need the original text query, not embeddings
      // The $vector parameter contains embeddings when called from context_search
      // We need to get the original query text from the options
      $searchQuery = '';
      
      if ( isset( $options['query'] ) && is_object( $options['query'] ) ) {
        // Get the message from the query object
        if ( method_exists( $options['query'], 'get_message' ) ) {
          $searchQuery = $options['query']->get_message();
        } elseif ( property_exists( $options['query'], 'message' ) ) {
          $searchQuery = $options['query']->message;
        }
      }
      
      // If we still don't have a query, check if we have searchQuery in options
      if ( empty( $searchQuery ) && isset( $options['searchQuery'] ) ) {
        $searchQuery = $options['searchQuery'];
      }
      
      // For OpenAI Vector Store, the search query is passed as the second parameter
      if ( empty( $searchQuery ) && is_string( $vector ) ) {
        $searchQuery = $vector;
      }
      
      if ( empty( $searchQuery ) ) {
        Meow_MWAI_Logging::error( 'No search query text available for Vector Store search' );
        return [];
      }
      
      $body = [
        'query' => $searchQuery,
        'max_num_results' => $this->maxSelect,
        'rewrite_query' => true // Enable query rewriting for better search results
      ];
      
      $res = $this->run( 'POST', "/vector_stores/{$this->store_id}/search", $body );
      
      if ( !isset( $res['data'] ) || !is_array( $res['data'] ) ) {
        Meow_MWAI_Logging::error( 'Vector Store search returned no data' );
        return [];
      }
      
      // Map the search results to our expected format
      global $wpdb;
      $table_vectors = $wpdb->prefix . 'mwai_vectors';
      $results = [];
      
      foreach ( $res['data'] as $searchResult ) {
        $fileId = $searchResult['file_id'];
        $score = isset( $searchResult['score'] ) ? $searchResult['score'] : 0.5;
        
        // Find the local vector ID based on the dbId (file_id)
        $localVectorId = $wpdb->get_var( $wpdb->prepare(
          "SELECT id FROM {$table_vectors} WHERE dbId = %s AND envId = %s",
          $fileId,
          $envId
        ) );
        
        if ( $localVectorId ) {
          $results[] = [
            'id' => $fileId,  // Use dbId as the id (this is what embeddings.php expects)
            'score' => $score
          ];
        } else {
          // If we don't have a local record, still include it but log a warning
          Meow_MWAI_Logging::warn( "Vector Store file {$fileId} not found in local database" );
          $results[] = [
            'id' => $fileId,
            'score' => $score
          ];
        }
      }
      
      return $results;
      
    } catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Vector Store search failed: ' . $e->getMessage() );
      
      // Fall back to returning all vectors if search fails
      global $wpdb;
      $table_vectors = $wpdb->prefix . 'mwai_vectors';
      
      $query = $wpdb->prepare(
        "SELECT id, dbId FROM {$table_vectors} 
         WHERE envId = %s AND dbId IS NOT NULL AND status = 'ok'
         ORDER BY updated DESC
         LIMIT %d",
        $envId,
        $this->maxSelect
      );
      
      $localVectors = $wpdb->get_results( $query, ARRAY_A );
      
      if ( empty( $localVectors ) ) {
        return [];
      }
      
      $results = [];
      $baseScore = 0.9;
      $scoreDecrement = 0.05;
      
      foreach ( $localVectors as $index => $localVector ) {
        $score = max( 0.1, $baseScore - ( $index * $scoreDecrement ) );
        $results[] = [
          'id' => $localVector['dbId'],
          'score' => $score
        ];
      }
      
      return $results;
    }
  }

  // Get a vector from OpenAI Vector Store.
  public function get_vector( $vector, $vectorId, $envId, $options ) {
    if ( !empty( $vector ) ) {
      return $vector;
    }
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }

    try {
      // Get file details from vector store
      $res = $this->run( 'GET', "/vector_stores/{$this->store_id}/files/{$vectorId}" );
      
      if ( isset( $res['id'] ) ) {
        // Get the actual file content
        $file_content = $this->run( 'GET', "/files/{$res['id']}/content", null, false );
        
        return [
          'id' => $res['id'],
          'content' => $file_content,
          'metadata' => $res
        ];
      }
    } catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Failed to get vector: ' . $e->getMessage() );
    }
    
    return null;
  }
}