<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait DeletesImages
{
    protected static function bootDeletesImages(): void
    {
        static::deleting(function ($model) {
            // Check if model uses SoftDeletes
            $usesSoftDeletes = in_array(
                \Illuminate\Database\Eloquent\SoftDeletes::class,
                class_uses_recursive($model)
            );

            // Only delete images if:
            // 1. Model doesn't use soft deletes, OR
            // 2. Model is being force deleted
            if (!$usesSoftDeletes || $model->isForceDeleting()) {
                $model->deleteAssociatedImages();
            }
        });
    }

    public function deleteAssociatedImages(): void
    {
        // Delete direct image column if exists
        if (isset($this->image) && $this->image) {
            $this->deleteImageFromStorage($this->image);
        }

        // Delete main_image column if exists (for Product)
        if (isset($this->main_image) && $this->main_image) {
            $this->deleteImageFromStorage($this->main_image);
        }

        // Delete morphMany files if relationship exists
        if (method_exists($this, 'files')) {
            foreach ($this->files as $file) {
                $this->deleteImageFromStorage($file->path);
                $file->delete();
            }
        }
    }

    protected function deleteImageFromStorage(?string $path): void
    {
        if (!$path) {
            return;
        }

        // Try public disk first
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return;
        }

        // Try without prefix if path includes 'storage/'
        $cleanPath = str_replace('storage/', '', $path);
        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }
}
