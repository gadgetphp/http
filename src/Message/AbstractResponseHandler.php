<?php

declare(strict_types=1);

namespace Gadget\Http\Message;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @template TResponse
 * @implements ResponseHandlerInterface<TResponse>
 */
abstract class AbstractResponseHandler implements ResponseHandlerInterface
{
    /**
     * @param ResponseInterface $response
     * @return TResponse
     */
    abstract public function handleResponse(ResponseInterface $response): mixed;


    /**
     * @param \Throwable $error
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     * @return TResponse
     */
    public function handleError(
        \Throwable $error,
        ServerRequestInterface|null $request = null,
        ResponseInterface|null $response = null
    ): mixed {
        throw $error;
    }
}
