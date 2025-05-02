<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\EntityRepository;

/**
 * User repository with strongly typed methods.
 */
interface UserRepository extends EntityRepository
{
    /**
     * Find user by ID.
     */
    public function findUserById(int $id): ?User;
}
