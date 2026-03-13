<?php

namespace ChadicusTest\Slim\OAuth2\Http;

use Chadicus\Slim\OAuth2\Http\ResponseBridge;
use OAuth2\Response;
use PHPUnit\Framework\TestCase;

final class ResponseBridgeTest extends TestCase
{
    public function testFromOAuth2BasicResponse(): void
    {
        $oauth2Response = new Response(
            ['foo' => 'bar', 'abc' => '123'],
            200,
            ['Content-Type' => 'application/json', 'Accept-Encoding' => 'gzip, deflate']
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(200, $slimResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => [
                    'application/json',
                ],
                'Accept-Encoding' => [
                    'gzip',
                    'deflate',
                ],
            ],
            $slimResponse->getHeaders()
        );

        $this->assertSame(json_encode(['foo' => 'bar', 'abc' => '123']), (string)$slimResponse->getBody());
    }

    public function testFromOAuth2EmptyBody(): void
    {
        $oauth2Response = new Response(
            [],
            204,
            ['Content-Type' => 'application/json']
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(204, $slimResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => [
                    'application/json',
                ],
            ],
            $slimResponse->getHeaders()
        );

        $this->assertSame('', (string)$slimResponse->getBody());
    }

    public function testFromOAuth2WritableEmptyBody(): void
    {
        $oauth2Response = new Response(
            [],
            204,
            ['Content-Type' => 'application/json']
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $slimResponse->getBody()->write('I was here');

        $this->assertSame(204, $slimResponse->getStatusCode());
        $this->assertSame(
            [
                'Content-Type' => [
                    'application/json',
                ],
            ],
            $slimResponse->getHeaders()
        );

        $this->assertSame('I was here', (string)$slimResponse->getBody());
    }

    public function testFromOAuth2ErrorResponse(): void
    {
        $oauth2Response = new Response(
            ['error' => 'invalid_grant', 'error_description' => 'The access token provided is expired'],
            401,
            ['Content-Type' => 'application/json']
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(401, $slimResponse->getStatusCode());

        $body = json_decode((string)$slimResponse->getBody(), true);
        $this->assertSame('invalid_grant', $body['error']);
        $this->assertSame('The access token provided is expired', $body['error_description']);
    }

    public function testFromOAuth2WithNoHeaders(): void
    {
        $oauth2Response = new Response(
            ['test' => 'value'],
            200,
            []
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(200, $slimResponse->getStatusCode());
        $this->assertSame([], $slimResponse->getHeaders());
        $this->assertSame(json_encode(['test' => 'value']), (string)$slimResponse->getBody());
    }

    public function testFromOAuth2RedirectResponse(): void
    {
        $oauth2Response = new Response(
            [],
            302,
            ['Location' => 'https://example.com/callback?code=abc123']
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(302, $slimResponse->getStatusCode());
        $this->assertSame(['https://example.com/callback?code=abc123'], $slimResponse->getHeader('Location'));
        $this->assertSame('', (string)$slimResponse->getBody());
    }

    public function testFromOAuth2ReturnsPsrResponseInterface(): void
    {
        $oauth2Response = new Response();

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $slimResponse);
    }

    public function testFromOAuth2MultipleHeaderValues(): void
    {
        $oauth2Response = new Response(
            ['data' => 'test'],
            200,
            [
                'X-Custom' => 'val1, val2, val3',
                'Cache-Control' => 'no-cache, no-store',
            ]
        );

        $slimResponse = ResponseBridge::fromOAuth2($oauth2Response);

        $this->assertSame(['val1', 'val2', 'val3'], $slimResponse->getHeader('X-Custom'));
        $this->assertSame(['no-cache', 'no-store'], $slimResponse->getHeader('Cache-Control'));
    }
}
