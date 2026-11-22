<?php

namespace App\Enums;

use App\Catalog\CatalogListDefinition;
use App\Catalog\Lists\ApplicationList;
use App\Catalog\Lists\CareTipList;
use App\Catalog\Lists\EdgeProfileList;
use App\Catalog\Lists\FinishList;
use App\Catalog\Lists\FurnitureTypeList;
use App\Catalog\Lists\SizeList;
use App\Catalog\Lists\StoneTypeList;
use App\Catalog\Lists\ThicknessList;
use Illuminate\Database\Eloquent\Model;

/**
 * The lists products are built from, each with its own page under Products
 * (`/dashboard/products/{list}`): the value is the page's slug. What each
 * list holds and how it is edited is its definition's (app/Catalog/Lists).
 */
enum CatalogList: string
{
    case StoneTypes = 'stone-types';
    case Sizes = 'sizes';
    case Thicknesses = 'thicknesses';
    case Finishes = 'finishes';
    case EdgeProfiles = 'edge-profiles';
    case FurnitureTypes = 'furniture-types';
    case Applications = 'applications';
    case CareTips = 'care-tips';

    /** @return CatalogListDefinition<Model> */
    public function definition(): CatalogListDefinition
    {
        /** @var CatalogListDefinition<Model> $definition Each list's own, for its own model. */
        $definition = app(match ($this) {
            self::StoneTypes => StoneTypeList::class,
            self::Sizes => SizeList::class,
            self::Thicknesses => ThicknessList::class,
            self::Finishes => FinishList::class,
            self::EdgeProfiles => EdgeProfileList::class,
            self::FurnitureTypes => FurnitureTypeList::class,
            self::Applications => ApplicationList::class,
            self::CareTips => CareTipList::class,
        });

        return $definition;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
