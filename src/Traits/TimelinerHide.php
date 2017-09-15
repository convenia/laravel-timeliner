<?php

namespace Convenia\Timeliner\Traits;

use BaoPham\DynamoDb\DynamoDbQueryBuilder;
use Illuminate\Support\Facades\Auth;

trait TimelinerHide
{
    public static function toggleVisibility($timelineId, $employeeId)
    {
        if (in_array($employeeId, self::$hides->jsonSerialize())) {
            return self::addInNode('hide', $employeeId);
        }

        return self::removeFromNode('hide', $employeeId);
    }

    protected static function boot()
    {
        parent::boot();

        if (Auth::user() !== null) {
            static::addGlobalScope('withoutHide', function (DynamoDbQueryBuilder $builder) {
                $builder->where('hides', 'not_contains', Auth::user()->employee()->first()->id);
            });
        }
    }
}
