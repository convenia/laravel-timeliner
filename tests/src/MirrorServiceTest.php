<?php

namespace Convenia\Mirrorable\src\Tests;

use Convenia\Timeliner\Models\Timeline;
use Convenia\Timeliner\Observers\TimelinerObserver;
use Convenia\Timeliner\Tests\TestCase;

/**
 * MirrorServiceTest
 */
class MirrorServiceTest extends TestCase
{
    /**
     * @group mirrorService
     */
    public function test_create_mirror_data()
    {

        $this->app->bind(TimelinerObserver::class, function () {
            return $this->getMockBuilder(TimelinerObserver::class)->disableOriginalConstructor()->getMock();
        });

        $params = [
            'event' => [
                'name' => 'event-name',
                'category' => '123123',
            ],
            'pinned' => true,
            'serialize' => true,
            'date' => date('Y-m-d h:i:s'),
            'dateTimestamp' => time(),
        ];

        $content = collect([
            'a' => 'lorem',
            'b' => 'ipsum',
            'date' => date('Y-m-d h:i:s'),
            'dateTimestamp' => time(),
        ]);

        $mirror = $this->timelineService->mirrorData($content, $params);

        $this->assertNotNull(Timeline::find($mirror->id));
    }

    public function test_observer()
    {

        //TestModel::observe(Timelinable::class);

        $model = $this->testModel->create(['name' => 'Observer Test']);

        $mirrorId = $this->timelineService->buildMirrorId('event', $model);

        $morroredModelData = Timeline::find($mirrorId)->toArray();

        $this->assertEquals('event-TestModel-1', $morroredModelData['id']);
    }
}
