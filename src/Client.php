<?php

/**
 * PLEIADES PHP API Client
 * Library to access the PLEIADES API.
 *
 * Author: Dusan Daniska, dusan.daniska@wai.sk
 *
 * License: See LICENSE.md file in the root folder of the software package.
 */

namespace PleiadesDecom\PhpApiClient;

class Client {

  public string $clientId;              // CLIENT_ID defined in the IAM (Keycloak)
  public string $clientSecret;          // CLIENT_SECRET defined in the IAM (Keycloak)
  public string $userName;              // USER_NAME defined in the IAM (Keycloak)
  public string $userPassword;          // USER_PASSWORD defined in the IAM (Keycloak)

  public string $iamTokenEndpoint;      // OAuth compatible endpoint of the IAM
  public string $apiEndpoint;           // PLEIADES connector (API server) endpoint

  // HTTP client
  public object $guzzle;                // 3rd-party HTTP library
  public object $lastResponse;          // Calue of the last HTTP response
  public string $debugFile;             // Path to the HTTP debug file

  // S3 client
  public object $s3Client;              // 3rd-party S3 client library

  // IAM client
  public string $accessToken;           // Access token received from IAM

  // Miscelaneous
  public string $database;              // Name of the database which will be used
                                        // in the HTTP requests
  
  /**
   * Constructs a PLEIADES PHP API client object
   *
   * @param  mixed $config
   * @return void
   */
  public function __construct(array $config) {

    // load configuration
    $this->clientId = $config['clientId'] ?? "";
    $this->clientSecret = $config['clientSecret'] ?? "";
    $this->userName = $config['userName'] ?? "";
    $this->userPassword = $config['userPassword'] ?? "";
    $this->debugFile = $config['debugFile'] ?? "";

    $this->iamTokenEndpoint = $config['iamTokenEndpoint'] ?? "";
    $this->apiEndpoint = $config['apiEndpoint'] ?? "";
    $this->s3Endpoint = $config['s3Endpoint'] ?? "";

    // initiate HTTP client
    $this->guzzle = new \GuzzleHttp\Client(['verify' => false]);

    // initiate S3 client
    $this->s3Client = new \Aws\S3\S3Client([
      'version' => 'latest',
      'region'  => 'us-east-1',
      'endpoint' => $this->s3Endpoint,
      'use_path_style_endpoint' => true,
      'credentials' => [
        'key'    => $this->userName,
        'secret' => $this->userPassword,
      ],
      'http' => ['verify' => FALSE],
    ]);

  }
  
  /**
   * Fetches access token from the IAM (Keycloak)
   *
   * @return string Access token received from the IAM (Keycloak)
   */
  public function getAccessToken() : string {
    $response = $this->guzzle->request(
      "POST",
      $this->iamTokenEndpoint."/token",
      [
        'headers' => [
          'content-type' => 'application/x-www-form-urlencoded',
        ],
        'form_params' => [
          'grant_type' => 'password',
          'client_id' => $this->clientId,
          'client_secret' => $this->clientSecret,
          'username' => $this->userName,
          'password' => $this->userPassword,
        ]
      ]
    );

    $responseJson = @json_decode((string) $response->getBody(), TRUE);

    $this->accessToken = $responseJson['access_token'] ?? "";

    return $this->accessToken;
  }
  
  /**
   * Send a request to the PLEIADES connector (API server)
   *
   * @param  mixed $method HTTP method (GET/POST/PUT/DELETE)
   * @param  mixed $command A command (API function) to call (e.g. "/database/DB_NAME/record/RECORD_ID")
   * @param  mixed $body Array of request's body parameters.
   * @return object Guzzle's HTTP response object.
   */
  public function sendRequest(string $method, string $command, array $body = []) {
    try {
      $options = [
        'headers' => [
          'content-type' => 'application/json',
          'authorization' => "Bearer {$this->accessToken}",
        ],
        'body' => json_encode($body),
      ];

      if (!empty($this->debugFile)) {
        $options['debug'] = fopen($this->debugFile, 'w');
      }


      // $options['debug'] = TRUE;

      $this->lastResponse = $this->guzzle->request(
        $method,
        $this->apiEndpoint.$command,
        $options
      );

      return $this->lastResponse;
    } catch (\GuzzleHttp\Exception\ConnectException $e) {
      throw new \PleiadesDecom\PhpApiClient\Exception\RequestException(
        json_encode([
          "statusCode" => 503,
          "reason" => "Connection to PLEIADES API server failed."
        ])
      );
    } catch (\GuzzleHttp\Exception\BadResponseException $e) {
      $this->lastResponse = $e->getResponse();
      throw new \PleiadesDecom\PhpApiClient\Exception\RequestException(
        json_encode([
          "statusCode" => $this->lastResponse->getStatusCode(),
          "reason" => $this->lastResponse->getReasonPhrase(),
          "responseBody" => @json_decode($this->lastResponse->getBody(true))
        ])
      );
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      throw new \PleiadesDecom\PhpApiClient\Exception\RequestException(
        json_encode([
          "statusCode" => 500,
          "reason" => "General RequestException error."
        ])
      );
    } 
  }
  
  /**
   * Creates a database.
   *
   * @param  mixed $database
   * @return void
   */
  public function createDatabase(string $database) {
    $res = $this->sendRequest("POST", "/database/{$database}");
    return (string) $res->getBody();
  }
  
  /**
   * Sets a database to be used in the request shortcuts.
   *
   * @param  mixed $database
   * @return void
   */
  public function setDatabase(string $database) {
    $this->database = $database;
  }
  
  /**
   * Shortcut to create a record.
   *
   * @param  mixed $recordContent Content of the new record.
   * @return string RecordId in case of 200 success. Otherwise exception is thrown.
   */
  public function createRecord(array $recordContent) : string {
    $res = $this->sendRequest("POST", "/database/{$this->database}/record", $recordContent);
    return (string) $res->getBody();
  }
  
  /**
   * Shortcut to update a record
   *
   * @param  mixed $recordId ID of the record to update.
   * @param  mixed $recordContent New record's content.
   * @return string RecordId in case of 200 success. Otherwise exception is thrown.
   */
  public function updateRecord(string $recordId, array $recordContent) : string {
    $res = $this->sendRequest("PUT", "/database/{$this->database}/record/{$recordId}", $recordContent);
    return (string) $res->getBody();
  }
  
  /**
   * Shortcut to get a record.
   *
   * @param  mixed $recordId ID of the record to get.
   * @return array Data of the requested record. Otherwise exception is thrown.
   */
  public function getRecord(string $recordId) : array {
    $res = $this->sendRequest("GET", "/database/{$this->database}/record/{$recordId}");
    return (array) json_decode((string) $res->getBody(), TRUE);
  }
  
  /**
   * Shortcut to delete a record
   *
   * @param  mixed $recordId ID of the record to delete.
   * @return string RecordId in case of 200 success. Otherwise exception is thrown.
   */
  public function deleteRecord(string $recordId) : string {
    $res = $this->sendRequest("DELETE", "/database/{$this->database}/record/{$recordId}");
    return (string) $res->getBody();
  }
  
  /**
   * Shortcut to get records by a query.
   *
   * @param  mixed $query A MongoDB-like search query.
   * @return array List of records matching the query.
   */
  public function getRecords($query = NULL) : array {
    $res = $this->sendRequest("POST", "/database/{$this->database}/records", ["query" => $query]);
    return (array) json_decode((string) $res->getBody(), TRUE);
  }

  /**
   * Shortcut to get list of available databases.
   *
   * @return array List of available databases..
   */
  public function getDatabases() : array {
    $res = $this->sendRequest("GET", "/databases");
    return json_decode((string) $res->getBody(), TRUE);
  }

  /**
   * Shortcut to delete a database
   *
   * @return string $databaseName of 200 success. Otherwise exception is thrown.
   */
  public function deleteDatabase() : string {
    $res = $this->sendRequest("DELETE", "/database/{$this->database}");
    return (string) $res->getBody();
  }

  public function downloadFile(string $url) : string {
    $tmpPos = strpos($url, "/");
    if ($tmpPos === FALSE) {
      $bucketName = "";
      $fileName = $url;
    } else {
      $bucketName = substr($url, 0, $tmpPos);
      $fileName = substr($url, $tmpPos + 1);
    }

    // Download the contents of the object.
    $object = $this->s3Client->getObject([
      'Bucket' => $bucketName,
      'Key'    => $fileName,
    ]);

    // Print the body of the result by indexing into the result object.
    return (string) $object['Body'];
  }

}