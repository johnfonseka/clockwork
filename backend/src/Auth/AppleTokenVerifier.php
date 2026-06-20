<?php

declare(strict_types=1);

namespace Clockwork\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Verifies Sign in with Apple identity tokens (JWTs).
 *
 * Fetches Apple's public JWK set, validates the token signature, and checks the
 * issuer and audience claims. Apple's keys are cached on disk to avoid a network
 * round-trip on every request; a stale cache is used as a fallback if Apple is
 * temporarily unreachable.
 */
final class AppleTokenVerifier
{
    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';
    private const ISSUER = 'https://appleid.apple.com';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly string $clientId,
        private readonly string $cacheFile = '/tmp/apple_jwks.json',
    ) {
    }

    /**
     * @return array{sub:string,email:?string}
     *
     * @throws TokenVerificationException
     */
    public function verify(string $identityToken): array
    {
        try {
            $keys = JWK::parseKeySet($this->fetchKeys());
            $decoded = JWT::decode($identityToken, $keys);
        } catch (TokenVerificationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TokenVerificationException('Invalid identity token: ' . $e->getMessage(), 0, $e);
        }

        if (($decoded->iss ?? null) !== self::ISSUER) {
            throw new TokenVerificationException('Unexpected token issuer');
        }
        if (($decoded->aud ?? null) !== $this->clientId) {
            throw new TokenVerificationException('Token audience does not match APPLE_CLIENT_ID');
        }

        $sub = $decoded->sub ?? null;
        if (!is_string($sub) || $sub === '') {
            throw new TokenVerificationException('Token is missing a subject');
        }

        $email = (isset($decoded->email) && is_string($decoded->email)) ? $decoded->email : null;

        return ['sub' => $sub, 'email' => $email];
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

        $raw = @file_get_contents(self::KEYS_URL);
        if ($raw === false) {
            // Network failure: fall back to a stale cache if we have one.
            $stale = $this->readCache(ignoreTtl: true);
            if ($stale !== null) {
                return $stale;
            }
            throw new TokenVerificationException('Unable to fetch Apple public keys');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['keys'])) {
            throw new TokenVerificationException('Malformed Apple key set');
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
