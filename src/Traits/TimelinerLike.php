<?php

namespace Convenia\Timeliner\Traits;

trait TimelinerLike
{
    public function likeOrDislike($employeeId)
    {

        $likes = collect([]);

        if ($this->likes !== null) {
            $likes = collect($this->likes->toArray());
        }

        if ($likes->search($employeeId)) {
            $this->removeFromNode('likes', $employeeId);
        }

        $this->addInNode('likes', $employeeId);

        return $this;
    }
}
