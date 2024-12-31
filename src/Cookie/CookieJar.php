<?php

declare(strict_types=1);

namespace Gadget\Http\Cookie;

/** @implements \IteratorAggregate<string,CookieInterface> */
class CookieJar implements CookieJarInterface, \IteratorAggregate
{
    /** @var array<string,CookieInterface> $cookies */
    private $cookies = [];


    /**
     * @param (string|array<string,string|int|bool|null>|CookieInterface)[] $cookies
     * @param int|null $defaultMaxAge
     */
    public function __construct(
        array $cookies = [],
        private int|null $defaultMaxAge = 600
    ) {
        $this->setCookies($cookies);
    }


    /**
     * @return int
     */
    public function getDefaultMagAge(): int|null
    {
        return $this->defaultMaxAge;
    }


    /**
     * @param int|null $defaultMaxAge
     * @return static
     */
    public function setDefaultMagAge(int|null $defaultMaxAge): static
    {
        $this->defaultMaxAge = $defaultMaxAge;
        return $this;
    }


    /**
     * @return CookieInterface[]
     */
    public function getCookies(): array
    {
        return array_values($this->cookies);
    }


    /**
     * @param (string|array<string,string|int|bool|null>|CookieInterface)[] $cookies
     * @return static
     */
    public function setCookies(array $cookies): static
    {
        foreach ($cookies as $cookie) {
            $this->setCookie($cookie);
        }
        return $this;
    }


    /**
     * @param string $domain
     * @param string $path
     * @param string $name
     * @return CookieInterface
     */
    public function getCookie(string $domain, string $path, string $name): CookieInterface|null
    {
        return $this->cookies[Cookie::getCookieKey($domain, $path, $name)] ?? null;
    }


    /**
     * @param string|array<string,string|int|bool|null>|CookieInterface $cookie
     * @return bool
     */
    public function setCookie(string|array|CookieInterface $cookie): bool
    {
        $cookie = match (true) {
            is_string($cookie) => Cookie::fromString($cookie),
            is_array($cookie) => new Cookie($cookie),
            default => $cookie
        };

        if ($cookie->getExpires() === null) {
            $cookie->setMaxAge($this->defaultMaxAge);
        }

        $oldCookie = $this->cookies[$cookie->getKey()] ?? null;
        if ($oldCookie === null) {
            $this->cookies[$cookie->getKey()] = $cookie;
            return true;
        }

        if ($cookie->getExpires() > $oldCookie->getExpires()) {
            $this->cookies[$cookie->getKey()] = $cookie;
            return true;
        }

        if ($cookie->getValue() !== $oldCookie->getValue()) {
            $this->cookies[$cookie->getKey()] = $cookie;
            return true;
        }

        return false;
    }


    /**
     * @return \Traversable<string,CookieInterface>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->cookies;
    }


    /**
     * @param string $scheme
     * @param string $host
     * @param string $path
     * @return CookieInterface[]
     */
    public function getMatches(
        string $scheme,
        string $host,
        string $path
    ): array {
        return array_filter(
            $this->getCookies(),
            fn(CookieInterface $cookie): bool => $cookie->matches($scheme, $host, $path)
        );
    }


    /**
     * @return static
     */
    public function clearExpired(): static
    {
        $this->cookies = array_filter(
            $this->cookies,
            fn(CookieInterface $cookie): bool => $cookie->getExpires() !== null && !$cookie->isExpired()
        );
        return $this;
    }
}
