<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tip
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['tip'])]
class CareTip extends Model
{
    public $timestamps = true;
}
