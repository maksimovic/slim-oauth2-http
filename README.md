# Chadicus\Slim\OAuth2\Http

> **Fork Notice:** This is a maintained fork of the abandoned [`chadicus/slim-oauth2-http`](https://github.com/chadicus/slim-oauth2-http) package. Updated for PHP 8.1+ with support for `laminas/laminas-diactoros` v3.

Static utility classes to bridge [PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP messages to [OAuth2 Server](http://bshaffer.github.io/oauth2-server-php-docs/) requests and responses. While this library is intended for use with [Slim](http://www.slimframework.com/), it should work with any PSR-7 compatible framework.

## Requirements

- PHP 8.1+
- [bshaffer/oauth2-server-php](https://github.com/bshaffer/oauth2-server-php) ^1.8
- [laminas/laminas-diactoros](https://github.com/laminas/laminas-diactoros) ^2.0 || ^3.0

## Installation

```sh
composer require maksimovic/slim-oauth2-http
```

## Usage

### Convert a PSR-7 request to an OAuth2 request

```php
use Chadicus\Slim\OAuth2\Http\RequestBridge;

$oauth2Request = RequestBridge::toOAuth2($psrRequest);
```

### Convert an OAuth2 response to a PSR-7 response

```php
use Chadicus\Slim\OAuth2\Http\ResponseBridge;

$psr7Response = ResponseBridge::fromOAuth2($oauth2Response);
```

## Example Integration

### Simple route for creating a new OAuth2 access token

```php
use Chadicus\Slim\OAuth2\Http\RequestBridge;
use Chadicus\Slim\OAuth2\Http\ResponseBridge;
use OAuth2;
use OAuth2\GrantType;
use OAuth2\Storage;
use Slim;

$storage = new Storage\Memory(
    [
        'client_credentials' => [
            'testClientId' => [
                'client_id' => 'testClientId',
                'client_secret' => 'testClientSecret',
            ],
        ],
    ]
);

$server = new OAuth2\Server(
    $storage,
    [
        'access_lifetime' => 3600,
    ],
    [
        new GrantType\ClientCredentials($storage),
    ]
);

$app = new Slim\App();

$app->post('/token', function ($psrRequest, $psrResponse, array $args) use ($app, $server) {
    // Create an \OAuth2\Request from the PSR-7 request
    $oauth2Request = RequestBridge::toOAuth2($psrRequest);

    // Let the OAuth2 server handle the request
    $oauth2Response = $server->handleTokenRequest($oauth2Request);

    // Map the OAuth2 response to a PSR-7 response
    return ResponseBridge::fromOAuth2($oauth2Response);
});
```

## Development

```sh
composer install
composer test
composer test:coverage
composer cs-check
```

## License

[MIT](LICENSE)
