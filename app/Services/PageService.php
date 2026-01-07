<?php

namespace App\Services;

use App\Repositories\Interfaces\PageRepositoryInterface;
use App\Support\UploadPathNormalizer;

class PageService
{
    protected PageRepositoryInterface $pageRepository;

    public function __construct(PageRepositoryInterface $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    public function getAllPages()
    {
        return $this->pageRepository->all();
    }

    public function create(array $data)
    {
        return $this->pageRepository->create($data);
    }

    public function update(array $data): \App\Models\Page
    {
        $slug = array_key_first($data);

        $page = $this->pageRepository->findBySlug($slug);

        if (!$page) {
            abort(404, 'Page not found');
        }

        $content = $data[$slug] ?? null;

        $content = $this->stripBaseFromImages($content);

        return $this->pageRepository->update($page, [
            'content' => $content,
        ]);
    }

    /**
     * Recursively walks through page content and normalizes upload paths
     */
    private function stripBaseFromImages($data)
    {
        $keysToNormalize = ['image', 'src', 'backgroundImage'];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (
                    in_array($key, $keysToNormalize, true) &&
                    is_string($value) &&
                    $value !== ''
                ) {
                    $data[$key] = $this->normalizeUploadPath($value);
                } else {
                    $data[$key] = $this->stripBaseFromImages($value);
                }
            }
            return $data;
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                if (
                    in_array($key, $keysToNormalize, true) &&
                    is_string($value) &&
                    $value !== ''
                ) {
                    $data->$key = $this->normalizeUploadPath($value);
                } else {
                    $data->$key = $this->stripBaseFromImages($value);
                }
            }
            return $data;
        }

        return $data;
    }

    /**
     * Converts any URL/path to "images/..."
     *
     * Examples:
     *  https://domain.com/storage/images/a.webp -> images/a.webp
     *  /storage/images/a.webp                  -> images/a.webp
     *  storage/images/a.webp                   -> images/a.webp
     *  images/a.webp                           -> images/a.webp
     */
    private function normalizeUploadPath(string $value): string
    {
        return UploadPathNormalizer::toRelative($value, ['images']);
    }
}


