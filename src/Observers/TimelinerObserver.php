<?php

namespace Convenia\Timeliner\Observers;

use Convenia\Timeliner\Models\Mirrorable;
use Convenia\Timeliner\Services\TimelineService;

class TimelinerObserver
{
    /**
     * @param $model
     *
     * @return bool
     */
    public static function saved($model)
    {
        //if ($model->mirrorable === false) {
        //    return false;
        //}

        $mirrorService = app(TimelineService::class);

        collect(array_keys($model->mirrorableFormat))->each(function ($event) use ($model, $mirrorService) {
            // TODO: get model and try to update first
            $mirrorService->mirrorModel($model, $event);
        });
    }

    /**
     * @param $model
     * @return bool
     */
    public static function deleted($model)
    {
        //if ($model->mirrorable === false) {
        //    return false;
        //}

        $mirrorService = app(TimelineService::class);

        collect(array_keys($model->mirrorableFormat))->each(function ($event) use ($model, $mirrorService) {
            $mirrorService->delete($mirrorService->buildMirrorId($event, $model));
        });
    }
}
