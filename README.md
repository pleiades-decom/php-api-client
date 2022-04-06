# PLEIADES PHP API Client

Library for accessing PLEIADES API.

Requires PHP 7.4 and above.

## Basic usage examples

```php
$api = new \PleiadesDecom\PhpApiClient\Client([
  "clientId" => "", // your app's client ID
  "clientSecret" => "", // your app's client secret
  "userName" => "", // name of the user to authenticate
  "userPassword" => "", // password of the user to authenticate
  "iamTokenEndpoint" => "", // OIDC endpoint address of IAM server
  "apiEndpoint" => "", // API server endpoint address
]);
$api->getAccessToken();
$api->setDatabase("testDatabase");
$records = $api->getRecords(["class" => "Database.Information"]);
```

## Working with records

### Create record

```php
$createdRecordId = $api->createRecord([
  "class" => "Any.Valid.Class.Name",
  "content" => ["AnyValidContent" => "AnyValidValue"]
]);
```

### Update record

```php
$api->updateRecord(
  $recordId,
  [
    "class" => "Any.Valid.Class.Name",
    "content" => ["AnyValidContent" => "AnyValidValue"]
  ]
);
```

### Delete record

```php
$api->deleteRecord($recordId);
```

### Get single record

```php
$record = $api->getRecord($recordId);
```

### Get list of records

```php
$records = $api->getRecords(["class" => "Any.Valid.Class.Name"]);
```