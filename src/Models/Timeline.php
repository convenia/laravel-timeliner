<?php

namespace Convenia\Timeliner\Models;

use BaoPham\DynamoDb\DynamoDbModel;
use Carbon\Carbon;
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
            'range' => 'dateTimestamp',
        ],
    ];

    protected $primaryKey = ['id'];
    protected $compositeKey = ['id', 'dateTimestamp'];

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

    protected $hidden = [
        'permissions',
        'hides',
        'company_id',
        'created_at',
        'updated_at'
    ];

    static $defaultFilter = [
        'page' => 1,
        'start_at' => null,
        'per_page' => 50,
        'date_start' => null,
        'date_end' => null,
        'category' => null,
        'type' => null,
        'pinned' => null,
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

    /**
     * @param $employeeId
     * @return mixed
     */


    public static function myTimeline($employeeId, $filters = [])
    {
        $default = self::$defaultFilter;
        $filters = array_merge($default, $filters);

        $query = self::query();

        $query
            ->byPerson($employeeId);
        //->limit($filters['per_page'])->get();


        if( !is_null($filters['date_start']) ) {
            $dateToWork = Carbon::createFromTimestamp($filters['date_start']);
            $dateToWork->startOfDay();
            $query->where('dateTimestamp', '>=', (int) $dateToWork->timestamp);
        }

        if( !is_null($filters['date_end'])){
            $dateEnd = Carbon::createFromTimestamp($filters['date_end']);
            $dateEnd->endOfDay();

            $query->where('dateTimestamp', '<=', (int) $dateEnd->timestamp);
        }

        if( !is_null($filters['category'])){
            $query->where('category', '=', $filters['category']);
        }

        if( !is_null($filters['type'])){
            $query->where('type', '=', $filters['type']);
        }

        if( !is_null($filters['start_at'])){
            $query->where('dateTimestamp', '=', $filters['start_at']);
        }

        if( !is_null($filters['pinned'])){
            $query->where('pinned', '=', $filters['pinned']);
        }

        $result = self::fixData($query->get());

        return $result;
    }

    public static function fixData($data)
    {
        $data->toArray();
        $result = [];

        foreach($data as $key => $time) {
            $result[$time['dateTimestamp'].'-'.sprintf('%02d',$key)] = collect($time);
        }

        krsort($result);
        return collect($result)->values();
    }
}
