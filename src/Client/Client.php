<?php

declare(strict_types=1);

namespace Gadget\Http\Client;

use Gadget\Http\Exception\ClientException;
use Gadget\Http\Exception\RequestException;
use Gadget\Http\Exception\ResponseException;
use Gadget\Http\Message\MessageFactoryInterface;
use Gadget\Http\Message\RequestFactoryInterface;
use Gadget\Http\Message\ResponseHandlerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class Client implements ClientInterface
{
    /** @var list<MiddlewareInterface> $middleware */
    private array $middleware = [];


    /**
     * @param ClientInterface $client
     * @param MessageFactoryInterface $messageFactory
     * @param MiddlewareInterface ...$middleware
     */
    public function __construct(
        private ClientInterface $client,
        private MessageFactoryInterface $messageFactory,
        MiddlewareInterface ...$middleware
    ) {
        $this->middleware = array_values($middleware);
    }


    /**
     * @template TResponse
     * @param RequestFactoryInterface $requestFactory
     * @param ResponseHandlerInterface<TResponse> $responseHandler
     * @return TResponse
     */
    public function sendHttpRequest(
        RequestFactoryInterface $requestFactory,
        ResponseHandlerInterface $responseHandler
    ): mixed {
        try {
            try {
                $request = $requestFactory->createRequest($this->messageFactory);
            } catch (\Throwable $requestError) {
                throw new RequestException($requestError);
            }

            try {
                $requestHandler = new RequestHandler(
                    $this,
                    $requestFactory->getMiddleware([])
                );
                $response = $requestHandler->handle($request);
            } catch (\Throwable $clientError) {
                throw new ClientException($request, $clientError);
            }

            try {
                $returnVal = $responseHandler->handleResponse($response);
            } catch (\Throwable $responseError) {
                throw new ResponseException($request, $response, $responseError);
            }
        } catch (\Throwable $error) {
            $returnVal = $responseHandler->handleError(
                $error,
                $request ?? null,
                $response  ?? null
            );
        }

        return $returnVal;
    }


    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $requestHandler = new RequestHandler(
            $this->client,
            $this->middleware
        );
        $request = $this->messageFactory->toServerRequest($request);

        return $requestHandler->handle($request);
    }
}
