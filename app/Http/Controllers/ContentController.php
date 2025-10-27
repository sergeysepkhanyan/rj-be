<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function getContent(Request $request): JsonResponse
    {
        $locale = $request->query('locale', config('app.locale'));
        app()->setLocale($locale);
        $file = base_path("resources/lang/{$locale}/content.php");

        if (!file_exists($file)) {
            return response()->json([
                'error' => "Content not found for locale '{$locale}'.",
                'checked_path' => $file,
            ], 404);
        }

        $texts = include $file;

        return response()->json([
            'locale' => $locale,
            'content' => $texts,
        ]);
    }
}


