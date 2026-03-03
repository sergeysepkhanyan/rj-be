<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $description
 * @property mixed $image
 * @property mixed $id
 * @property mixed $items
 */
class SubServiceResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return array_filter([
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
//            'description' => $data['description'] ?? null,
//            'image' => $this->image ? asset('storage/' . $this->image) : null,

            $this->mergeWhen(($data['type'] ?? null) === 'Variant Based', [
                'items' => SubServiceItemResource::collection($this->items),
            ]),

            $this->mergeWhen(($data['type'] ?? null) !== 'Variant Based', [
                'duration'     => $this->duration ?? null,
                'durationUnit' => $this->duration_unit ?? null,
                'showDuration' => (bool) ($this->show_duration ?? true),
                'price'        => $this->price ?? null,
                'currency'     => $this->currency ?? null,
                'vatEnabled' => (bool) ($this->vat_enabled ?? false),
                'vatRate'    => (float) ($this->vat_rate ?? config('vat.rate', 0.05)),

            ]),
        ]);
    }
}
