<?php

declare(strict_types=1);

namespace Clockwork\Tests\E2E;

use Clockwork\UserRepository;

/**
 * Exercises UserRepository::resolveIdentity against the real test database —
 * multi-provider account creation and the verified-email linking rule.
 */
final class UserIdentityE2ETest extends E2ETestCase
{
    private function users(): UserRepository
    {
        return new UserRepository($this->db());
    }

    public function test_new_provider_subject_creates_a_user(): void
    {
        $user = $this->users()->resolveIdentity('google', 'g-1', 'a@gmail.com', true);

        $this->assertSame('g-1', $user['google_user_id']);
        $this->assertNull($user['apple_user_id']);
        $this->assertSame('a@gmail.com', $user['email']);
    }

    public function test_same_subject_returns_same_user(): void
    {
        $first = $this->users()->resolveIdentity('google', 'g-1', 'a@gmail.com', true);
        $again = $this->users()->resolveIdentity('google', 'g-1', 'a@gmail.com', true);

        $this->assertSame($first['id'], $again['id']);
    }

    public function test_verified_email_links_google_onto_existing_apple_account(): void
    {
        $apple = $this->users()->resolveIdentity('apple', 'a-1', 'shared@icloud.com', true);
        $google = $this->users()->resolveIdentity('google', 'g-1', 'shared@icloud.com', true);

        // Same account: one row now carrying both provider subjects.
        $this->assertSame($apple['id'], $google['id']);
        $this->assertSame('a-1', $google['apple_user_id']);
        $this->assertSame('g-1', $google['google_user_id']);
    }

    public function test_unverified_email_does_not_link_and_creates_a_separate_user(): void
    {
        $apple = $this->users()->resolveIdentity('apple', 'a-1', 'shared@example.com', true);
        // Google reports the same email but unverified → must NOT hijack the account.
        $google = $this->users()->resolveIdentity('google', 'g-1', 'shared@example.com', false);

        $this->assertNotSame($apple['id'], $google['id']);
        $this->assertNull($google['apple_user_id']);
        // The unverified email is not stored (email is the unique link key).
        $this->assertNull($google['email']);
    }

    public function test_dev_bypass_helper_still_works(): void
    {
        $user = $this->users()->upsertByAppleId('dev-user', null);
        $this->assertSame('dev-user', $user['apple_user_id']);
        $this->assertNull($user['email']);
    }
}
