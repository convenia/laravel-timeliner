<?php

namespace Convenia\Timeliner\Traits;

trait TimelinerPaginator
{
    public static function paginate($limit = 1, $startItem = null)
    {
        if (is_null($startItem)) {
            return self::scanFromBeginning($limit);
        }

        return self::scanFromStartKey($startItem, $limit);
    }

    protected static function scanFromBeginning($limit)
    {
        $client = self::getDynamoDbClientService()->getClient();

        $result = $client->scan([
            'TableName' => config('mirrorable.table'),
            'Select' => 'ALL_ATTRIBUTES',
            'Limit' => $limit,
            'ScanIndexForward' => true,
        ]);

        $result = $result->toArray()['Items'];

        return $result;
    }

    protected static function scanFromStartKey($startKey, $limit)
    {
        $client = self::getDynamoDbClientService()->getClient();

        $result = $client->scan([
            'TableName' => config('mirrorable.table'),
            'Select' => 'ALL_ATTRIBUTES',
            'ExclusiveStartKey' => [
                'id' => [
                    'S' => $startKey,
                ],
            ],
            'Limit' => $limit,
            'ScanIndexForward' => true,
        ]);

        $result = $result->toArray()['Items'];

        return $result;
    }
}
