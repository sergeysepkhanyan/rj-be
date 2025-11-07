<?php

namespace App\Repositories;

use App\Models\File;

class FileRepository
{
    public function createForFileable($fileable, string $path): File
    {
        return $fileable->files()->create(['path' => $path]);
    }

    public function delete(File $file): bool
    {
        return $file->delete();
    }
}

