<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Page;

class PagesController extends Controller {
    public function index(Request $request, $slug)
    {
        $page = Page::where('slug', $slug)
            ->where('visible', true)
            ->firstOrFail();

        return Inertia::render('PageView')
            ->with('page', $page);
    }

}
