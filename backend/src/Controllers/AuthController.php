<?php

declare(strict_types=1);

namespace Clockwork\Controllers;

use Clockwork\Auth\TokenVerificationException;
use Clockwork\Auth\TokenVerifier;
use Clockwork\Database;
use Clockwork\Http\Json;
use Clockwork\UserRepository;

/**
 * POST /api/auth — verifies an Apple or Google identity token and resolves the
 * caller to an internal user record (creating it on first sign-in, or linking the
 * provider onto an existing account with the same verified email).
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

        try {
            $identity = (new TokenVerifier())->verify($token);
        } catch (TokenVerificationException $e) {
            Json::error($e->getMessage(), 401);

            return;
        }

        $users = new UserRepository(Database::connection());
        $user = $users->resolveIdentity(
            $identity['provider'],
            $identity['sub'],
            $identity['email'],
            $identity['email_verified'],
        );

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
