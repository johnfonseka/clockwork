<?php

declare(strict_types=1);

namespace Clockwork\Tests\Unit;

use Clockwork\Auth\GoogleTokenVerifier;
use Clockwork\Auth\TokenVerificationException;
use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\TestCase;

/**
 * Verifies GoogleTokenVerifier end-to-end without any network: a local RSA
 * keypair stands in for Google's signing key, its public half is written to the
 * verifier's JWKS cache file, and tokens are minted with the private half.
 */
final class GoogleTokenVerifierTest extends TestCase
{
    private const ISSUER = 'https://accounts.google.com';
    private const CLIENT_ID = '123-abc.apps.googleusercontent.com';
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

    public function test_valid_verified_token_returns_claims(): void
    {
        $token = $this->mint(['sub' => 'google-123', 'email' => 'user@gmail.com', 'email_verified' => true]);
        $claims = $this->verifier()->verify($token);

        $this->assertSame('google-123', $claims['sub']);
        $this->assertSame('user@gmail.com', $claims['email']);
        $this->assertTrue($claims['email_verified']);
    }

    public function test_email_verified_as_string_true_is_accepted(): void
    {
        // Google sometimes serialises the claim as the string "true".
        $claims = $this->verifier()->verify($this->mint(['sub' => 'g', 'email' => 'u@gmail.com', 'email_verified' => 'true']));
        $this->assertTrue($claims['email_verified']);
    }

    public function test_unverified_email_reports_false(): void
    {
        $claims = $this->verifier()->verify($this->mint(['sub' => 'g', 'email' => 'u@gmail.com', 'email_verified' => false]));
        $this->assertFalse($claims['email_verified']);
    }

    public function test_missing_email_verified_reports_false(): void
    {
        $claims = $this->verifier()->verify($this->mint(['sub' => 'g', 'email' => 'u@gmail.com']));
        $this->assertFalse($claims['email_verified']);
    }

    public function test_both_google_issuer_spellings_are_accepted(): void
    {
        foreach (['accounts.google.com', 'https://accounts.google.com'] as $issuer) {
            $claims = $this->verifier()->verify($this->mint(['sub' => 'g', 'iss' => $issuer]));
            $this->assertSame('g', $claims['sub']);
        }
    }

    public function test_wrong_audience_is_rejected(): void
    {
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($this->mint(['sub' => 'g', 'aud' => 'someone-else.apps.googleusercontent.com']));
    }

    public function test_wrong_issuer_is_rejected(): void
    {
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($this->mint(['sub' => 'g', 'iss' => 'https://appleid.apple.com']));
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($this->mint(['sub' => 'g', 'exp' => time() - 60]));
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
        $token = JWT::encode($this->payload(['sub' => 'g']), $attacker, 'RS256', self::KID);

        $this->expectException(TokenVerificationException::class);
        $this->verifier()->verify($token);
    }

    // MARK: helpers

    private function verifier(): GoogleTokenVerifier
    {
        return new GoogleTokenVerifier(self::CLIENT_ID, $this->cacheFile);
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

    /**
     * @return array<string,mixed>
     */
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

    /** @param array<string,mixed> $jwks */
    private function writeJwks(array $jwks): string
    {
        $file = tempnam(sys_get_temp_dir(), 'gjwks');
        file_put_contents($file, json_encode($jwks));
        $this->tempFiles[] = $file;

        return $file;
    }

    private function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
