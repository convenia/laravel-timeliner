<?php

namespace Convenia\Timeliner\Tests;

use Convenia\Timeliner\Traits\Timelinable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TestModel
 */
class TestModel extends Model
{
    use SoftDeletes, Timelinable;

    public $mirrorable = true;

    public $mirrorableFormat = [
        'event' => [
            'fields' => [
                'date' => 'created_at',
                'hides' => [0, 1],
                'likes' => [0, 1],
            ],
            'tags' => [
                'something|static',
            ],
            'pinned' => 0,
            'category' => 'custom|static',

        ],
    ];

    protected $fillable = [
        'name',
    ];
}
