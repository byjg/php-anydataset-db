<?php

namespace Test\Helpers;

use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client that answers with a queue of canned Cloudflare D1 responses and records every
 * request it received, so the tests can assert what the driver actually sent over the wire.
 */
class D1FakeClient implements ClientInterface
{
    /**
     * @var ResponseInterface[]
     */
    private array $queue = [];

    /**
     * @var RequestInterface[]
     */
    private array $requests = [];

    /**
     * @var string[] The decoded body of every request, in order
     */
    private array $bodies = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $this->bodies[] = (string)$request->getBody();

        if (empty($this->queue)) {
            throw new RuntimeException(
                'D1FakeClient received an unexpected request to ' . $request->getUri()
                . ' - no response was queued'
            );
        }

        return array_shift($this->queue);
    }

    public function queueResponse(ResponseInterface $response): static
    {
        $this->queue[] = $response;

        return $this;
    }

    public function queueRaw(int $statusCode, string $body): static
    {
        return $this->queueResponse((new Response($statusCode))->withBody(new MemoryStream($body)));
    }

    /**
     * Queue a successful D1 envelope.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $meta
     */
    public function queueSuccess(array $rows = [], array $meta = []): static
    {
        return $this->queueRaw(200, (string)json_encode([
            'success' => true,
            'errors' => [],
            'messages' => [],
            'result' => [
                [
                    'success' => true,
                    'results' => $rows,
                    'meta' => $meta + [
                        'changed_db' => true,
                        'changes' => count($rows),
                        'duration' => 1,
                        'last_row_id' => 0,
                        'rows_read' => count($rows),
                        'rows_written' => 0,
                    ],
                ],
            ],
        ]));
    }

    /**
     * Queue a D1 error envelope.
     */
    public function queueError(string $message, int $code = 7500, int $statusCode = 400): static
    {
        return $this->queueRaw($statusCode, (string)json_encode([
            'success' => false,
            'errors' => [['code' => $code, 'message' => $message]],
            'messages' => [],
            'result' => null,
        ]));
    }

    public function getLastRequest(): RequestInterface
    {
        if (empty($this->requests)) {
            throw new RuntimeException('No request was sent to D1FakeClient');
        }

        return $this->requests[count($this->requests) - 1];
    }

    /**
     * The JSON body of the last request, decoded.
     *
     * @return array<string, mixed>
     */
    public function getLastBody(): array
    {
        if (empty($this->bodies)) {
            throw new RuntimeException('No request was sent to D1FakeClient');
        }

        $decoded = json_decode($this->bodies[count($this->bodies) - 1], true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }

    public function getPendingCount(): int
    {
        return count($this->queue);
    }
}
