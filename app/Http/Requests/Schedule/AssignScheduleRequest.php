<?php

namespace App\Http\Requests\Schedule;

use App\Models\User;
use App\Services\ScheduleService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Assign an appointment to a sales member: `salesId`, which must name one.
 * SchedulePolicy decides access in the controller.
 */
class AssignScheduleRequest extends FormRequest
{
    public const SALES_MESSAGE = 'Choose a sales member to confirm this appointment';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['salesId' => ['required', 'integer']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'salesId.required' => self::SALES_MESSAGE,
            'salesId.integer' => self::SALES_MESSAGE,
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('salesId') && $this->sales() === null) {
                    $validator->errors()->add('salesId', self::SALES_MESSAGE);
                }
            },
        ];
    }

    /** The sales member named, or null when `salesId` names nobody who may confirm appointments. */
    public function sales(): ?User
    {
        $sales = User::query()->find($this->integer('salesId'));

        return $sales !== null && ScheduleService::isAssignableSales($sales) ? $sales : null;
    }
}
