<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Source extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'base_url',
        'description',
        'detection_method',
        'selector_title',
        'selector_body',
        'selector_image',
        'schedule_type',
        'schedule_value',
        'last_scraped_at',
        'next_scrape_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'next_scrape_at' => 'datetime',
    ];
}
