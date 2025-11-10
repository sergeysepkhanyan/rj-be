<?php

namespace App\Repositories;

use App\Models\File;

class FileRepository
{
    public function createForFileable($fileable, string $path): File
    {
        return $fileable->files()->create(['path' => $path]);
    }

    /**
     * Create multiple file records for a fileable model
     *
     * @param  mixed $fileable
     * @param  array $paths
     * @return array
     */
    public function createMultipleForFileable(mixed $fileable, array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $files[] = $this->createForFileable($fileable, $path);
        }
        return $files;
    }

    public function deleteByPaths($model, array $paths): void
    {
        $model->files()->whereIn('path', $paths)->delete();
    }
}

