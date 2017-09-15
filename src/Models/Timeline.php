<?php

namespace Convenia\Timeliner\Models;

use BaoPham\DynamoDb\DynamoDbModel;
use Convenia\Timeliner\Traits\TimelinerHide;
use Convenia\Timeliner\Traits\TimelinerLike;
use Convenia\Timeliner\Traits\TimelinerPaginator;
use Convenia\Timeliner\Traits\TimelinerQuery;

/**
 * Class Mirrorable.
 */
class Timeline extends DynamoDbModel
{
    use TimelinerQuery, TimelinerLike, TimelinerHide, TimelinerPaginator;

    const DEFAULT_TYPE = 'custom';

    const DEFAULT_EVENT = 'default';

    protected $client;

    protected $dynamoDbIndexKeys = [
        'id-dateTimestamp-index' => [
            'hash' => 'id',
            'sort' => 'dateTimestamp',
        ],
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'date',
        'obj',
        'pinned',
        'category',
        'likes',
        'hides',
        'permission',
    ];

    /**
     * Mirrorable constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('mirrorable.table', 'TestTable');
        parent::__construct($attributes);
        $this->client = $this->getClient();
    }
}
