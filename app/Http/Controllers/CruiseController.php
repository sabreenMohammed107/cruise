<?php

namespace App\Http\Controllers;

use App\Models\Cruise;
use App\Models\HomeSection;
use App\Support\ScDataPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CruiseController extends Controller
{
    /**
     * Cruise details (details.html). details.js reads the cruise from SC_DATA.page.cruise
     * and the departure from ?sailing=CODE.
     */
    public function show(Request $request, Cruise $cruise, ScDataPresenter $presenter): View
    {
        abort_unless($cruise->is_active && $cruise->sailings()->upcoming()->exists(), 404);

        return view('pages.cruise-show', [
            'cruise' => $cruise,
            'sections' => HomeSection::forPage('cruise'), // headings and texts (Admin → Cruise pages)
            'scData' => $presenter->make($request->user('web'), ['cruise' => $cruise->slug]),
        ]);
    }
}
