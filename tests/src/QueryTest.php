<?php

namespace Convenia\Mirrorable\src\Tests;

use Convenia\Timeliner\Models\Timeline;
use Convenia\Timeliner\Tests\TestCase;

/**
 * LikeTest
 */
class QueryTest extends TestCase
{
    public function test_paginator()
    {
        $this->addItem();
        $this->addItem();
        $this->addItem();
        $this->addItem();
        $this->addItem();

        $paginate = Timeline::paginate(1);
        $this->assertEquals(1, count($paginate));
    }

    public function test_paginator_starts()
    {
        $this->addItem();
        $this->addItem();
        $this->addItem();
        $starts = $this->item = $this->addItem();
        $this->addItem();
        $this->addItem();
        $this->addItem();
        $this->addItem();

        $paginate = Timeline::paginate(1, $starts->id);
        $this->assertEquals(1, count($paginate));
    }

    public function test_scope_pinned()
    {
        $this->addItem();
        $this->addItem();
        $this->addItem();

        $paginate = Timeline::ByPinned(true)->get();
        $this->assertEquals(0, count($paginate));
    }
}
