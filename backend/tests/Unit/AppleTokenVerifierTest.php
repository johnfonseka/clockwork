<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Auth\AppleTokenVerifier;
use Clockwork\Auth\TokenVerificationException;
use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * Verifies AppleTokenVerifier end-to-end without any network: a local RSA
 * keypair stands in for Apple's signing key, its public half is written to the
 * verifier's JWKS cache file, and tokens are minted with the private half.
 */
final class AppleTokenVerifierTest extends TestCase
{
    private const ISSUER = 'https://appleid.apple.com';
    private const CLIENT_ID = 'com.example.clockwork';
    private const KID = 'test-kid';

    private OpenSSLAsymmetricKey $privateKey;
    private string $cacheFile;
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $this->privateKey = $key;

        $details = openssl_pkey_get_details($this->privateKey);
        $this->cacheFile = $this->writeJwks($this->jwks($details['rsa']['n'], $details['rsa']['e']));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    public function test_valid_token_returns_claims(): void
    {
        $token = $this->mint(['sub' => 'apple-123', 'email' => 'user@example.com']);
        $claims = $this->verifier()->verify($token);

        $this->assertSame('apple-123', $claims['sub']);
        $this->assertSame('user@example.com', $claims['email']);
    }

    public function test_token_without_email_yields_null_email(): void
    {
        $claims = $this->verifier()->verify($this->mint(['sub' => 'apple-123']));
        $this->assertNull($claims['email']);
    }

    public function test_wrong_audience_is_rejected(): void
    {
        $token = $this->mint(['sub' => 'apple-123', 'aud' => 'com.someone.else']);
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($token);
    }

    public function test_wrong_issuer_is_rejected(): void
    {
        $token = $this->mint(['sub' => 'apple-123', 'iss' => 'https://evil.example.com']);
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($token);
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->mint(['sub' => 'apple-123', 'exp' => time() - 60]);
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($token);
    }

    public function test_missing_subject_is_rejected(): void
    {
        $this->expectExceptionMessage('subject');
        $this->verifier()->verify($this->mint([]));
    }

    public function test_signature_from_another_key_is_rejected(): void
    {
        $attacker = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        // Signed by the attacker's key, but the JWKS only trusts ours.
        $token = JWT::encode(
            $this->payload(['sub' => 'apple-123']),
            $attacker,
            'RS256',
            self::KID
        );

        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($token);
    }

    // MARK: helpers

    private function verifier(): AppleTokenVerifier
    {
        return new AppleTokenVerifier(self::CLIENT_ID, $this->cacheFile);
    }

    /** @param array<string,mixed> $overrides */
    private function mint(array $overrides): string
    {
        return JWT::encode($this->payload($overrides), $this->privateKey, 'RS256', self::KID);
    }

    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function payload(array $overrides): array
    {
        return array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);
    }

    private function jwks(string $modulus, string $exponent): array
    {
        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => self::KID,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->base64url($modulus),
                'e' => $this->base64url($exponent),
            ]],
        ];
    }

    private function writeJwks(array $jwks): string
    {
        $file = tempnam(sys_get_temp_dir(), 'jwks');
        file_put_contents($file, json_encode($jwks));
        $this->tempFiles[] = $file;

        return $file;
    }

    private function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
