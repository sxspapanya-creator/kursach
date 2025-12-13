<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\User;

interface AnalyticsServiceInterface
{
    public function analyze(User $user);
}