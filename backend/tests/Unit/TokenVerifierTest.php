<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Auth\TokenVerifier;
use Clockwork\Auth\TokenVerificationException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

/**
 * The dispatcher routes on the token's `iss` claim. Tokens from an unknown issuer
 * must be rejected before any provider verifier (or network) is touched.
 */
final class TokenVerifierTest extends TestCase
{
    public function test_unknown_issuer_is_rejected(): void
    {
        $token = JWT::encode(
            ['iss' => 'https://evil.example.com', 'sub' => 'x', 'exp' => time() + 60],
            'secret',
            'HS256'
        );

        $this->expectException(TokenVerificationException::class);
        $this->expectExceptionMessage('Unrecognised token issuer');
        (new TokenVerifier())->verify($token);
    }

    public function test_malformed_token_is_rejected(): void
    {
        $this->expectException(TokenVerificationException::class);
        (new TokenVerifier())->verify('not-a-jwt');
    }
}
