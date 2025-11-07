<?php
namespace App\Services;

use App\Repositories\FileRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function __construct(protected FileRepository $files) {}

    public function upload(UploadedFile $file, string $slug): string
    {
        $folder = "images/{$slug}";
        return $file->store($folder, 'public');
    }

    /**
     * Upload multiple files to a folder based on slug
     */
    public function uploadMultiple(array $files, string $slug): array
    {
        $paths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->upload($file, $slug);
            }
        }

        return $paths;
    }
}



