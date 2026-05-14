<?php

namespace App\Services\Analytics\Contracts;

use Illuminate\Support\Collection;
interface AnomalyDetectorInterface
{
    public function detect(Collection $transactions): Collection;
    public function filterOutliers(Collection $transactions): Collection;
    public function getName(): string;
}