<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Work;

class WorkController extends Controller
{
    public function index()
    {
        $works = Work::orderByDesc('sort_order')->orderByDesc('id')->get();

        return response()->json([
            'works' => $works->map(fn ($w) => [
                'id' => $w->id,
                'title' => $w->title,
                'category' => $w->category,
                'description' => $w->description ?? '',
                'image' => $w->image,
            ]),
            'categories' => $works->pluck('category')->unique()->values(),
        ]);
    }
}
