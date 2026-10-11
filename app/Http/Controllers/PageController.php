<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use App\Support\ScDataPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Template pages whose content is rendered by their page script from SC_DATA (decision D1).
 */
class PageController extends Controller
{
    public function __construct(private ScDataPresenter $presenter) {}

    public function home(Request $request): View
    {
        // Section headings, buttons and photos are server-rendered from the admin's Homepage screen
        return $this->page('pages.home', $request)->with('home', HomeSection::forHome());
    }

    public function ship(Request $request): View
    {
        return $this->page('pages.ship', $request)->with('sections', HomeSection::forPage('ship'));
    }

    public function destinations(Request $request): View
    {
        return $this->page('pages.destinations', $request)->with('sections', HomeSection::forPage('destinations'));
    }

    public function catalog(Request $request): View
    {
        return $this->page('pages.catalog', $request)->with('sections', HomeSection::forPage('catalog'));
    }

    private function page(string $view, Request $request): View
    {
        return view($view, ['scData' => $this->presenter->make($request->user('web'))]);
    }
}
