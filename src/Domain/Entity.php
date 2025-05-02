<?php

declare(strict_types=1);

namespace App\Domain;

interface Entity
{
    /**
     * Get entity identifier.
     *
     * @return mixed Identifier value, may be null for new entities
     */
    public function getId(): mixed;
}
