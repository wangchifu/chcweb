<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $fillable = [
        'semester',
        'name',
        'track',
        'field',
        'frequency',
        'numbers',
        'disable',
        'open',
        'started_at',
        'stopped_at',
    ];

    public function items()
    {
        return $this->hasMany(Item::class)->orderBy('order');
    }
}
