<?php

/**
* Class MeowPro_MWAI_Stats
*
* Represents a statistics data object (a single stat).
* You can pass an instance of this to MeowPro_MWAI_Statistics::commit_stats()
* or commit_stats_from_realtime() to create or update stats in the database.
*/
class MeowPro_MWAI_Stats {
  /**
  * Unique reference ID (if provided and found in DB, the stat will be updated).
  * @var string|null
  */
  public $refId = null;

  /**
  * Session ID (used to group stats).
  * @var string|null
  */
  public $session = null;

  /**
  * Feature name (e.g. 'assistant', 'embedding', etc.).
  * @var string|null
  */
  public $feature = null;

  /**
  * Model name (e.g. 'gpt-4', 'gpt-4o-mini-realtime-preview', etc.).
  * @var string|null
  */
  public $model = null;

  /**
  * Environment ID (optional).
  * @var string|null
  */
  public $envId = null;

  /**
  * Number of units (tokens, images, seconds, etc.).
  * @var int
  */
  public $units = 0;

  /**
  * Type of units (e.g. 'tokens', 'images', 'seconds').
  * @var string|null
  */
  public $type = null;

  /**
  * Price of this usage (computed or set externally).
  * @var float
  */
  public $price = 0.0;

  /**
  * Scope of the usage (e.g. 'chatbot', 'form', etc.).
  * @var string|null
  */
  public $scope = null;

  /**
  * Additional metadata that you might want to store in logmeta.
  * @var array
  */
  public $metadata = [];

  /**
  * Complex stats info, stored as JSON in the "stats" DB column (e.g., token breakdown).
  * @var array|null
  */
  public $stats = null;

  /**
  * Accuracy of usage data ('none', 'estimated', 'tokens', 'price', 'full').
  * @var string
  */
  public $accuracy = 'none';

  /**
  * Constructor (optional). Set any default values or handle initialization.
  */
  public function __construct() {
    // Default constructor if needed
  }

  // Add more helper methods here as you like.
}
