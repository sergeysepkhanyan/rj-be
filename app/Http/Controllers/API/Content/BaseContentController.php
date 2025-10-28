<?php

namespace App\Http\Controllers\API\Content;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

abstract class BaseContentController extends Controller
{
    /**
     * Get localized content for a specific page key (e.g., home, about).
     */
    protected function getPageContent(string $pageKey): JsonResponse
    {
        $locale = request('locale', App::getLocale());
        App::setLocale($locale);
        $content = __('content');
        if (!isset($content[$pageKey])) {
            return response()->json([
                'error' => "Page '{$pageKey}' not found in content file for locale '{$locale}'.",
            ], 404);
        }
        return response()->json([
            'locale' => $locale,
            'page' => $pageKey,
            'content' => $content[$pageKey],
        ]);
    }
}

