<?php

namespace App\Support;

use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\Json\JsonResource;

trait StripsMissingValues
{
    protected function stripMissingValues(mixed $value): mixed
    {
        if ($value instanceof MissingValue) {
            return null;
        }

        if ($value instanceof JsonResource) {
            return $this->stripMissingValues($value->resolve());
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $clean = $this->stripMissingValues($v);

                if ($clean !== null) {
                    $out[$k] = $clean;
                }
            }
            return $out;
        }

        return $value;
    }
}
