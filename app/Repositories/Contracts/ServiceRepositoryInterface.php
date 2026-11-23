<?php

namespace App\Repositories\Contracts;

use App\Models\DetailField;
use App\Models\ServiceType;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface
{
    /**
     * The service types still on the admin's Services page, each with its
     * services still on it, offered or not.
     *
     * @return Collection<int, ServiceType>
     */
    public function typesForAdmin(): Collection;

    /**
     * Every question a service can ask, with its active listed answers.
     *
     * @return Collection<int, DetailField>
     */
    public function questions(): Collection;

    /**
     * The stone types a service can be made from.
     *
     * @return Collection<int, StoneType>
     */
    public function activeStoneTypes(): Collection;
}
