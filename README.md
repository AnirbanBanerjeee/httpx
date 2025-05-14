# HttpX - PHP HTTP Client

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net/)
[![cURL Required](https://img.shields.io/badge/cURL-Required-green.svg)](https://curl.se/)

A robust HTTP client with retry logic, proxy support, concurrent requests, and logging capabilities.

##   Why Choose HttpX? 
- Automatic retry with backoff
- Proxy & authentication support
- Cookie persistence
- JSON request/response handling
- Concurrent multi-requests
- Detailed logging
- Timeout configuration
- All HTTP methods support

## Installation 📦
```php
require_once 'HttpX.php';
```

## Configuration 
```php
// Set proxy with authentication
HttpX::setProxy('proxy.example.com:3128', 'user:pass');

// Configure timeouts (seconds)
HttpX::setTimeout(20);

// Set maximum retries
HttpX::setRetryCount(5);

// Enable logging
HttpX::setLogFile(__DIR__.'/http_logs.log');
```
## Usage Examples
<b>GET Request</b>
```php
$response = HttpX::send('https://api.example.com/data');
echo "Status: {$response->statusCode}\nBody: {$response->body}";
```
<b>POST Form Data</b>
```php
$response = HttpX::send(
    'https://api.example.com/login',
    'POST',
    ['username' => 'john', 'password' => 'secret']
);
// JSON
$response = HttpX::sendJson(
    'https://api.example.com/users',
    'POST',
    ['name' => 'Alice', 'email' => 'alice@example.com']
);
```
<b>PATCH Request</b>
```php
$response = HttpX::sendJson(
    'https://api.example.com/users/123',
    'PATCH',
    ['status' => 'inactive']
);
```
<b>File Download with Cookies</b>
```php
// Login and save cookies
HttpX::send(
    'https://example.com/login',
    'POST',
    ['user' => 'me', 'pass' => 'secret'],
    [],
    'cookies.txt'
);

// Download protected content
$response = HttpX::send(
    'https://example.com/dashboard',
    'GET',
    null,
    [],
    'cookies.txt'
);
```
<b>Concurrent Requests</b>
```php
$requests = [
    'profile' => [
        'url' => 'https://api.example.com/users/1',
        'method' => 'GET'
    ],
    'create' => [
        'url' => 'https://api.example.com/posts',
        'method' => 'POST',
        'data' => ['title' => 'Hello']
    ]
];

$responses = HttpX::multiSend($requests);
echo $responses['profile']->body;
```

## Response Object
```php
class HttpXResponse {
    public int $statusCode;
    public string $body;
    public int $retryCount;
}

// Example usage:
if ($response->statusCode === 200) {
    $data = json_decode($response->body);
}
```
## Logging Format
```log
[Timestamp] Request to {URL} with method {METHOD} returned status {STATUS}
[Timestamp] Response: {RESPONSE_BODY}
[Timestamp] Retry Count: {COUNT}
```

## Requirements 
```
• PHP 7.4+
• cURL extension
• OpenSSL extension (for HTTPS)
```

## Author 👤 
- GitHub: [@anirbanbanerjeee](https://github.com/anirbanbanerjeee)  

## License 📄
This project is licensed under the MIT License
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
