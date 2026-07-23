<?php

declare(strict_types=1);

namespace Clockwork;

use PDO;

/**
 * @phpstan-type UserRow array{id:int,apple_user_id:?string,google_user_id:?string,email:?string,created_at:string}
 */
final class UserRepository
{
    private const COLUMNS = 'id, apple_user_id, google_user_id, email, created_at';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Resolves the user behind a verified provider identity, creating or linking a
     * row as needed, and returns it:
     *
     *   1. an existing user already carrying this provider subject, else
     *   2. an existing user with the same **verified** email — the new provider is
     *      linked onto that account (one person, one account across providers), else
     *   3. a brand-new user.
     *
     * Only a provider-verified email is ever stored or used to link, so an
     * unverified email can never hijack an existing account.
     *
     * @param 'apple'|'google' $provider
     *
     * @return UserRow
     */
    public function resolveIdentity(string $provider, string $subject, ?string $email, bool $emailVerified): array
    {
        $column = $provider === 'google' ? 'google_user_id' : 'apple_user_id';
        $storeEmail = ($emailVerified && $email !== null && $email !== '') ? $email : null;

        // 1. Known provider subject.
        $existing = $this->findBy($column, $subject);
        if ($existing !== null) {
            if ($storeEmail !== null && $existing['email'] === null) {
                $this->db->prepare('UPDATE users SET email = :email WHERE id = :id')
                    ->execute(['email' => $storeEmail, 'id' => $existing['id']]);
            }

            return $this->findById($existing['id']);
        }

        // 2. Link onto an existing account with the same verified email.
        if ($storeEmail !== null) {
            $byEmail = $this->findBy('email', $storeEmail);
            if ($byEmail !== null) {
                $this->db->prepare("UPDATE users SET {$column} = :subject WHERE id = :id")
                    ->execute(['subject' => $subject, 'id' => $byEmail['id']]);

                return $this->findById($byEmail['id']);
            }
        }

        // 3. New user.
        $insert = $this->db->prepare("INSERT INTO users ({$column}, email) VALUES (:subject, :email)");
        $insert->execute(['subject' => $subject, 'email' => $storeEmail]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    /**
     * Inserts/returns a user by Apple subject. Retained for the `X-Dev-User`
     * bypass and Apple callers; delegates to {@see resolveIdentity} (Apple always
     * delivers a verified email).
     *
     * @return UserRow
     */
    public function upsertByAppleId(string $appleUserId, ?string $email): array
    {
        return $this->resolveIdentity('apple', $appleUserId, $email, $email !== null);
    }

    /**
     * @param 'apple_user_id'|'google_user_id'|'email' $column
     *
     * @return UserRow|null
     */
    private function findBy(string $column, string $value): ?array
    {
        $select = $this->db->prepare('SELECT ' . self::COLUMNS . " FROM users WHERE {$column} = :value");
        $select->execute(['value' => $value]);
        $row = $select->fetch();

        return $row === false ? null : $this->normalise($row);
    }

    /**
     * @return UserRow
     */
    private function findById(int $id): array
    {
        $select = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id');
        $select->execute(['id' => $id]);

        /** @var array<string,mixed> $row */
        $row = $select->fetch();

        return $this->normalise($row);
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return UserRow
     */
    private function normalise(array $row): array
    {
        $row['id'] = (int) $row['id'];

        /** @var UserRow $row */
        return $row;
    }
}
