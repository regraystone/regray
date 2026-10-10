<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $stone_id
 * @property Carbon $created_at
 */
#[Fillable([])]
class SavedStone extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;
}
