<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Models\ShipFaq;
use App\Support\ScDataPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pre-boarding guide: visas, boarding steps, check-in, terminal, prohibited items and kids policy. Texts, cards and
 * links from Admin → Cruise pages → Pre-boarding guide (config/pages.php → guide); the questions are the Ship FAQs.
 * Rendered on the server, so it only needs the small SC_DATA core.js uses.
 */
class PreBoardingGuideController extends Controller
{
    public function __invoke(Request $request, ScDataPresenter $presenter): View
    {
        return view('pages.pre-boarding-guide', [
            'scData' => $presenter->minimal($request->user('web')),
            'sections' => HomeSection::forPage('guide'),
            'faqs' => ShipFaq::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
