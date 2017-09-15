<?php

namespace Convenia\Timeliner\Services;

use Aws\DynamoDb\SetValue;
use Carbon\Carbon;
use Convenia\Timeliner\Exceptions\InvalidFieldException;
use Convenia\Timeliner\Models\Timeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Validator;

class TimelineService
{
    public function mirrorData($content, $params)
    {
        $data = collect($content);

        if (! $content instanceof Collection) {
            $data = collect($content);
        } else {
            $data = $content;
        }
        if (key_exists('serialize', $params) && $params['serialize'] == true) {
            $data->put('obj', $content->toArray());
        }

        if (key_exists('type', $params)) {
            $data->put('type', $params['type']);
        } else {
            $data->put('type', Timeline::DEFAULT_TYPE);
        }

        if (key_exists('tags', $params)) {
            $data->put('user_tags', $params['tags']);
        }

        $data->put('pinned', $params['pinned']);
        $data->put('event', self::generateEvent($params['event']['category'], $params['event']['name']));
        $data->put('system_tags', self::generateSystemTags($params['event']['name'], null, true));
        $data->put('id', self::buildMirrorId($params['event']['name']));

        self::makeValidate($params);

        $mirrorable = Timeline::find($this->buildMirrorId($params['event']['name']));

        if ($mirrorable === null) {
            $mirrorable = app(Timeline::class);
        }

        $data->each(function ($content, $field) use ($mirrorable) {
            $mirrorable->{$field} = $content;
        });

        $mirrorable->save();

        return $mirrorable;
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
            case is_string($field) :
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
        Validator::make($data, self::validationRules())->validate();
    }

    protected function validationRules()
    {
        return [
            'serialize' => 'sometimes|boolean',
            'pinned' => 'required|boolean',
            'tags' => 'sometimes|array',
            'event.name' => 'required',
            'event.category' => 'required',
            'date' => 'required',
            'dateTimestamp' => 'required',
        ];
    }

    public function mirrorModel($model, $name)
    {
        $event = $model->mirrorableFormat[$name];

        $data = self::prepareModelData($model, $event);
        $data->put('obj', $model->toArray());
        $data->put('type', class_basename($model));
        $data->put('event', self::generateEvent($event['category'], $name, $model));
        $data->put('system_tags', self::generateSystemTags($name, $model));
        $data->put('user_tags', self::generateUserTags($model, $event['tags']));
        $data->put('pinned', self::getNonRequiredField($event['pinned'], $model, false));
        $data->put('id', self::buildMirrorId($name, $model));

        $dates = $this->buildDates($model, $event);
        $data->put('date', $dates['date']);
        $data->put('dateTimestamp', $dates['dateTimestamp']);

        $mirrorable = Timeline::find($this->buildMirrorId($name, $model));

        if ($mirrorable === null) {
            $mirrorable = app(Timeline::class);
        }

        collect($data->toArray())->each(function ($content, $field) use ($mirrorable) {
            $mirrorable->{$field} = $content;
        });

        $mirrorable->save();

        return $mirrorable;
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

    protected function buildDates(Model $model, $event)
    {
        if (isset($event['date']) || $model->created_at !== null) {
            return $this->buildDatesFromField($model, $event['date'] ?? 'created_at');
        }

        $date = Carbon::now();

        return [
            'date' => $date->format('Y-m-d h:i:s'),
            'dateTimestamp' => $date->getTimestamp(),
        ];
    }

    protected function buildDatesFromField(Model $model, $field)
    {
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $model->{$field});

        return [
            'date' => $date,
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
