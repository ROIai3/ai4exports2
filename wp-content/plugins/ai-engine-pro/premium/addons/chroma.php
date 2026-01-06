<?php

class MeowPro_MWAI_Addons_Chroma {
  private $core = null;

  // Current Vector DB
  private $env = null;
  private $apiKey = null;
  private $server = null;
  private $tenant = null;
  private $database = null;
  private $collection = null;
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

    // This class has only Chroma support.
    if ( empty( $this->env ) || $this->env['type'] !== 'chroma' ) {
      return false;
    }

    $this->apiKey = isset( $this->env['apikey'] ) ? $this->env['apikey'] : null;
    
    // Trim the server URL
    $server = isset( $this->env['server'] ) && !empty( $this->env['server'] ) ? $this->env['server'] : 'https://api.trychroma.com';
    $this->server = rtrim( trim( $server ), '/' );
    
    $this->tenant = isset( $this->env['tenant'] ) && !empty( $this->env['tenant'] ) ? $this->env['tenant'] : null;
    $this->database = isset( $this->env['database'] ) && !empty( $this->env['database'] ) ? $this->env['database'] : 'default_database';
    $this->collection = isset( $this->env['collection'] ) && !empty( $this->env['collection'] ) 
      ? $this->env['collection'] 
      : 'mwai';
    $this->maxSelect = isset( $this->env['max_select'] ) ? (int) $this->env['max_select'] : 10;
    return true;
  }

  // Generic function to run a request to Chroma.
  public function run( $method, $url, $query = null, $json = true, $isAbsoluteUrl = false ) {
    // Detect if this is Chroma Cloud based on the server URL
    $isChromaCloud = strpos( $this->server, 'trychroma.com' ) !== false || strpos( $this->server, 'chroma.com' ) !== false;
    
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json'
    ];
    
    if ( $isChromaCloud ) {
      // Chroma Cloud uses special headers
      if ( $this->apiKey ) {
        $headers['X-Chroma-Token'] = $this->apiKey;
      }
      if ( $this->tenant ) {
        $headers['X-Chroma-Tenant'] = $this->tenant;
      }
    } else {
      // Self-hosted uses Bearer token
      if ( $this->apiKey ) {
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;
      }
    }
    
    $body = $query ? json_encode( $query ) : null;
    
    // Construct URL based on the server type
    if ( !$isAbsoluteUrl ) {
      // Both Chroma Cloud and self-hosted now use v2 API
      $tenant = $this->tenant ?: 'default_tenant';
      $url = $this->server . "/api/v2/tenants/{$tenant}/databases/{$this->database}" . $url;
    }
    
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
      return $data;
    }
    catch ( Exception $e ) {
      Meow_MWAI_Logging::error( 'Chroma: ' . $e->getMessage() );
      throw new Exception( $e->getMessage() . ' (Chroma)' );
    }
    return [];
  }

  // List all vectors from Chroma.
  public function list_vectors( $vectors, $options ) {
    if ( !empty( $vectors ) ) {
      return $vectors;
    }
    $envId = $options['envId'];
    $limit = $options['limit'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    
    try {
      // Get collection ID first
      $collectionId = $this->get_collection_id( $this->collection );
      
      // Get documents from the collection
      $body = [ 'limit' => $limit ?: 100 ];
      $res = $this->run( 'POST', "/collections/{$collectionId}/get", $body );
      
      // Extract IDs from the response
      $vectors = isset( $res['ids'] ) ? $res['ids'] : [];
      
      return $vectors;
    }
    catch ( Exception $e ) {
      // If collection doesn't exist, return empty array
      return [];
    }
  }

  // Delete vectors from Chroma.
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
    
    try {
      // Get collection ID
      $collectionId = $this->get_collection_id( $this->collection );
      
      if ( $deleteAll ) {
        // For delete all, we need to get all IDs first, then delete them
        $allVectors = $this->list_vectors( [], [ 'envId' => $envId, 'limit' => null ] );
        if ( empty( $allVectors ) ) {
          return true; // Nothing to delete
        }
        $ids = $allVectors;
      }
      
      if ( !empty( $ids ) ) {
        $body = [ 'ids' => $ids ];
        $this->run( 'POST', "/collections/{$collectionId}/delete", $body );
      }
      
      return true;
    }
    catch ( Exception $e ) {
      // If collection doesn't exist, consider it a success
      return true;
    }
  }

  // Add a vector to Chroma.
  public function add_vector( $success, $vector, $options ) {
    if ( $success ) {
      return $success;
    }
    $envId = $options['envId'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    
    try {
      // Ensure collection exists before adding
      $this->ensure_collection_exists();
      
      // Get collection ID
      $collectionId = $this->get_collection_id( $this->collection );

      $randomId = $this->get_uuid();
      $body = [
        'ids' => [ $randomId ],
        'embeddings' => [ $vector['embedding'] ],
        'metadatas' => [ [
          'type' => $vector['type'],
          'title' => $vector['title'],
          'model' => $vector['model']
        ] ]
      ];
      
      // Add document content if available
      if ( !empty( $vector['content'] ) ) {
        $body['documents'] = [ $vector['content'] ];
      }
      
      $res = $this->run( 'POST', "/collections/{$collectionId}/add", $body );
      
      // Chroma v2 returns success without returning the IDs
      return $randomId;
    }
    catch ( Exception $e ) {
      throw new Exception( 'Failed to add vector: ' . $e->getMessage() );
    }
  }

  // Query vectors from Chroma.
  public function query_vectors( $vectors, $vector, $options ) {
    if ( !empty( $vectors ) ) {
      return $vectors;
    }
    $envId = $options['envId'];
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    
    try {
      // Get collection ID
      $collectionId = $this->get_collection_id( $this->collection );
      
      $body = [ 
        'query_embeddings' => [ $vector ],
        'n_results' => $this->maxSelect
      ];
      
      $res = $this->run( 'POST', "/collections/{$collectionId}/query", $body );
      
      // Chroma returns results in a nested array format
      if ( isset( $res['ids'] ) && isset( $res['ids'][0] ) ) {
        $vectors = [];
        $ids = $res['ids'][0];
        $distances = isset( $res['distances'][0] ) ? $res['distances'][0] : [];
        $metadatas = isset( $res['metadatas'][0] ) ? $res['metadatas'][0] : [];
        $documents = isset( $res['documents'][0] ) ? $res['documents'][0] : [];
        
        // Format results to match expected structure
        for ( $i = 0; $i < count( $ids ); $i++ ) {
          // Chroma uses cosine distance (0 = identical, 2 = opposite)
          // Convert to similarity score (1 = identical, 0 = opposite)
          $distance = isset( $distances[$i] ) ? $distances[$i] : 0;
          $score = 1 - ( $distance / 2 );
          
          $vectors[] = [
            'id' => $ids[$i],
            'score' => $score,
            'metadata' => isset( $metadatas[$i] ) ? $metadatas[$i] : [],
            'document' => isset( $documents[$i] ) ? $documents[$i] : ''
          ];
        }
      }
      
      return $vectors;
    }
    catch ( Exception $e ) {
      // If collection doesn't exist, return empty array
      return [];
    }
  }

  // Get a vector from Chroma.
  public function get_vector( $vector, $vectorId, $envId, $options ) {
    // Check if the filter has been already handled.
    if ( !empty( $vector ) ) {
      return $vector;
    }
    if ( !$this->init_settings( $envId ) ) {
      return false;
    }
    
    try {
      // Get collection ID
      $collectionId = $this->get_collection_id( $this->collection );
      
      // Query for the specific vector
      $body = [ 'ids' => [ $vectorId ] ];
      
      $res = $this->run( 'POST', "/collections/{$collectionId}/get", $body );
      
      if ( isset( $res['ids'] ) && in_array( $vectorId, $res['ids'] ) ) {
        $index = array_search( $vectorId, $res['ids'] );
        $metadata = isset( $res['metadatas'][$index] ) ? $res['metadatas'][$index] : [];
        
        return [
          'id' => $vectorId,
          'type' => isset( $metadata['type'] ) ? $metadata['type'] : 'manual',
          'title' => isset( $metadata['title'] ) ? $metadata['title'] : '',
          'content' => isset( $res['documents'][$index] ) ? $res['documents'][$index] : '',
          'model' => isset( $metadata['model'] ) ? $metadata['model'] : '',
          'values' => isset( $res['embeddings'][$index] ) ? $res['embeddings'][$index] : []
        ];
      }
      
      return null;
    }
    catch ( Exception $e ) {
      return null;
    }
  }

  // Ensure collection exists, create if not
  private function ensure_collection_exists() {
    try {
      // Try to get collection ID
      $this->get_collection_id( $this->collection );
      // If we get here, collection exists
      return true;
    }
    catch ( Exception $e ) {
      // Collection doesn't exist, create it
      $this->create_collection();
    }
  }

  // Create a new collection
  private function create_collection() {
    $body = [
      'name' => $this->collection
    ];
    
    // Add metadata if it's not empty
    $metadata = [
      'description' => 'AI Engine Pro vectors',
      'created_by' => 'mwai'
    ];
    if ( !empty( $metadata ) ) {
      $body['metadata'] = $metadata;
    }
    
    try {
      $res = $this->run( 'POST', '/collections', $body );
      return true;
    }
    catch ( Exception $e ) {
      // Collection might already exist, which is fine
      if ( strpos( $e->getMessage(), 'already exists' ) === false ) {
        throw $e;
      }
    }
  }

  // Get collection ID by name
  private function get_collection_id( $name ) {
    // Both Chroma Cloud and self-hosted v2 API need to look up collection ID
    $tenant = $this->tenant ?: 'default_tenant';
    $collections = $this->run( 'GET', "/collections?tenant_name={$tenant}&database_name={$this->database}" );
    
    if ( isset( $collections ) && is_array( $collections ) ) {
      foreach ( $collections as $collection ) {
        if ( isset( $collection['name'] ) && $collection['name'] === $name ) {
          return $collection['id'];
        }
      }
    }
    
    throw new Exception( "Collection not found: {$name}" );
  }

  // Generate UUID for vector IDs
  private function get_uuid( $len = 32, $strong = true ) {
    $data = openssl_random_pseudo_bytes( $len, $strong );
    $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
    $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
  }
}