<?php

declare(strict_types=1);

namespace Clockwork\Controllers;

use Clockwork\Auth\AppleTokenVerifier;
use Clockwork\Auth\TokenVerificationException;
use Clockwork\Config;
use Clockwork\Database;
use Clockwork\Http\Json;
use Clockwork\UserRepository;

/**
 * POST /api/auth — verifies a Sign in with Apple identity token and resolves the
 * caller to an internal user record (creating it on first sign-in).
 *
 * The token may be supplied as `Authorization: Bearer <token>` or as an
 * `identity_token` field in the JSON body.
 */
final class AuthController
{
    public function authenticate(): void
    {
        $token = $this->bearerToken();
        if ($token === null) {
            $bodyToken = Json::body()['identity_token'] ?? null;
            $token = is_string($bodyToken) ? $bodyToken : null;
        }

        if ($token === null || $token === '') {
            Json::error('Missing identity token', 400);

            return;
        }

        $verifier = new AppleTokenVerifier(Config::require('APPLE_CLIENT_ID'));
        try {
            $claims = $verifier->verify($token);
        } catch (TokenVerificationException $e) {
            Json::error($e->getMessage(), 401);

            return;
        }

        $users = new UserRepository(Database::connection());
        $user = $users->upsertByAppleId($claims['sub'], $claims['email']);

        Json::send(['user' => $user]);
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
