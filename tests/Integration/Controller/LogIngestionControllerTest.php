<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Message\LogMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class LogIngestionControllerTest extends WebTestCase
{
    // 202 Accepted
    public function testValidBatchReturns202(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->validPayload(2)),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('accepted', $body['status']);
        self::assertSame(2, $body['logs_count']);
        self::assertMatchesRegularExpression('/^batch_[0-9a-f]{32}$/', $body['batch_id']);
    }

    public function testMessagesAreDispatchedForEachLog(): void
    {
        $client    = static::createClient();
        $container = static::getContainer();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->validPayload(3)),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        self::assertCount(3, $transport->get());
    }

    public function testDispatchedMessageContainsMetadata(): void
    {
        $client    = static::createClient();
        $container = static::getContainer();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->validPayload(1)),
        );

        $responseBody = json_decode($client->getResponse()->getContent(), true);
        $batchId      = $responseBody['batch_id'];

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $envelopes = $transport->get();

        self::assertCount(1, $envelopes);

        /** @var LogMessage $message */
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(LogMessage::class, $message);
        self::assertSame($batchId, $message->batchId);
        self::assertSame(0, $message->retryCount);
        self::assertNotEmpty($message->publishedAt);
    }

    // 400 Bad Request
    public function testMissingLogsFieldReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['data' => []]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertNotEmpty($body['errors']);
    }

    public function testMissingRequiredMsgLogFieldReturns400(): void
    {
        $client = static::createClient();

        $payload = [
            'logs' => [
                ['timestamp' => '2026-02-26T10:30:45Z', 'level' => 'info', 'service' => 'svc'],
            ],
        ];

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('message', implode(' ', $body['errors']));
    }

    public function testOver1000LogsReturns400(): void
    {
        $client  = static::createClient();
        $payload = $this->validPayload(1001);

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testInvalidJsonReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid-json',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testWrongContentTypeReturns415(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: json_encode($this->validPayload(1)),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    public function testEmptyBatchReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/logs/ingest',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['logs' => []]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    //  helpers
    private function validEntry(array $overrides = []): array
    {
        return array_merge([
            'timestamp' => '2026-02-26T10:30:45Z',
            'level'     => 'error',
            'service'   => 'auth-service',
            'message'   => 'User authentication failed',
            'context'   => ['user_id' => 123, 'ip' => '192.168.1.1'],
            'trace_id'  => 'abc123def456',
        ], $overrides);
    }

    private function validPayload(int $count): array
    {
        return ['logs' => array_fill(0, $count, $this->validEntry())];
    }
}
