<?php

namespace App\Http\Controllers;

use App\Models\Preset;
use Illuminate\Http\Request;

class PresetPageController extends Controller
{
    public function index()
    {
        $presets = Preset::where('is_active', true)->whereNotNull('slug')->orderBy('sort_order')->get();
        return view('presets-index', ['presets' => $presets]);
    }

    public function show(Preset $preset)
    {
        if (!$preset->is_active) abort(404);
        $related = Preset::where('is_active', true)
            ->whereNotNull('slug')
            ->whereKeyNot($preset->id)
            ->inRandomOrder()
            ->limit(6)
            ->get();
        return view('preset-page', ['preset' => $preset, 'related' => $related]);
    }

    public function sitemap()
    {
        $base = rtrim(config('app.url'), '/');
        $urls = [
            ['loc' => $base, 'priority' => '1.0'],
            ['loc' => "$base/bromas", 'priority' => '0.9'],
            ['loc' => "$base/trending", 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => "$base/press", 'priority' => '0.5'],
        ];
        foreach (Preset::where('is_active', true)->whereNotNull('slug')->get() as $p) {
            $urls[] = ['loc' => "$base/bromas/{$p->slug}", 'priority' => '0.8', 'lastmod' => $p->updated_at?->toIso8601String()];
        }
        foreach (
            \App\Models\JokeCall::where('is_public', true)
                ->whereNotNull('share_slug')
                ->whereNotNull('recording_url')
                ->orderByDesc('share_views')
                ->limit(500)
                ->get(['share_slug', 'updated_at']) as $c
        ) {
            $urls[] = ['loc' => "$base/v/{$c->share_slug}", 'priority' => '0.7', 'lastmod' => $c->updated_at?->toIso8601String()];
        }
        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
