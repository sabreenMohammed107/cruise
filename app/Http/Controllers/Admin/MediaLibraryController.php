<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One page of the media library grid (admin.partials.media-library), newest first:
 * the first 24 when the modal opens, 24 more per "Load more" / scroll, and again on search.
 */
class MediaLibraryController extends Controller
{
    private const PER_PAGE = 24;

    public function __invoke(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query('offset', 0));
        $items = MediaLibrary::items((string) $request->query('q', ''));

        return response()->json([
            'items' => array_slice($items, $offset, self::PER_PAGE),
            'total' => count($items),
            'has_more' => $offset + self::PER_PAGE < count($items),
        ]);
    }
}
