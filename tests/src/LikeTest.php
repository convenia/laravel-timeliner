<?php

namespace Convenia\Mirrorable\src\Tests;

use Convenia\Timeliner\Models\Timeline;
use Convenia\Timeliner\Observers\TimelinerObserver;
use Convenia\Timeliner\Tests\TestCase;

/**
 * LikeTest
 */
class LikeTest extends TestCase
{
    public $item;

    public function setUp()
    {
        parent::setUp();
        $this->item = $this->addItem();
    }

    public function test_like()
    {
        $this->app->bind(TimelinerObserver::class, function () {
            return $this->getMockBuilder(TimelinerObserver::class)->disableOriginalConstructor()->getMock();
        });

        $employeeId = time();

        $this->item->likeOrDislike($employeeId);
        $itemNew = Timeline::find($this->item->id);

        $likes = $itemNew->likes->toArray();
        $this->assertEquals($employeeId, $likes[2]);
    }

    public function test_dislike()
    {
        $this->app->bind(TimelinerObserver::class, function () {
            return $this->getMockBuilder(TimelinerObserver::class)->disableOriginalConstructor()->getMock();
        });

        $employeeId = 31;

        $this->item->likeOrDislike($employeeId);
        $this->item->likeOrDislike($employeeId);
        $this->item->find($this->item->id);

        $this->assertInstanceOf(Timeline::class, $this->item);
    }

    public function test_like_fail()
    {
        $this->expectException(\Exception::class);

        $employeeId = 'a';

        $this->item->likeOrDislike($employeeId);
        $this->item->likeOrDislike($employeeId);
        $this->item->find($this->item->id);
    }
}
