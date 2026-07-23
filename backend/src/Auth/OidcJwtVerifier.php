<?php

declare(strict_types=1);

namespace Clockwork\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Shared machinery for verifying an OpenID Connect identity token (JWT) against a
 * provider's public JWK set.
 *
 * Handles fetching + caching the provider's keys, validating the RS256 signature,
 * and checking the standard `iss` / `aud` / `sub` claims. Provider-specific
 * subclasses (Apple, Google) supply the keys URL and accepted issuers, and shape
 * the returned claims. Keys are cached on disk to avoid a network round-trip per
 * request; a stale cache is used as a fallback if the provider is unreachable.
 */
abstract class OidcJwtVerifier
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly string $clientId,
        private readonly string $keysUrl,
        private readonly string $cacheFile,
    ) {
    }

    /**
     * The issuers this provider's tokens may carry (`iss` must match one).
     *
     * @return list<string>
     */
    abstract protected function issuers(): array;

    /**
     * Decodes the token, verifies its signature against the provider's JWKS, and
     * validates the issuer, audience, and subject claims.
     *
     * @throws TokenVerificationException
     */
    final protected function decodeAndValidate(string $token): object
    {
        try {
            $keys = JWK::parseKeySet($this->fetchKeys());
            $decoded = JWT::decode($token, $keys);
        } catch (TokenVerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TokenVerificationException('Invalid identity token: ' . $e->getMessage(), 0, $e);
        }

        if (!in_array($decoded->iss ?? null, $this->issuers(), true)) {
            throw new TokenVerificationException('Unexpected token issuer');
        }
        if (($decoded->aud ?? null) !== $this->clientId) {
            throw new TokenVerificationException('Token audience does not match the configured client id');
        }

        $sub = $decoded->sub ?? null;
        if (!is_string($sub) || $sub === '') {
            throw new TokenVerificationException('Token is missing a subject');
        }

        return $decoded;
    }

    /**
     * @return array<string,mixed>
     *
     * @throws TokenVerificationException
     */
    private function fetchKeys(): array
    {
        $cached = $this->readCache(ignoreTtl: false);
        if ($cached !== null) {
            return $cached;
        }

        $raw = @file_get_contents($this->keysUrl);
        if ($raw === false) {
            // Network failure: fall back to a stale cache if we have one.
            $stale = $this->readCache(ignoreTtl: true);
            if ($stale !== null) {
                return $stale;
            }
            throw new TokenVerificationException('Unable to fetch provider public keys');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['keys'])) {
            throw new TokenVerificationException('Malformed provider key set');
        }

        @file_put_contents($this->cacheFile, $raw);

        return $decoded;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function readCache(bool $ignoreTtl): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }
        if (!$ignoreTtl && (time() - (int) filemtime($this->cacheFile)) > self::CACHE_TTL) {
            return null;
        }

        $raw = @file_get_contents($this->cacheFile);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
