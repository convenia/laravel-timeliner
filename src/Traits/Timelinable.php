<?php

namespace Convenia\Timeliner\Traits;

use Convenia\Timeliner\Models\Timeline;
use Convenia\Timeliner\Observers\TimelinerObserver;

/**
 * Trait Timelinable
 *
 * @package Convenia\Timeliner\Traits
 */
trait Timelinable
{
    /**
     * $mirrorable = true;
     * $mirrorableFormat = [
     *  'event-name' = [
     *    'fields' => [
     *      'mirror_field_name' => 'model_field',
     *      'mirror_feld_relation' => 'model.relation_field',
     *      'static_name' => 'some_value|static'
     *   ],
     *   'tags' => [
     *     'something|static',
     *     'model_field',
     *     'model.relation_field'
     *   ],
     *   'pinned' => 'model_field',
     *   'category' => 'model.relation_field'
     *
     */
    /**
     * boot
     */
    public static function bootTimelinable()
    {
        self::observe(new TimelinerObserver());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function mirrorable()
    {
        return Timeline::query();
    }
}
