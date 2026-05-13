<?php

namespace App\Enum;

enum PlanTypeEnum: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public static function getPlanTypes(): array
    {
        return [
            self::MONTHLY->value => 'Месяц',
            self::YEARLY->value => 'Год'
        ];
    }
}
