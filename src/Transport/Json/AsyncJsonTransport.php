<?php

declare(strict_types=1);

namespace Phpro\HttpTools\Transport\Json;

use function Amp\call;
use Amp\Promise;
use Generator;
use Http\Client\HttpAsyncClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Phpro\HttpTools\Async\HttplugPromiseAdapter;
use Phpro\HttpTools\Request\RequestInterface;
use Phpro\HttpTools\Transport\AsyncTransportInterface;
use Phpro\HttpTools\Uri\UriBuilderInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use function Safe\json_decode;
use function Safe\json_encode;

/**
 * @implements AsyncTransportInterface<array|null, array>
 */
final class AsyncJsonTransport implements AsyncTransportInterface
{
    private HttpAsyncClient $client;
    private UriBuilderInterface $uriBuilder;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        HttpAsyncClient $client,
        UriBuilderInterface $uriBuilder,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->client = $client;
        $this->uriBuilder = $uriBuilder;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public static function createWithAutodiscoveredPsrFactories(
        HttpAsyncClient $client,
        UriBuilderInterface $uriBuilder
    ): self {
        return new self(
            $client,
            $uriBuilder,
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );
    }

    /**
     * @param RequestInterface<array|null> $request
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Safe\Exceptions\JsonException
     *
     * @return Promise<array>
     */
    public function __invoke(RequestInterface $request): Promise
    {
        $httpRequest = $this->requestFactory->createRequest(
            $request->method(),
            ($this->uriBuilder)($request)
        )
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(
                null !== $request->body() ? json_encode($request->body()) : ''
            ));

        $httpPromise = $this->client->sendAsyncRequest($httpRequest);

        return call(
            static function () use ($httpPromise): Generator {
                $response = yield HttplugPromiseAdapter::adapt($httpPromise);

                return (array) json_decode((string) $response->getBody(), true);
            }
        );
    }
}
