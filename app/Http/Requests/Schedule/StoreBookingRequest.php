<?php

namespace App\Http\Requests\Schedule;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Book an appointment: the date, arrival time, purpose and notes
 * (BookingFieldRules), and, when staff book, the client it is for.
 *
 * A client always books for themselves: a client or user id in their payload is
 * ignored. Staff send `clientId`, which must name a client account; the
 * booking is that client's and is confirmed at once (BookScheduleAction).
 */
class StoreBookingRequest extends FormRequest
{
    use BookingFieldRules;

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
            ...($this->isStaffBooking() ? ['clientId' => ['required', 'integer']] : []),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...$this->bookingFieldMessages(),
            'clientId.required' => 'Choose the client this appointment is for',
            'clientId.integer' => 'Choose the client this appointment is for',
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            $this->arrivalStillAhead(),
            function (Validator $validator): void {
                if (! $this->isStaffBooking() || $validator->errors()->has('clientId')) {
                    return;
                }

                if ($this->client() === null) {
                    $validator->errors()->add('clientId', 'Choose a client with a Regray account');
                }
            },
        ];
    }

    /** Whether staff are booking for a client, rather than a client for themselves. */
    public function isStaffBooking(): bool
    {
        return ! $this->user()->isClient();
    }

    /** The client account a staff booking is for, or null when it names none. */
    public function client(): ?User
    {
        $client = User::query()->find($this->integer('clientId'));

        return $client?->isClient() ? $client : null;
    }
}
