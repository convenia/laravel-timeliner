<?php

namespace Convenia\Timeliner\Traits;

use Aws\DynamoDb\SetValue;
use Convenia\Timeliner\Exceptions\CommandFailed;
use Mockery\Exception;

/**
 * Trait TimelinerQuery
 *
 * @package Convenia\Timeliner\Traits
 */
trait TimelinerQuery
{
    /**
     * @param $node
     * @param $values
     * @param string $type
     * @return $this
     * @throws \Convenia\Timeliner\Exceptions\CommandFailed
     */
    public function addInNode($node, $values, $type = 'NS')
    {

        $oldValues = $this->{$node};

        if ($oldValues === null) {
            $oldValues = collect([]);
        }

        $oldValues = $oldValues->toArray();

        $oldValues[] = $values;
        $this->{$node} = new SetValue($oldValues);
        $this->save();
        return $this;

    }

    /**
     * @param $node
     * @param $values
     * @param string $type
     * @return $this
     * @throws \Convenia\Timeliner\Exceptions\CommandFailed
     */
    public function removeFromNode($node, $values, $type = 'NS')
    {
        $oldValues = [];

        if ($this->{$node} !== null) {
            $oldValues = $this->{$node}->toArray();
        }

        $exists = array_search($values, $oldValues, false);

        if ($exists === false) {
            return $this;
        }

        unset($oldValues[$exists]);
        $this->{$node} = new SetValue($oldValues);
        $this->save();

        return $this;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeByPinned($query, $pinned = 1)
    {
        return $query->where('pinned', $pinned);
    }

    /**client
     *
     * @param $query
     * @param $personId
     *
     * @return mixed
     */
    public function scopeByPerson($query, $personId)
    {
        return $query->where('permissions', 'contains', (int) $personId);
    }

    /**
     * @param $employeeId
     * @return mixed
     */
    public static function myTimeline($employeeId)
    {
        return self::query()->byPerson($employeeId)->get();
    }
}
