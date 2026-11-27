<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ClientKind;
use App\Enums\ScheduleKind;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\ScheduleService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Book an appointment: the date, arrival time, purpose and notes
 * (BookingFieldRules), and, when staff book, the client it is for and the
 * sales member who confirms it.
 *
 * A client also says what it is (`kind`, a showroom visit when left out):
 * for an installation or a delivery, `orderReference` names one of their
 * accepted orders that takes it (ScheduleService::orderRefusal()), and the
 * purpose is not asked, as the order says what it is for. Staff book showroom
 * visits only, so `kind` in their payload is ignored.
 *
 * A client always books for themselves: a client, user or sales id in their
 * payload is ignored. Staff (marketing) send who it is for, either
 * `clientId`, a client account they may find (User::scopeClientsVisibleTo()),
 * or `directClientId`, a direct (walk-in) client record, and `salesId`, which
 * must name a sales member; the booking is the client's, awaiting that sales
 * member (BookScheduleAction).
 */
class StoreBookingRequest extends FormRequest
{
    use BookingFieldRules;

    public const CLIENT_MESSAGE = 'Choose the client this appointment is for';

    public const ORDER_MESSAGE = 'Choose one of your accepted orders';

    /** The order named, once looked up: false until then. */
    private Inquiry|false|null $order = false;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->bookingFieldRules(),
            ...($this->isStaffBooking() ? [] : [
                'kind' => ['nullable', 'string', Rule::enum(ScheduleKind::class)],
            ]),
            ...($this->kind()->isForOrder() ? [
                'purpose' => ['nullable', 'string', 'max:80'],
                'orderReference' => ['required', 'string', 'max:30'],
            ] : []),
            ...($this->isStaffBooking() ? [
                'clientId' => ['required_without:directClientId', 'nullable', 'integer'],
                'directClientId' => ['nullable', 'integer'],
                'salesId' => ['required', 'integer'],
            ] : []),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->bookingFieldMessages(),
            'clientId.required_without' => self::CLIENT_MESSAGE,
            'clientId.integer' => self::CLIENT_MESSAGE,
            'directClientId.integer' => self::CLIENT_MESSAGE,
            'salesId.required' => AssignScheduleRequest::SALES_MESSAGE,
            'salesId.integer' => AssignScheduleRequest::SALES_MESSAGE,
            'kind.enum' => 'Choose what you would like to schedule',
            'orderReference.required' => self::ORDER_MESSAGE,
            'orderReference.string' => self::ORDER_MESSAGE,
            'orderReference.max' => self::ORDER_MESSAGE,
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            $this->arrivalStillAhead(),
            function (Validator $validator): void {
                if (! $this->kind()->isForOrder() || $validator->errors()->hasAny(['kind', 'orderReference'])) {
                    return;
                }

                $refusal = app(ScheduleService::class)->orderRefusal($this->order(), $this->kind());

                if ($refusal !== null) {
                    $validator->errors()->add('orderReference', $refusal);
                }
            },
            function (Validator $validator): void {
                if (! $this->isStaffBooking()) {
                    return;
                }

                if ($this->filled('directClientId')) {
                    if (! $validator->errors()->has('directClientId') && $this->directClient() === null) {
                        $validator->errors()->add('clientId', 'Choose a direct client on file');
                    }
                } elseif (! $validator->errors()->has('clientId') && $this->client() === null) {
                    $validator->errors()->add('clientId', 'Choose a client with a Regray account');
                }

                if (! $validator->errors()->has('salesId') && $this->sales() === null) {
                    $validator->errors()->add('salesId', AssignScheduleRequest::SALES_MESSAGE);
                }
            },
        ];
    }

    /**
     * What is booked: what a client chose, a showroom visit when they chose
     * nothing or something unknown (which the rules refuse), and always a
     * showroom visit for staff.
     */
    public function kind(): ScheduleKind
    {
        if ($this->isStaffBooking()) {
            return ScheduleKind::ShowroomVisit;
        }

        return ScheduleKind::tryFrom($this->string('kind')->value()) ?? ScheduleKind::ShowroomVisit;
    }

    /** The client's accepted order an installation or a delivery is for, or null when it names none of theirs. */
    public function order(): ?Inquiry
    {
        if ($this->order === false) {
            $this->order = $this->filled('orderReference')
                ? app(ScheduleService::class)->findOrder($this->user(), $this->string('orderReference')->trim()->value())
                : null;
        }

        return $this->order;
    }

    /** Whether staff are booking for a client, rather than a client for themselves. */
    public function isStaffBooking(): bool
    {
        return ! $this->user()->isClient();
    }

    /**
     * Who a staff booking is for: the walk-in when it names one, else the
     * client account. Null when it names neither.
     */
    public function bookedFor(): User|Client|null
    {
        return $this->filled('directClientId') ? $this->directClient() : $this->client();
    }

    /**
     * The client account a staff booking is for, or null when it names none
     * the booker may find.
     */
    public function client(): ?User
    {
        return User::query()->clientsVisibleTo($this->user())->find($this->integer('clientId'));
    }

    /** The direct (walk-in) client record a staff booking is for, or null when it names none. */
    public function directClient(): ?Client
    {
        return Client::query()->where('kind', ClientKind::Direct->value)->find($this->integer('directClientId'));
    }

    /** The sales member a staff booking is assigned to, or null when it names none. */
    public function sales(): ?User
    {
        $sales = User::query()->find($this->integer('salesId'));

        return $sales !== null && ScheduleService::isAssignableSales($sales) ? $sales : null;
    }
}
