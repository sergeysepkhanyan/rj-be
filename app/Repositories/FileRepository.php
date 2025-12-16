<?php

namespace App\Repositories;

use App\Models\File;
use Illuminate\Support\Str;

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
        $normalizedPaths = collect($paths)
            ->map(fn ($path) => $this->normalizePath($path))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedPaths)) {
            return;
        }

        $model->files()
            ->whereIn('path', $normalizedPaths)
            ->delete();
    }

    private function normalizePath(string $path): ?string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $path = parse_url($path, PHP_URL_PATH) ?? '';
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        return $path ?: null;
    }
}

