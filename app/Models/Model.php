<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    /**
     * A date as it is written to the database: in the app's zone, which is the
     * zone the database session reads it in (config/database.php).
     *
     * Eloquent formats a date without its offset, so a date in any other zone
     * (one parsed from a UTC string, say) would otherwise be stored as the
     * wrong instant. Converting first keeps the instant whatever zone it came
     * in.
     */
    public function fromDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return $this->asDateTime($value)
            ->setTimezone(config('app.timezone'))
            ->format($this->getDateFormat());
    }
}
