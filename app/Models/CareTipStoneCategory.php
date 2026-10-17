<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $stone_category_id
 * @property int $care_tip_id
 * @property int $sort_order
 */
#[Fillable(['sort_order'])]
class CareTipStoneCategory extends Model
{
    protected $table = 'care_tip_stone_category';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
