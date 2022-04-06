<?php

/**
 * PLEIADES PHP API Client
 * Library to access the PLEIADES API.
 *
 * Author: Dusan Daniska, dusan.daniska@wai.sk
 *
 * License: See LICENSE.md file in the root folder of the software package.
 */


// HOW TO USE:
//   - copy this file and related composer.json in the root folder of your project
//   - run composer install
//   - configure the Client's configuration
//   - run the script


require_once('vendor/autoload.php');

// initiate API client
$api = new \PleiadesDecom\PhpApiClient\Client([
  "clientId" => "", // your app's client ID
  "clientSecret" => "", // your app's client secret
  "userName" => "", // name of the user to authenticate
  "userPassword" => "", // password of the user to authenticate
  "iamTokenEndpoint" => "", // OIDC endpoint address of IAM server
  "apiEndpoint" => "", // API server endpoint address
  "debugFile" => "guzzle_debug.log", // log file
]);

// retrieve the access token
$api->getAccessToken();
echo "Received access token: {$api->accessToken}";

// tell the client to work with 'testDatabase'
$api->setDatabase("testDatabase");

// get the list of records
$records = $api->getRecords(["class" => "Database.Information"]);

// print out received records
var_dump($records);
