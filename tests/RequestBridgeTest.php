<?php

namespace ChadicusTest\Slim\OAuth2\Http;

use Chadicus\Slim\OAuth2\Http\RequestBridge;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;

final class RequestBridgeTest extends TestCase
{
    public function testToOAuth2BasicRequest(): void
    {
        $uri = 'https://example.com/foo/bar';
        $headers = ['Host' => ['example.com'], 'Accept' => ['application/json', 'text/json']];
        $cookies = ['PHPSESSID' => uniqid()];
        $server = ['SCRIPT_NAME' => __FILE__, 'SCRIPT_FILENAME' => __FILE__];
        $json = json_encode(['foo' => 'bar', 'abc' => '123']);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $json);
        rewind($stream);

        $files = [
            'foo' => new UploadedFile(
                __FILE__,
                100,
                UPLOAD_ERR_OK,
                'foo.txt',
                'text/plain'
            ),
        ];

        $psr7Request = new ServerRequest($server, $files, $uri, 'PATCH', $stream, $headers, $cookies, ['baz' => 'bat']);

        $oauth2Request = RequestBridge::toOauth2($psr7Request);

        $this->assertInstanceOf('\OAuth2\Request', $oauth2Request);
        $this->assertSame('bat', $oauth2Request->query('baz'));
        $this->assertSame('example.com', $oauth2Request->headers('Host'));
        $this->assertSame('application/json, text/json', $oauth2Request->headers('Accept'));
        $this->assertSame($cookies, $oauth2Request->cookies);
        $this->assertSame(__FILE__, $oauth2Request->server('SCRIPT_NAME'));
        $this->assertSame($json, $oauth2Request->getContent());

        $this->assertSame(
            [
                'foo' => [
                    'name' => 'foo.txt',
                    'type' => 'text/plain',
                    'size' => 100,
                    'tmp_name' => __FILE__,
                    'error' => UPLOAD_ERR_OK,
                ],
            ],
            $oauth2Request->files
        );
    }

    public function testToOAuth2JsonContentType(): void
    {
        $uri = 'https://example.com/foos';

        $data = ['foo' => 'bar', 'abc' => '123'];

        $json = json_encode($data);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $json);
        rewind($stream);

        $headers = [
            'Content-Type' => ['application/json'],
            'Content-Length' => [strlen($json)],
        ];

        $psr7Request = new ServerRequest([], [], $uri, 'POST', $stream, $headers, [], [], $data);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame((string)strlen($json), $oauth2Request->headers('Content-Length'));
        $this->assertSame('application/json', $oauth2Request->headers('Content-Type'));
        $this->assertSame('bar', $oauth2Request->request('foo'));
        $this->assertSame('123', $oauth2Request->request('abc'));
    }

    public function testToOAuth2HeaderKeyNames(): void
    {
        $uri = 'https://example.com/foos';

        $headers = [
            'Php-Auth-User' => ['test_client_id'],
            'Php-Auth-Pw' => ['test_secret'],
        ];

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', $headers);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('test_client_id', $oauth2Request->headers('PHP_AUTH_USER'));
        $this->assertSame('test_secret', $oauth2Request->headers('PHP_AUTH_PW'));
        $this->assertNull($oauth2Request->headers('Php-Auth-User'));
        $this->assertNull($oauth2Request->headers('Php-Auth-Pw'));
    }

    public function testToOAuth2WithAuthorization(): void
    {
        $uri = 'https://example.com/foos';

        $headers = ['HTTP_AUTHORIZATION' => ['Bearer abc123']];

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', $headers);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('Bearer abc123', $oauth2Request->headers('AUTHORIZATION'));
    }

    public function testToOAuth2BodyContentsOfRequestPreserved(): void
    {
        $uri = 'https://example.com/foos';

        $temp = tmpfile();
        fwrite($temp, 'foo');
        rewind($temp);

        $psr7Request = new ServerRequest([], [], $uri, 'POST', $temp);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('foo', $psr7Request->getBody()->getContents());
        $this->assertSame('foo', $oauth2Request->getContent());
    }

    public function testToOAuth2WithMultipleFiles(): void
    {
        $files = [
            'multi' => [
                new UploadedFile(
                    __FILE__,
                    100,
                    UPLOAD_ERR_OK,
                    'foo1.txt',
                    'text/plain'
                ),
                new UploadedFile(
                    __FILE__,
                    100,
                    UPLOAD_ERR_OK,
                    'foo2.txt',
                    'text/plain'
                ),
            ],
        ];

        $psr7Request = (new ServerRequest())->withUploadedFiles($files);
        $oauth2Request = RequestBridge::toOauth2($psr7Request);

        $this->assertSame(
            [
                'multi' => [
                    [
                        'name' => 'foo1.txt',
                        'type' => 'text/plain',
                        'size' => 100,
                        'tmp_name' => __FILE__,
                        'error' => UPLOAD_ERR_OK,
                    ],
                    [
                        'name' => 'foo2.txt',
                        'type' => 'text/plain',
                        'size' => 100,
                        'tmp_name' => __FILE__,
                        'error' => UPLOAD_ERR_OK,
                    ],
                ],
            ],
            $oauth2Request->files
        );
    }

    public function testToOAuth2RequestMethodPreserved(): void
    {
        $uri = 'https://example.com/foos';

        $psr7Request = new ServerRequest([], [], $uri, 'POST', 'php://input');

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('POST', $psr7Request->getMethod());
        $this->assertSame('POST', $oauth2Request->server('REQUEST_METHOD'));
    }

    public function testToOAuth2WithPhpAuthDigestHeader(): void
    {
        $uri = 'https://example.com/foos';

        $headers = [
            'Php-Auth-Digest' => ['some-digest-value'],
        ];

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', $headers);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('some-digest-value', $oauth2Request->headers('PHP_AUTH_DIGEST'));
        $this->assertNull($oauth2Request->headers('Php-Auth-Digest'));
    }

    public function testToOAuth2WithAuthTypeHeader(): void
    {
        $uri = 'https://example.com/foos';

        $headers = [
            'Auth-Type' => ['Basic'],
        ];

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', $headers);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('Basic', $oauth2Request->headers('AUTH_TYPE'));
        $this->assertNull($oauth2Request->headers('Auth-Type'));
    }

    public function testToOAuth2GetRequestWithQueryParams(): void
    {
        $uri = 'https://example.com/resource?grant_type=client_credentials&scope=read';

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', [], [], [
            'grant_type' => 'client_credentials',
            'scope' => 'read',
        ]);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('client_credentials', $oauth2Request->query('grant_type'));
        $this->assertSame('read', $oauth2Request->query('scope'));
    }

    public function testToOAuth2WithAttributes(): void
    {
        $uri = 'https://example.com/foos';

        $psr7Request = (new ServerRequest([], [], $uri, 'GET', 'php://input'))
            ->withAttribute('route', 'test-route')
            ->withAttribute('user_id', 42);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('test-route', $oauth2Request->attributes['route']);
        $this->assertSame(42, $oauth2Request->attributes['user_id']);
    }

    public function testToOAuth2WithNoFiles(): void
    {
        $uri = 'https://example.com/foos';

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input');

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame([], $oauth2Request->files);
    }

    public function testToOAuth2WithNullParsedBody(): void
    {
        $uri = 'https://example.com/foos';

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input');

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        // getParsedBody() returns null for GET requests, cast to array should give empty array
        $this->assertSame([], $oauth2Request->request);
    }

    public function testToOAuth2WithScalarHeaderValue(): void
    {
        // Test that non-mapped headers with array values are imploded correctly
        $uri = 'https://example.com/foos';

        $headers = [
            'X-Custom-Header' => ['value1', 'value2'],
        ];

        $psr7Request = new ServerRequest([], [], $uri, 'GET', 'php://input', $headers);

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('value1, value2', $oauth2Request->headers('X-Custom-Header'));
    }

    public function testToOAuth2WithEmptyBody(): void
    {
        $uri = 'https://example.com/foos';

        $psr7Request = new ServerRequest([], [], $uri, 'DELETE', 'php://input');

        $oauth2Request = RequestBridge::toOAuth2($psr7Request);

        $this->assertSame('', $oauth2Request->getContent());
        $this->assertSame('DELETE', $oauth2Request->server('REQUEST_METHOD'));
    }
}
