<?php

declare(strict_types=1);

namespace Clockwork\Auth;

use Clockwork\Config;

/**
 * Provider-agnostic identity-token verification.
 *
 * Reads the token's (unverified) `iss` claim purely to route it to the right
 * provider verifier — the chosen verifier then fully validates the signature and
 * all claims. Returns a normalised identity so the rest of the app never has to
 * care which provider a user signed in with.
 */
final class TokenVerifier
{
    /**
     * @return array{provider:string,sub:string,email:?string,email_verified:bool}
     *
     * @throws TokenVerificationException
     */
    public function verify(string $token): array
    {
        $issuer = self::peekIssuer($token);

        if ($issuer === AppleTokenVerifier::ISSUER) {
            $claims = (new AppleTokenVerifier(Config::require('APPLE_CLIENT_ID')))->verify($token);

            return [
                'provider' => 'apple',
                'sub' => $claims['sub'],
                'email' => $claims['email'],
                // Apple only ever returns a verified email.
                'email_verified' => $claims['email'] !== null,
            ];
        }

        if (in_array($issuer, GoogleTokenVerifier::ISSUERS, true)) {
            $claims = (new GoogleTokenVerifier(Config::require('GOOGLE_CLIENT_ID')))->verify($token);

            return [
                'provider' => 'google',
                'sub' => $claims['sub'],
                'email' => $claims['email'],
                'email_verified' => $claims['email_verified'],
            ];
        }

        throw new TokenVerificationException('Unrecognised token issuer');
    }

    /**
     * Reads the `iss` claim from the token payload without verifying the
     * signature. Safe: it only decides which verifier runs, and that verifier
     * re-checks `iss` against its trusted issuers after verifying the signature.
     */
    private static function peekIssuer(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        return (is_array($payload) && isset($payload['iss']) && is_string($payload['iss']))
            ? $payload['iss']
            : null;
    }
}
