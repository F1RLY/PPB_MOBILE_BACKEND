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
                'cover_image'       => $comic->cover_image_url,
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
     * Submit review (Create atau Update).
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

    /**
     * Update review (edit review yang sudah ada).
     * PUT /api/reviews/{id}
     * 
     * ✨ ENDPOINT BARU
     */
    public function updateReview(StoreReviewRequest $request, int $id): JsonResponse
    {
        $review = RatingReview::where('id', $id)
            ->where('user_id', $request->user()->id) // pastikan hanya bisa edit milik sendiri
            ->first();

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        $review->update([
            'rating'      => $request->rating,
            'review_text' => $request->review_text,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil diperbarui.',
            'data'    => $review,
        ]);
    }

    /**
     * Hapus review milik user yang sedang login.
     * DELETE /api/reviews/{id}
     */
    public function destroyReview(Request $request, int $id): JsonResponse
    {
        $review = RatingReview::where('id', $id)
            ->where('user_id', $request->user()->id) // pastikan hanya bisa hapus milik sendiri
            ->first();

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        $review->delete(); // soft delete karena model pakai SoftDeletes

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil dihapus.',
        ]);
    }

    public function getUserReviews(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = RatingReview::where('user_id', $user->id)
            ->with([
                'comic' => fn($q) => $q->select('id', 'title', 'alternative_title', 'author', 'type', 'cover_image', 'status')
                                         ->with(['genres:id,name,slug']),
            ]);
 
        // Filter: by genre (optional)
        if ($request->filled('genre')) {
            $query->whereHas('comic.genres', fn($q) => $q->where('slug', $request->genre));
        }
 
        // Sorting
        match ($request->get('sort', 'newest')) {
            'oldest'         => $query->oldest(),
            'highest_rating' => $query->orderByDesc('rating'),
            'lowest_rating'  => $query->orderBy('rating'),
            default          => $query->latest(), // 'newest'
        };
 
        $perPage = min((int) $request->get('per_page', 10), 50); // max 50 per page
        $reviews = $query->paginate($perPage);
 
        // Transform: include comic info + metadata
        $transformedReviews = $reviews->getCollection()->map(function (RatingReview $review) {
            return [
                'id'             => $review->id,
                'rating'         => $review->rating,
                'review_text'    => $review->review_text,
                'created_at'     => $review->created_at->toIso8601String(),
                'updated_at'     => $review->updated_at->toIso8601String(),
                'is_editable'    => true,  // user selalu bisa edit review sendiri
                'is_deletable'   => true,  // user selalu bisa delete review sendiri
                'comic'          => [
                    'id'                  => $review->comic->id,
                    'title'               => $review->comic->title,
                    'alternative_title'   => $review->comic->alternative_title,
                    'author'              => $review->comic->author,
                    'type'                => $review->comic->type,
                    'cover_image'         => $review->comic->cover_image_url,
                    'status'              => $review->comic->status,
                    'genres'              => $review->comic->genres,
                ],
            ];
        });
 
        $reviews->setCollection($transformedReviews);
 
        return response()->json([
            'success' => true,
            'data'    => $reviews,
        ]);
    }
 
    /**
     * GET /api/me/reviews/stats
     * Get summary stats untuk user reviews (future feature).
     * Returns: total reviews, average rating, genres reviewed
     */
    public function getUserReviewStats(Request $request): JsonResponse
    {
        $user = $request->user();
 
        $totalReviews = RatingReview::where('user_id', $user->id)->count();
        $averageRating = RatingReview::where('user_id', $user->id)->avg('rating');
        
        $genresReviewed = RatingReview::where('user_id', $user->id)
            ->with('comic.genres')
            ->get()
            ->pluck('comic.genres')
            ->flatten()
            ->unique('id')
            ->values();
 
        return response()->json([
            'success' => true,
            'data'    => [
                'total_reviews'    => $totalReviews,
                'average_rating'   => $averageRating ? round($averageRating, 1) : null,
                'genres_reviewed'  => $genresReviewed,
            ],
        ]);
    }
}