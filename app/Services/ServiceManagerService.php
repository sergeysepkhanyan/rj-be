<?php

namespace App\Services;

use App\Filters\ServiceFilter;
use App\Models\File;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ServiceManagerService
{
    public function __construct(protected ServiceRepositoryInterface $serviceRepository){}

    public function getAllServices( array $filters = [] )
    {
        return $this->serviceRepository->all($filters);
    }

    public function getServiceById($id)
    {
        return $this->serviceRepository->find($id);
    }

    public function createService(array $data)
    {
        return $this->serviceRepository->create($data);
    }

    public function updateService(Service $service, array $data): Service
    {
        return $this->serviceRepository->update($service, $data);
    }

    public function deleteService(Service $service): ?bool
    {
        return $this->serviceRepository->delete($service);
    }

    public function getPaginatedServices(?ServiceFilter $filter = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->serviceRepository->paginateWithFilter($filter, $perPage);
    }

    public function getByIds(array $services): \Illuminate\Database\Eloquent\Collection
    {
        return $this->serviceRepository->getByIds($services);
    }

    /**
     * Sync additional images for a service
     * @param Service $service
     * @param array $images Array of image paths or objects with 'id' and/or 'path'
     */
    public function syncServiceImages(Service $service, array $images): void
    {
        // Get existing file IDs
        $existingIds = $service->files()->pluck('id')->toArray();
        $newIds = [];

        foreach ($images as $image) {
            if (is_array($image) && isset($image['id'])) {
                // Existing image - keep it
                $newIds[] = $image['id'];
            } elseif (is_array($image) && isset($image['path'])) {
                // New image with path
                $file = $service->files()->create(['path' => $image['path']]);
                $newIds[] = $file->id;
            } elseif (is_string($image)) {
                // New image path as string
                $file = $service->files()->create(['path' => $image]);
                $newIds[] = $file->id;
            }
        }

        // Delete removed images
        $toDelete = array_diff($existingIds, $newIds);
        if (!empty($toDelete)) {
            $filesToDelete = $service->files()->whereIn('id', $toDelete)->get();
            foreach ($filesToDelete as $file) {
                // Delete from storage
                if (Storage::disk('public')->exists($file->path)) {
                    Storage::disk('public')->delete($file->path);
                }
                $file->delete();
            }
        }
    }

}
