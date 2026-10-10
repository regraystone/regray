<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $stone_id
 * @property int $care_tip_id
 * @property int $sort_order
 */
#[Fillable(['sort_order'])]
class CareTipStone extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
