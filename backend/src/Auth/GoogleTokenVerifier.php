<?php

declare(strict_types=1);

namespace Clockwork\Auth;

/**
 * Verifies Google Sign-In identity tokens (OpenID Connect JWTs).
 *
 * Google accepts two spellings of its issuer claim. Unlike Apple, Google's email
 * is only trustworthy when the token's `email_verified` claim is true, so it is
 * returned alongside the email for the caller's account-linking decision.
 */
final class GoogleTokenVerifier extends OidcJwtVerifier
{
    /** @var list<string> */
    public const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];
    private const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(string $clientId, string $cacheFile = '/tmp/google_jwks.json')
    {
        parent::__construct($clientId, self::KEYS_URL, $cacheFile);
    }

    protected function issuers(): array
    {
        return self::ISSUERS;
    }

    /**
     * @return array{sub:string,email:?string,email_verified:bool}
     *
     * @throws TokenVerificationException
     */
    public function verify(string $identityToken): array
    {
        $decoded = $this->decodeAndValidate($identityToken);

        $email = (isset($decoded->email) && is_string($decoded->email)) ? $decoded->email : null;

        return [
            'sub' => $decoded->sub,
            'email' => $email,
            'email_verified' => self::isVerified($decoded->email_verified ?? null),
        ];
    }

    /** Google may encode `email_verified` as a boolean or the string "true". */
    private static function isVerified(mixed $value): bool
    {
        return $value === true || $value === 'true';
    }
}
