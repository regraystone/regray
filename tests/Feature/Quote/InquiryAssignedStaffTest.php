<?php

use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\InquiryHandler;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-AS1']);
});

/** A member of staff with this role and name. */
function namedStaff(Role $role, string $name): User
{
    return User::factory()->role($role)->create(['name' => $name]);
}

/** The assigned staff the details page is sent, as `$viewer` on `$route`. */
function assignedStaffFor(mixed $test, User $viewer, string $route = 'quote.show'): array
{
    return $test->actingAs($viewer)->get(route($route, 'INQ-AS1'))->assertOk()
        ->viewData('page')['props']['inquiry']['assignedStaff'];
}

it('shows the client who handles their inquiry and as what, first assigned first, then by name', function (): void {
    $this->travelTo(now()->subDay());
    assignInquiry($this->inquiry, namedStaff(Role::Technical, 'Zoe Tan'));
    $this->travelBack();
    assignInquiry($this->inquiry, namedStaff(Role::Sales, 'Ben Cruz'), namedStaff(Role::Sales, 'Amy Reyes'));

    expect(assignedStaffFor($this, $this->client))->toBe([
        ['name' => 'Zoe Tan', 'roleLabel' => 'Technical Staff'],
        ['name' => 'Amy Reyes', 'roleLabel' => 'Sales Representative'],
        ['name' => 'Ben Cruz', 'roleLabel' => 'Sales Representative'],
    ]);
});

it('sends an empty list for an inquiry nobody has been assigned to', function (): void {
    expect(assignedStaffFor($this, $this->client))->toBe([]);
});

it('sends a staff reader the same list as the client', function (): void {
    $sales = namedStaff(Role::Sales, 'Ben Cruz');
    letStaffRead($this->inquiry, $sales);
    assignInquiry($this->inquiry, namedStaff(Role::Technical, 'Zoe Tan'));

    expect(assignedStaffFor($this, $sales, 'inquiry.show'))->toBe(assignedStaffFor($this, $this->client))
        ->and(array_column(assignedStaffFor($this, $sales, 'inquiry.show'), 'name'))->toBe(['Ben Cruz', 'Zoe Tan']);
});

it('never has a handler whose role has no label: the admin cannot be assigned', function (): void {
    expect(fn () => DB::transaction(fn () => assignInquiry($this->inquiry, namedStaff(Role::Admin, 'Ada Admin'))))
        ->toThrow(QueryException::class, 'Only sales, technical, accounting or logistics staff');

    expect(assignedStaffFor($this, $this->client))->toBe([]);
});

it('reads the assignment afresh on every load, so a replaced handler is gone', function (): void {
    assignInquiry($this->inquiry, $old = namedStaff(Role::Sales, 'Ben Cruz'));
    expect(array_column(assignedStaffFor($this, $this->client), 'name'))->toBe(['Ben Cruz']);

    InquiryHandler::query()->where('inquiry_id', $this->inquiry->id)->where('user_id', $old->id)->delete();
    assignInquiry($this->inquiry, namedStaff(Role::Sales, 'Amy Reyes'));

    expect(assignedStaffFor($this, $this->client))->toBe([['name' => 'Amy Reyes', 'roleLabel' => 'Sales Representative']]);
});

it('lists neither marketing holding the lead nor whoever opened the inquiry', function (): void {
    $lead = makeLead($this->client, namedStaff(Role::Marketing, 'Mia Lead'));
    Inquiry::query()->whereKey($this->inquiry->id)->update([
        'lead_id' => $lead->id,
        'created_by' => namedStaff(Role::Sales, 'Sam Opener')->id,
    ]);
    assignInquiry($this->inquiry, namedStaff(Role::Technical, 'Zoe Tan'));

    expect(array_column(assignedStaffFor($this, $this->client), 'name'))->toBe(['Zoe Tan']);
});

it('reads every handler\'s name and role without a query per handler', function (): void {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        assignedStaffFor($this, $this->client);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    assignInquiry($this->inquiry, namedStaff(Role::Sales, 'Ben Cruz'));
    // The first request also fills the permission cache.
    $queriesFor();
    $withOne = $queriesFor();
    assignInquiry($this->inquiry, namedStaff(Role::Technical, 'Zoe Tan'), namedStaff(Role::Sales, 'Amy Reyes'));

    expect($queriesFor())->toBe($withOne);
});
