<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\User;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Repository\BaseRepository;
use App\Infrastructure\Storage\StorageInterface;

final class PostgreSQLUserRepository extends BaseRepository implements UserRepository
{
    private const TABLE_NAME = 'users';

    public function __construct(
        StorageInterface $storage,
        HydratorInterface $hydrator,
        QueryFactory $queryFactory,
    ) {
        parent::__construct($storage, $hydrator, $queryFactory, self::TABLE_NAME);
    }

    public function findUserById(int $id): ?User
    {
        /** @var User|null */
        return $this->findById(User::class, $id);
    }
}
