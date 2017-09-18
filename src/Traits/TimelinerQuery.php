<?php

namespace Convenia\Timeliner\Traits;

use Convenia\Timeliner\Exceptions\CommandFailed;
use Mockery\Exception;

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

        try {
            $this->getClient()->updateItem([
                'TableName' => $this->table,
                'Key' => [
                    'id' => [
                        'S' => $this->id,
                    ],
                ],
                'ExpressionAttributeValues' => [
                    ":v" => ["{$type}" => [$values]],
                ],
                'UpdateExpression' => "ADD {$node} :v",
            ]);
        } catch (Exception $e) {
            throw new CommandFailed($e->getMessage());
        }

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

        try {
            $result = $this->getClient()->updateItem([
                'TableName' => $this->table,
                'Key' => [
                    'id' => [
                        'S' => $this->id,
                    ],
                ],
                'AttributeUpdates' => [
                    "{$node}" => [
                        'Action' => 'DELETE',
                        'Value' => [
                            "{$type}" => [$values],
                        ],
                    ],
                ],
            ]);
        } catch (Exception $e) {
            throw new CommandFailed($e->getMessage());
        }

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
        return $query->whereIn('permission', 'contains', $personId);
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
