<?php

declare(strict_types=1);

namespace Clockwork;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Inserts the user on first sign-in, otherwise refreshes a newly-provided
     * email, and returns the stored row.
     *
     * @return array{id:int,apple_user_id:string,email:?string,created_at:string}
     */
    public function upsertByAppleId(string $appleUserId, ?string $email): array
    {
        $insert = $this->db->prepare(
            'INSERT INTO users (apple_user_id, email)
             VALUES (:apple_user_id, :email)
             ON DUPLICATE KEY UPDATE
                email = COALESCE(VALUES(email), email),
                id = LAST_INSERT_ID(id)'
        );
        $insert->execute([
            'apple_user_id' => $appleUserId,
            'email' => $email,
        ]);

        $select = $this->db->prepare(
            'SELECT id, apple_user_id, email, created_at
             FROM users
             WHERE apple_user_id = :apple_user_id'
        );
        $select->execute(['apple_user_id' => $appleUserId]);

        /** @var array{id:int,apple_user_id:string,email:?string,created_at:string} $row */
        $row = $select->fetch();
        $row['id'] = (int) $row['id'];

        return $row;
    }
}
