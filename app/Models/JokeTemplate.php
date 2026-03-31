<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JokeTemplate extends Model
{
    protected $fillable = ['category', 'joke_text'];

    /**
     * Get a random joke template for a category.
     */
    public static function randomForCategory(string $category): ?string
    {
        $template = static::where('category', $category)
            ->inRandomOrder()
            ->first();

        return $template?->joke_text;
    }
}
