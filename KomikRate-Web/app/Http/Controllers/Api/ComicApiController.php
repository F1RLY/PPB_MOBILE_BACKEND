<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Models\Comic;
use App\Models\RatingReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComicApiController extends Controller
{
    /**
     * Daftar komik dengan support filter & search.
     * GET /api/comics
     */
    public function index(Request $request): JsonResponse
    {
        $query = Comic::query()
            ->with(['genres:id,name,slug'])
            ->withAvg('ratingsReviews', 'rating');

        // Filter: Berdasarkan genre
        if ($request->filled('genre')) {
            $query->whereHas('genres', fn($q) => $q->where('slug', $request->genre));
        }

        // Filter: Berdasarkan type
        if ($request->filled('type')) {
            $allowedTypes = ['Manga', 'Manhwa', 'Manhua'];
            if (in_array($request->type, $allowedTypes)) {
                $query->ofType($request->type);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('alternative_title', 'like', $search);
            });
        }

        // Sorting
        match ($request->get('sort', 'latest')) {
            'trending' => $query->orderBy('view_count', 'desc'),
            'popular'  => $query->orderBy('ratings_reviews_avg_rating', 'desc'),
            default    => $query->latest(),
        };

        $perPage = $request->get('per_page', 15);
        $comics = $query->select([
            'id', 'title', 'alternative_title', 'author',
            'type', 'cover_image', 'status', 'view_count',
        ])->paginate($perPage);

        // Transform response untuk Flutter
        $transformedComics = $comics->getCollection()->map(function (Comic $comic) {
            return [
                'id'                => $comic->id,
                'title'             => $comic->title,
                'alternative_title' => $comic->alternative_title,
                'author'            => $comic->author,
                'type'              => $comic->type,
                'cover_image'       => $comic->cover_image_url, // Ini sudah benar sekarang
                'status'            => $comic->status,
                'average_rating'    => $comic->ratings_reviews_avg_rating
                                        ? round($comic->ratings_reviews_avg_rating, 1)
                                        : null,
                'genres'            => $comic->genres,
            ];
        });

        // Ganti collection dengan yang sudah ditransform
        $comics->setCollection($transformedComics);

        return response()->json([
            'success' => true,
            'data'    => $comics,
        ]);
    }

    /**
     * Detail komik.
     * GET /api/comics/{id}
     */
    public function show(int $id): JsonResponse
    {
        $comic = Comic::with([
            'genres:id,name,slug',
            'legalLinks:id,comic_id,platform_name,url',
            'ratingsReviews' => fn($q) => $q->with('user:id,name')->latest()->limit(10),
        ])
        ->withAvg('ratingsReviews', 'rating')
        ->withCount('ratingsReviews')
        ->findOrFail($id);

        // Increment view count
        $comic->increment('view_count');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $comic->id,
                'title'               => $comic->title,
                'alternative_title'   => $comic->alternative_title,
                'author'              => $comic->author,
                'artist'              => $comic->artist,
                'type'                => $comic->type,
                'synopsis'            => $comic->synopsis,
                'cover_image'         => $comic->cover_image_url,
                'status'              => $comic->status,
                'genres'              => $comic->genres,
                'legal_links'         => $comic->legalLinks,
                'average_rating'      => $comic->ratings_reviews_avg_rating
                                          ? round($comic->ratings_reviews_avg_rating, 1)
                                          : null,
                'total_reviews'       => $comic->ratings_reviews_count,
                'recent_reviews'      => $comic->ratingsReviews,
            ],
        ]);
    }

    /**
     * Submit review.
     * POST /api/comics/{id}/review
     */
    public function storeReview(StoreReviewRequest $request, int $id): JsonResponse
    {
        $comic = Comic::findOrFail($id);
        $user  = $request->user();

        $review = RatingReview::updateOrCreate(
            ['user_id' => $user->id, 'comic_id' => $comic->id],
            ['rating' => $request->rating, 'review_text' => $request->review_text]
        );

        $wasUpdated = !$review->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => $wasUpdated ? 'Review berhasil diperbarui.' : 'Review berhasil ditambahkan.',
            'data'    => $review,
        ], $wasUpdated ? 200 : 201);
    }
}