<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleReviewsService
{
    private const CACHE_KEY = 'google_place_reviews';

    /**
     * Get Google reviews for the configured place, cached.
     *
     * @return array{rating: float|null, totalReviews: int, reviews: array, placeUrl: string|null}
     */
    public function getReviews(): array
    {
        $apiKey  = config('services.google_places.api_key');
        $placeId = config('services.google_places.place_id');

        if (! $apiKey || ! $placeId) {
            return $this->emptyResponse();
        }

        $cacheMinutes = max(1, (int) config('services.google_places.cache_minutes', 360));

        return Cache::remember(self::CACHE_KEY, now()->addMinutes($cacheMinutes), function () use ($apiKey, $placeId) {
            return $this->fetchFromGoogle($apiKey, $placeId);
        });
    }

    /**
     * Clear the cached reviews (useful for admin refresh).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Call Google Places API (New) — Place Details.
     */
    private function fetchFromGoogle(string $apiKey, string $placeId): array
    {
        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key'  => $apiKey,
                'X-Goog-FieldMask' => 'rating,userRatingCount,reviews,googleMapsUri',
            ])->get("https://places.googleapis.com/v1/places/{$placeId}");

            if (! $response->successful()) {
                Log::warning('Google Places API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return $this->emptyResponse();
            }

            $data = $response->json();

            return [
                'rating'       => $data['rating'] ?? null,
                'totalReviews' => $data['userRatingCount'] ?? 0,
                'placeUrl'     => $data['googleMapsUri'] ?? null,
                'reviews'      => $this->formatReviews($data['reviews'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::error('Google Places API exception', ['message' => $e->getMessage()]);
            return $this->emptyResponse();
        }
    }

    /**
     * Format raw Google review objects into a clean structure.
     */
    private function formatReviews(array $rawReviews): array
    {
        return collect($rawReviews)->map(function (array $review) {
            return [
                'authorName'      => $review['authorAttribution']['displayName'] ?? null,
                'authorPhotoUrl'  => $review['authorAttribution']['photoUri'] ?? null,
                'rating'          => $review['rating'] ?? null,
                'text'            => $review['text']['text'] ?? null,
                'relativeTime'    => $review['relativePublishTimeDescription'] ?? null,
                'publishTime'     => $review['publishTime'] ?? null,
            ];
        })->all();
    }

    private function emptyResponse(): array
    {
        return [
            'rating'       => null,
            'totalReviews' => 0,
            'placeUrl'     => null,
            'reviews'      => [],
        ];
    }
}
