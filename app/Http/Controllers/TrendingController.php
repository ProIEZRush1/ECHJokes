<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;

class TrendingController extends Controller
{
    public function index(Request $request)
    {
        // Rank by (share_views + share_clicks*2) with decay favoring recent
        // SQLite compatible formula, no postgres-specific functions
        $calls = JokeCall::where('is_public', true)
            ->whereNotNull('recording_url')
            ->whereNotNull('share_slug')
            ->where('share_views', '>', 0)
            ->orderByRaw('(share_views + share_clicks * 2) DESC')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'share_slug', 'victim_name', 'custom_joke_prompt', 'joke_text', 'share_views', 'share_clicks', 'call_duration_seconds', 'created_at', 'user_id']);

        // Load creator names for all in one query
        $userIds = $calls->pluck('user_id')->filter()->unique();
        $users = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        $items = $calls->map(function ($c) use ($users) {
            return [
                'slug' => $c->share_slug,
                'victim_name' => $c->victim_name,
                'scenario' => \Illuminate\Support\Str::limit($c->custom_joke_prompt ?: $c->joke_text ?: '', 120),
                'views' => (int) $c->share_views,
                'creator' => $c->user_id ? ($users[$c->user_id] ?? 'Alguien') : 'Alguien',
                'duration' => (int) $c->call_duration_seconds,
                'date' => optional($c->created_at)->diffForHumans(),
            ];
        });

        return view('trending', ['items' => $items]);
    }
}
