<?php

namespace Convenia\Timeliner\Traits;

trait TimelinerLike
{
    public function likeOrDislike($employeeId)
    {
        $likes = collect([]);

        if ($this->likes !== null) {
            $likes = collect($likes = $this->likes->toArray());
        }

        if ($likes->search($employeeId, false) !== false) {
            return $this->removeFromNode('likes', $employeeId);
        }

        return $this->addInNode('likes', $employeeId);

    }
}
