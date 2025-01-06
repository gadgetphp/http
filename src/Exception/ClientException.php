<?php

declare(strict_types=1);

namespace Gadget\Http\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClientException extends HttpException implements ClientExceptionInterface
{
    public function __construct(
        ServerRequestInterface $request,
        \Throwable|null $t = null,
        int $code = 0
    ) {
        parent::__construct([
            "Error sending request: %s %s",
            $request->getMethod(),
            $request->getUri()
        ], $t, $code);
    }
}
