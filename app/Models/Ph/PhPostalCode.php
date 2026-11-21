<?php

namespace App\Models\Ph;

use Illuminate\Database\Eloquent\Model;

/**
 * One PHLPost ZIP code a city or municipality is served by. Reference data,
 * read only; the pair is the key.
 *
 * @property string $postal_code
 * @property string $city_code
 */
class PhPostalCode extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
