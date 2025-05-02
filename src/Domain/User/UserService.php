<?php

declare(strict_types=1);

namespace App\Domain\User;

final readonly class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function findById(int $id): ?User
    {
        return $this->repository->findUserById($id);
    }

    public function createUser(string $passwordHash): User
    {
        $now = time();

        $user = new User(
            id: null,
            passwordHash: $passwordHash,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->repository->save($user);
    }
}
