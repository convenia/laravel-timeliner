<?php
namespace Convenia\Timeliner\Services;

use Aws\DynamoDb\SetValue;
use Carbon\Carbon;
use Convenia\Timeliner\Exceptions\InvalidFieldException;
use Convenia\Timeliner\Models\Timeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Validator;

/**
 * Class TimelineService
 *
 * @package Convenia\Timeliner\Services
 */
class TimelineService
{
    protected $defaultConfig = [
        'custom' => [
            'fields' => [
                'company_id' => 'company_id',
                'pinned' => '0|static',
                'category' => 'Recados|static',
                'date' => 'created_at'
            ],
        ]
    ];

    /**
     * @param $config
     * @return array
     */
    public function getConfig($config)
    {
        return array_merge($this->defaultConfig['custom'], $config);
    }

    /**
     * @param null $category
     * @param string $name
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return \Illuminate\Support\Collection
     */
    protected function generateEvent($category = null, $name = Timeline::DEFAULT_EVENT, Model $model = null)
    {
        $data = collect([
            'type' => $name ?? Timeline::DEFAULT_EVENT,
        ]);

        $data->put('category', $category);
        if (! is_null($model)) {
            $data->put('category', self::getNonRequiredField($category, $model));
        }

        return $data;
    }

    /**
     * @param $data
     * @param string $name
     * @param bool $trhow
     * @return \Illuminate\Foundation\Application|mixed|void
     */
    public function add($data, $name = 'custom', $trhow = true)
    {

        switch (true) {
            case $data instanceof Model:
                return $this->prepareModel($data, $name, $trhow);
                break;
            default:
                return $this->insertRaw(collect($data), $name, null, $trhow);
                break;
        }
    }

    /**
     * @param \Illuminate\Support\Collection $data
     * @param null $name
     * @param null $model
     * @param bool $thow
     * @return \Illuminate\Foundation\Application|mixed|void
     * @throws \Exception
     */
    protected function insertRaw(Collection $data, $name = null, $model = null, $thow = true)
    {
        try {
            self::makeValidate($data->toArray());
        } catch (\Exception $e) {
            if ($thow) {
                throw $e;
            }
            return ;
        }

        $timelineId = $this->buildMirrorId($name, $model);

        if (isset($data['id'])) {
            $timelineId = $data['id'];
        }

        $mirrorable = Timeline::query()->where('id', $timelineId)->get()->last();

        if ($mirrorable === null) {
            $mirrorable = app(Timeline::class);
        }

        if ($mirrorable->dateTimestamp !== null) {
            $data['dateTimestamp'] = $mirrorable->dateTimestamp;
        }

        $data->each(function ($content, $field) use ($mirrorable) {
            $mirrorable->{$field} = $content;
        });

        $mirrorable->save();

        return $mirrorable;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param null $name
     * @param bool $trhow
     * @return \Illuminate\Foundation\Application|mixed|void
     */
    public function prepareModel(Model $model, $name = null, $trhow = true)
    {
        if ($name === null || ! array_key_exists($name, $model->mirrorableFormat)) {
            $configs = $model->mirrorableFormat;

            foreach ($configs as $config => $info) {
                $this->prepareModel($model, $config);
                return ;
            }
        }

        $event = $this->getConfig($model->mirrorableFormat[$name]);

        if (method_exists($model, 'withAll') ) {
            $model->withAll();
        }

        $data_reflex = $this->prepareModelData($model, $event);
        $data_reflex->put('obj', $model->toArray());

        if (!isset($data_reflex['type'])) {
            $data_reflex->put('type', $name);
        }

        $data_reflex->put('model', class_basename($model));
        $data_reflex->put('system_tags', self::generateSystemTags($name, $model));
        $data_reflex->put('user_tags', self::generateUserTags($model, $event['tags']));
        $data_reflex->put('id', self::buildMirrorId($name, $model));

        $dates = $this->buildDates($model, $event['date']);

        $data_reflex->put('date', $dates['date']);
        $data_reflex->put('dateTimestamp', $dates['dateTimestamp']);

        $data_reflex->put('permissions', $model->setMirrorablePermissions($model) ?? null);

        return $this->insertRaw($data_reflex, $name, $model, $trhow);
    }

    /**
     * @param $field
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param null $nullReturn
     * @return \Aws\DynamoDb\SetValue|mixed|null
     */
    protected function getNonRequiredField($field, Model $model = null, $nullReturn = null)
    {
        if (is_null($field)) {
            return $nullReturn;
        }

        return self::getField($model, $field);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $field
     * @return \Aws\DynamoDb\SetValue|mixed
     */
    public function getField(Model $model, $field)
    {
        switch (true) {
            case is_array($field):
                return $this->getFieldArray($model, $field);
                break;
            case is_string($field):
                return $this->getFieldString($model, $field);
                break;
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $field
     * @return \Aws\DynamoDb\SetValue
     */
    public function getFieldArray(Model $model, $field)
    {
        return new SetValue($field);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $field
     * @return mixed
     * @throws \Convenia\Timeliner\Exceptions\InvalidFieldException
     */
    protected function getFieldString(Model $model, $field)
    {
        $parameters = explode('|', $field);

        if (count($parameters) > 1) {

            if ($parameters[1] == 'static') {
                return $parameters[0];
            }

            if ($parameters[1] == 'bool') {
                return filter_var($parameters[1], FILTER_VALIDATE_BOOLEAN);
            }

            if (substr($parameters[1], 0, 4) == 'func') {
                $values = explode(':', $parameters[1]);

                if (count($values) > 1) {
                    $attrs = explode(',', $values[1]);
                }

                return call_user_func([$model, $parameters[0]], $attrs ?? null);
            }
        }

        $childs = explode('.', $field);
        try {
            if (count($childs) === 1) {
                return $model->{$field};
            }

            $model->load($childs[0]);
            $model->load("category");

            return $model->{$childs[0]}->{$childs[1]};
        } catch (\Exception $e) {
            throw new InvalidFieldException('Invalid Field Name on $mirrorableFormat variable');
        }
    }

    /**
     * @param string $name
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param bool $isCustom
     * @return \Illuminate\Support\Collection
     */
    protected function generateSystemTags($name = Timeline::DEFAULT_EVENT, Model $model = null, $isCustom = false)
    {
        $data = collect([
            'event_name' => $name,
            'custom' => $isCustom,
        ]);

        if (! is_null($model)) {
            $data->put('model_name', class_basename($model));
        }

        return $data;
    }

    /**
     * @param $name
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @return string
     */
    public function buildMirrorId($name, Model $model = null)
    {
        if (! is_null($model)) {
            return "{$name}-".class_basename($model)."-{$model->id}";
        } else {
            return "{$name}-custom-".uniqid();
        }
    }

    /**
     * @param $data
     */
    protected function makeValidate($data)
    {
        Validator::make($data,
            [
                'obj' => 'required',
                'pinned' => 'required',
                'category' => 'required',
                'date' => 'required',
                'dateTimestamp' => 'required',
            ])
            ->validate();
    }

    /**
     * @param $model
     * @param $name
     * @return \Illuminate\Foundation\Application|mixed|void
     */
    public function mirrorModel($model, $name)
    {
        $event = $model->mirrorableFormat[$name];

        $data = self::prepareModelData($model, $event);
        $data->put('obj', $model->toArray());
        $data->put('type', $name);
        $data->put('model', class_basename($model));
        $data->put('event', self::generateEvent($event['fields']['category'], $name, $model));
        $data->put('pinned', self::getNonRequiredField($event['fields']['pinned'], $model, false));
        $data->put('id', self::buildMirrorId($name, $model));

        $dates = $this->buildDates($model, $event);
        $data->put('date', $dates['date']);
        $data->put('dateTimestamp', $dates['dateTimestamp']);

        // identifier tags
        $data->put('system_tags', self::generateSystemTags($name, $model));
        $data->put('user_tags', self::generateUserTags($model, $event['tags']));


        return $this->insertRaw($data, $name);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $event
     * @return $this
     */
    public function prepareModelData(Model $model, $event)
    {
        return collect($event['fields'] ?? [])->transform(function ($field) use ($model) {
            return self::getField($model, $field);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $tags
     * @return $this
     */
    protected function generateUserTags(Model $model, $tags = [])
    {
        return collect($tags)->transform(function ($tag) use ($model) {
            return self::getField($model, $tag);
        });
    }

    protected function buildDates(Model $model, $field = null)
    {

        if ($field !== null) {
            return $this->buildDatesFromField($model, $field);
        }

        return $this->dateToArray(Carbon::now());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param $field
     * @return array
     */
    protected function buildDatesFromField(Model $model, $field)
    {
        if ($model->{$field} instanceof Carbon) {
            return $this->dateToArray($model->{$field});
        }

        $date = Carbon::createFromFormat('Y-m-d H:i:s', $model->{$field});
        return $this->dateToArray($date);
    }

    /**
     * @param \Carbon\Carbon $date
     * @return array
     */
    protected function dateToArray(Carbon $date)
    {
        return [
            'date' => $date->format('Y-m-d h:i:s'),
            'dateTimestamp' => $date->getTimestamp(),
        ];
    }

    /**
     * @param $id
     * @return mixed
     */
    public function get($id)
    {
        return Timeline::where('id', $id)->get()->last();
    }

    /**
     * @param $id
     * @param $content
     */
    public function update($id, $content)
    {
        $mirror = Timeline::where('id', $id)->get()->last();
        $mirror->update($content);
        $mirror->save();
    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        $timeline = Timeline::query()->where('id', $id)->get()->last();

        if ($timeline !== null) {
            $timeline->delete();
        }
    }
}
