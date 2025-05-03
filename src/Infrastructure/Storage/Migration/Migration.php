<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Migration;

interface Migration
{
    public function getVersion(): string;

    public function up(): string;

    public function down(): string;
}
