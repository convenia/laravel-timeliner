<?php

namespace Convenia\Timeliner\Services;

use Aws\DynamoDb\SetValue;
use Carbon\Carbon;
use Convenia\Timeliner\Exceptions\InvalidFieldException;
use Convenia\Timeliner\Models\Timeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Validator;

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
            'date' => 'created_at'
        ]
    ];

    public function getConfig($config)
    {
        return array_merge($this->defaultConfig['custom'], $config);
    }

    protected function generateEvent($category = null, $name = Timeline::DEFAULT_EVENT, Model $model = null)
    {
        $data = collect([
            'type' => $name ?? Timeline::DEFAULT_EVENT,
        ]);

        if (! is_null($model)) {
            $data->put('category', self::getNonRequiredField($category, $model));
        } else {
            $data->put('category', $category);
        }

        return $data;
    }

    public function add($data, $name = 'custom')
    {
        switch (true) {
            case $data instanceof Model:
                return $this->prepareModel($data, $name);
                break;
            default:
                return $this->insertRaw(collect($data), $name);
                break;
        }
    }

    protected function insertRaw(Collection $data, $name = null, $throw = true)
    {
        try {
            self::makeValidate($data->toArray());
        } catch (\Exception $e) {
            Log::info(print_r($data, true));
            if ($throw) {
                throw $e;
            }

            return;
        }

        $mirrorable = Timeline::query()->where('id', $this->buildMirrorId('custom'))->first();

        if ($mirrorable === null) {
            $mirrorable = app(Timeline::class);
        }

        $data->each(function ($content, $field) use ($mirrorable) {
            $mirrorable->{$field} = $content;
        });

        $mirrorable->save();

        return $mirrorable;
    }

    public function prepareModel(Model $model, $name = null)
    {
        if ($name === null || ! array_key_exists($name, $model->mirrorableFormat)) {
            $configs = $model->mirrorableFormat;

            foreach ($configs as $config => $info) {
                return $this->prepareModel($model, $config);
            }
        }

        $event = $this->getConfig($model->mirrorableFormat[$name]);

        $data_reflex = self::prepareModelData($model, $event);
        $data_reflex->put('obj', $model->toArray());
        $data_reflex->put('type', $name);
        $data_reflex->put('model', class_basename($model));
        $data_reflex->put('system_tags', self::generateSystemTags($name, $model));
        $data_reflex->put('user_tags', self::generateUserTags($model, $event['tags']));
        $data_reflex->put('id', self::buildMirrorId($name, $model));

        $dates = $this->buildDates($model, $event['date']);



        $data_reflex->put('date', $dates['date']);
        $data_reflex->put('dateTimestamp', $dates['dateTimestamp']);

        $data_reflex->put('permissions', $model->setMirrorablePermissions($model) ?? null);

        return $this->insertRaw($data_reflex, $name);
    }

    protected function getNonRequiredField($field, Model $model = null, $nullReturn = null)
    {
        if (is_null($field)) {
            return $nullReturn;
        }

        return self::getField($model, $field);
    }

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

    public function getFieldArray(Model $model, $field)
    {
        return new SetValue($field);
    }

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

            return $model->{$childs[0]}->{$childs[1]};
        } catch (\Exception $e) {
            throw new InvalidFieldException('Invalid Field Name on $mirrorableFormat variable');
        }
    }

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

    public function buildMirrorId($name, Model $model = null)
    {
        if (! is_null($model)) {
            return "{$name}-".class_basename($model)."-{$model->id}";
        } else {
            return "{$name}-custom-".uniqid();
        }
    }

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

    public function prepareModelData(Model $model, $event)
    {
        return collect($event['fields'] ?? [])->transform(function ($field) use ($model) {
            return self::getField($model, $field);
        });
    }

    protected function generateUserTags(Model $model, $tags = [])
    {
        return collect($tags)->transform(function ($tag) use ($model) {
            return self::getField($model, $tag);
        });
    }

    protected function buildDates(Model $model, $field = null)
    {
        if ($field !== null || $model->created_at !== null) {
            return $this->buildDatesFromField($model, $field['date'] ?? 'created_at');
        }

        return $this->dateToArray(Carbon::now());
    }

    protected function buildDatesFromField(Model $model, $field)
    {
        if ($model->{$field} instanceof Carbon) {
            return $this->dateToArray($model->{$field});
        }

        $date = Carbon::createFromFormat('Y-m-d H:i:s', $model->{$field});
        return $this->dateToArray($date);
    }

    protected function dateToArray(Carbon $date)
    {
        return [
            'date' => $date->format('Y-m-d h:i:s'),
            'dateTimestamp' => $date->getTimestamp(),
        ];
    }

    public function get($id)
    {
        return Timeline::findOrFail($id);
    }

    public function update($id, $content)
    {
        $mirror = Timeline::findOrFail($id);
        $mirror->update($content);
        $mirror->save();
    }

    public function delete($id)
    {
        Timeline::findOrFail($id)->delete();
    }
}
