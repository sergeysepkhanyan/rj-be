<?php

namespace App\Services;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;

class FaqService
{
    public function list(): Collection
    {
        return Faq::ordered()->get();
    }

    public function listActive(): Collection
    {
        return Faq::active()->ordered()->get();
    }

    public function find(int $id): ?Faq
    {
        return Faq::find($id);
    }

    public function create(array $data): Faq
    {
        return Faq::create($data);
    }

    public function update(Faq $faq, array $data): Faq
    {
        $faq->update($data);
        return $faq->fresh();
    }

    public function delete(Faq $faq): bool
    {
        return $faq->delete();
    }

    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            Faq::where('id', $id)->update(['sort_order' => $index]);
        }
    }
}
