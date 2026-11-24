<?php

namespace App\Enums;

use App\Catalog\CatalogListDefinition;
use App\Catalog\Lists\ApplicationList;
use App\Catalog\Lists\CareTipList;
use App\Catalog\Lists\EdgeProfileList;
use App\Catalog\Lists\FinishList;
use App\Catalog\Lists\FurnitureTypeList;
use App\Catalog\Lists\QuestionList;
use App\Catalog\Lists\ServiceTypeList;
use App\Catalog\Lists\SizeList;
use App\Catalog\Lists\StoneTypeList;
use App\Catalog\Lists\ThicknessList;
use Illuminate\Database\Eloquent\Model;

/**
 * The lists the catalog is built from, each with its own page: the ones
 * products are built from under Products (`/dashboard/products/{list}`), and
 * the ones services are built from under Services (`/dashboard/services/{list}`).
 * The value is the page's slug. A list's pages and writes take its section's
 * permissions (`permission()`). What each list holds and how it is edited is
 * its definition's (app/Catalog/Lists).
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
    case ServiceTypes = 'service-types';
    case Questions = 'questions';

    public const PRODUCTS = 'products';

    public const SERVICES = 'services';

    /** The section its page is under: `products` or `services`. */
    public function section(): string
    {
        return match ($this) {
            self::ServiceTypes, self::Questions => self::SERVICES,
            default => self::PRODUCTS,
        };
    }

    /** Its section's permission to `view`, `create`, `update` or `archive`. */
    public function permission(string $ability): Permission
    {
        return Permission::from("{$this->section()}.{$ability}");
    }

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
            self::ServiceTypes => ServiceTypeList::class,
            self::Questions => QuestionList::class,
        });

        return $definition;
    }

    /** @return list<string> The slugs of the lists in this section. */
    public static function values(string $section): array
    {
        return array_values(array_map(
            fn (self $list): string => $list->value,
            array_filter(self::cases(), fn (self $list): bool => $list->section() === $section),
        ));
    }
}
