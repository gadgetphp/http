<?php

declare(strict_types=1);

namespace Gadget\Http\Exception;

class RequestException extends HttpException
{
    public function __construct(
        \Throwable|null $t = null,
        int $code = 0
    ) {
        parent::__construct("Error building request", $t, $code);
    }
}
