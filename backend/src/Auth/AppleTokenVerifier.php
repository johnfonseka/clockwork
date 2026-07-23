<?php

declare(strict_types=1);

namespace Clockwork\Auth;

/**
 * Verifies Sign in with Apple identity tokens (JWTs).
 *
 * Apple always delivers a verified email (a real address or a private-relay one),
 * so the caller may treat the returned email as verified.
 */
final class AppleTokenVerifier extends OidcJwtVerifier
{
    public const ISSUER = 'https://appleid.apple.com';
    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public function __construct(string $clientId, string $cacheFile = '/tmp/apple_jwks.json')
    {
        parent::__construct($clientId, self::KEYS_URL, $cacheFile);
    }

    protected function issuers(): array
    {
        return [self::ISSUER];
    }

    /**
     * @return array{sub:string,email:?string}
     *
     * @throws TokenVerificationException
     */
    public function verify(string $identityToken): array
    {
        $decoded = $this->decodeAndValidate($identityToken);

        $email = (isset($decoded->email) && is_string($decoded->email)) ? $decoded->email : null;

        return ['sub' => $decoded->sub, 'email' => $email];
    }
}
