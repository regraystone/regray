<?php

namespace App\Http\Requests\Account;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit account forms: a name, an email and a staff role. There is
 * no password: a new account's comes from config/accounts.php, on the server.
 *
 * The email is unique among every account, archived ones included: an archived
 * account keeps its email, so it is never free for a new one.
 */
abstract class AccountRequest extends FormRequest
{
    use ProfileValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** The account being changed, or null when one is being added. */
    abstract protected function account(): ?User;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->account()?->id),
            'role' => ['required', 'string', Rule::in(array_map(fn (Role $role): string => $role->value, Role::staffRoles()))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role.in' => __('Choose one of the staff roles.'),
            'email.unique' => __('That email already has an account.'),
        ];
    }

    /**
     * Say which account has the email when it is an archived one.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('email')) {
                    return;
                }

                $archived = User::query()
                    ->where('email', $this->input('email'))
                    ->whereNotNull('archived_at')
                    ->whereKeyNot($this->account()->id ?? 0)
                    ->exists();

                if ($archived) {
                    $validator->errors()->forget('email');
                    $validator->errors()->add('email', __('That email belongs to an archived account and can\'t be used again.'));
                }
            },
        ];
    }

    /** @return array{name: string, email: string, role: Role} */
    public function accountData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->toString(),
            'role' => Role::from($this->string('role')->toString()),
        ];
    }
}
