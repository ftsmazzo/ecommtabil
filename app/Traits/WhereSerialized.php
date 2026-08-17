<?php

namespace App\Traits;

trait WhereSerialized
{
    public function whereSerialized(
        string $column,
        string $value,
        array $keys = []
    ): self {
        $regex = empty($keys)
            ? 's:[0-9]+:"' . $value . '"'
            : implode('|', $keys) . '";s:[0-9]+:"' . $value . '"';

        return $this->whereRaw("{$column} REGEXP ?", [$regex]);
    }
}
