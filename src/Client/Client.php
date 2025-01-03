<?php

declare(strict_types=1);

namespace Gadget\Http\Client;

use Gadget\Http\Client\ClientInterface;
use Gadget\Http\Exception\ClientException;
use Gadget\Http\Exception\RequestException;
use Gadget\Http\Exception\ResponseException;
use Gadget\Http\Message\MessageFactoryInterface;
use Gadget\Http\Message\RequestFactoryInterface;
use Gadget\Http\Message\ResponseHandlerInterface;
use Gadget\Lang\Cast;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Client implements ClientInterface, PsrClientInterface
{
    /** @var list<MiddlewareInterface> $middleware */
    private array $middleware = [];


    /**
     * @param PsrClientInterface $client
     * @param MessageFactoryInterface $messageFactory
     * @param MiddlewareInterface ...$middleware
     */
    public function __construct(
        private PsrClientInterface $client,
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
    final public function sendHttpRequest(
        RequestFactoryInterface $requestFactory,
        ResponseHandlerInterface $responseHandler
    ): mixed {
        try {
            try {
                $request = $this->createRequest($requestFactory);
            } catch (\Throwable $requestError) {
                throw new RequestException($requestError);
            }

            try {
                $response = $this->sendRequest($request);
            } catch (\Throwable $clientError) {
                throw new ClientException($request, $clientError);
            }

            try {
                return $this->handleResponse($responseHandler, $response);
            } catch (\Throwable $responseError) {
                throw new ResponseException($request, $response, $responseError);
            }
        } catch (\Throwable $error) {
            return $this->handleError(
                $responseHandler,
                $error,
                $request ?? null,
                $response  ?? null
            );
        }
    }


    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    final public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $request = $this->toServerRequest($request);
        return $this
            ->createRequestHandler($request)
            ->handle($request);
    }


    /**
     * @param RequestFactoryInterface $requestFactory
     * @return ServerRequestInterface
     */
    protected function createRequest(RequestFactoryInterface $requestFactory): ServerRequestInterface
    {
        return $requestFactory
            ->createRequest($this->messageFactory)
            ->withAttribute(
                MiddlewareInterface::class,
                $requestFactory->getMiddleware($this->middleware)
            );
    }


    /**
     * @param ServerRequestInterface $request
     * @return RequestHandlerInterface
     */
    protected function createRequestHandler(
        ServerRequestInterface $request
    ): RequestHandlerInterface {
        $middleware = $request->getAttribute(MiddlewareInterface::class);
        return new RequestHandler(
            $this->client,
            is_array($middleware)
                ? array_values(array_filter(
                    $middleware,
                    fn(mixed $v) => $v instanceof MiddlewareInterface
                ))
                : $this->middleware
        );
    }


    /**
     * @template TResponse
     * @param ResponseHandlerInterface<TResponse> $responseHandler
     * @param ResponseInterface $response
     * @return TResponse
     */
    protected function handleResponse(
        ResponseHandlerInterface $responseHandler,
        ResponseInterface $response
    ): mixed {
        return $responseHandler->handleResponse($response);
    }


    /**
     * @template TResponse
     * @param ResponseHandlerInterface<TResponse> $responseHandler
     * @param \Throwable $error
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     * @return TResponse
     */
    protected function handleError(
        ResponseHandlerInterface $responseHandler,
        \Throwable $error,
        ServerRequestInterface|null $request = null,
        ResponseInterface|null $response = null
    ): mixed {
        return $responseHandler->handleError($error, $request, $response);
    }


    /**
     * @param RequestInterface $request
     * @return ServerRequestInterface
     */
    protected function toServerRequest(RequestInterface $request): ServerRequestInterface
    {
        return $this->messageFactory->toServerRequest($request);
    }
}
