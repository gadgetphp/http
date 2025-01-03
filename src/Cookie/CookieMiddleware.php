<?php

declare(strict_types=1);

namespace Gadget\Http\Cookie;

use Gadget\Cache\CacheInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CookieMiddleware implements MiddlewareInterface
{
    /**
     * @var CookieJarInterface|null $cookieJar
     */
    protected CookieJarInterface|null $cookieJar = null;


    /**
     * @param CacheInterface $cache
     */
    public function __construct(private CacheInterface $cache)
    {
        $this->cache = $cache->withNamespace(self::class);
    }


    public function __destruct()
    {
        if ($this->cookieJar !== null) {
            $this->cache->set('cookieJar', $this->cookieJar->clearExpired());
        }
    }


    /**
     * @return CookieJarInterface
     */
    protected function getCookieJar(): CookieJarInterface
    {
        $this->cookieJar ??= $this->cache->getT(
            'cookieJar',
            fn(mixed $v) => $v instanceof CookieJarInterface ? $v : null
        );
        return $this->cookieJar ?? throw new \RuntimeException();
    }


    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cookies = $this->getCookieJar()->getMatches(
            $request->getUri()->getScheme(),
            $request->getUri()->getHost(),
            $request->getUri()->getPath()
        );
        if (count($cookies) > 0) {
            $request = $request->withHeader('Cookie', implode('; ', $cookies));
        }

        $response = $handler->handle($request);

        $cookies = $response->getHeader('Set-Cookie');
        foreach ($cookies as $c) {
            $cookie = Cookie::fromString($c);
            if ($cookie->getDomain() === null) {
                $cookie->setDomain($request->getUri()->getHost());
            }
            if (strpos($cookie->getPath(), '/') !== 0) {
                $cookie->setPath(Cookie::getCookiePath($request->getUri()->getPath()));
            }
            if (!$cookie->matchesDomain($request->getUri()->getHost())) {
                continue;
            }
            $this->getCookieJar()->setCookie($cookie);
        }

        return $response;
    }
}
