<?php

namespace App\Models;

use App\Data\PhAddress;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One address in a client's address book (Profile → Addresses). At most five
 * per account, exactly one of them the default while there are any
 * (ClientAddressService). An inquiry copies one when it is sent, and never
 * points back here.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $region_code
 * @property string $region_name
 * @property string|null $province_code
 * @property string|null $province_name
 * @property string $city_code
 * @property string $city_name
 * @property string $barangay_code
 * @property string $barangay_name
 * @property string|null $postal_code
 * @property string|null $lat
 * @property string|null $lng
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'label', 'region_code', 'region_name', 'province_code', 'province_name', 'city_code', 'city_name',
    'barangay_code', 'barangay_name', 'postal_code', 'lat', 'lng', 'is_default',
])]
class ClientAddress extends Model
{
    /** How many addresses one account may keep. */
    public const LIMIT = 5;

    /** The labels offered as quick picks; any other is the client's own. */
    public const QUICK_LABELS = ['Home', 'Office', 'Site'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): PhAddress
    {
        /** @var PhAddress */
        return PhAddress::fromColumns($this->getAttributes());
    }
}
