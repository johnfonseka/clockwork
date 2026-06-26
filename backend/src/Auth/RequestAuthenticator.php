<?php

declare(strict_types=1);

namespace Clockwork\Auth;

use Clockwork\Config;
use Clockwork\UserRepository;
use PDO;

/**
 * Resolves the calling user for a request.
 *
 * Normally this verifies a Sign in with Apple identity token
 * (`Authorization: Bearer <token>`). When `APP_ENV=development`, an
 * `X-Dev-User: <id>` header bypasses Apple verification and resolves/creates a
 * user directly — this lets the backend be exercised end-to-end (and the
 * frontend developed) without a live Apple token.
 */
final class RequestAuthenticator
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @throws TokenVerificationException when the caller cannot be authenticated
     */
    public function resolveUserId(): int
    {
        $users = new UserRepository($this->db);

        if (Config::isDev()) {
            $devUser = $_SERVER['HTTP_X_DEV_USER'] ?? null;
            if (is_string($devUser) && $devUser !== '') {
                return $users->upsertByAppleId($devUser, null)['id'];
            }
        }

        $token = $this->bearerToken();
        if ($token === null) {
            throw new TokenVerificationException('Missing bearer token');
        }

        $verifier = new AppleTokenVerifier(Config::require('APPLE_CLIENT_ID'));
        $claims = $verifier->verify($token);

        return $users->upsertByAppleId($claims['sub'], $claims['email'])['id'];
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
