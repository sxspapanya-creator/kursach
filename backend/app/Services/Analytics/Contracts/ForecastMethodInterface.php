<?php

namespace App\Services\Analytics\Contracts;

use Illuminate\Support\Collection;
interface ForecastMethodInterface
{
    public function forecast(array $data, int $steps): array;
    public function getName(): string;
    public function getReliabilityMessage(): string;
    public function getMinMonths(): int;
    public function getMaxMonths(): ?int;
}