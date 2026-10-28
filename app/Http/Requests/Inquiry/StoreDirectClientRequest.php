<?php

namespace App\Http\Requests\Inquiry;

use App\Enums\ContactKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A direct (walk-in) client as staff take them down: a full name, a phone
 * number (required), an optional company and address, and any other ways to
 * reach them (Viber, WhatsApp, email).
 */
class StoreDirectClientRequest extends FormRequest
{
    public const CONTACT_LIMIT = 8;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'contacts' => ['required', 'array', 'min:1', 'max:'.self::CONTACT_LIMIT],
            'contacts.*.kind' => ['required', 'string', Rule::in(ContactKind::values())],
            'contacts.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fullName.required' => "Enter the client's full name.",
            'contacts.required' => 'Add at least one way to reach the client.',
            'contacts.min' => 'Add at least one way to reach the client.',
            'contacts.*.value.required' => 'Enter the contact, or remove the row.',
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $contacts = (array) $this->input('contacts', []);
                $hasPhone = collect($contacts)->contains(fn ($contact): bool => is_array($contact)
                    && ($contact['kind'] ?? null) === ContactKind::Phone->value
                    && trim((string) ($contact['value'] ?? '')) !== '');

                if (! $hasPhone) {
                    $validator->errors()->add('contacts.0.value', "Enter the client's phone number.");
                }

                foreach ($contacts as $index => $contact) {
                    $kind = ContactKind::tryFrom((string) ($contact['kind'] ?? ''));
                    $value = trim((string) ($contact['value'] ?? ''));

                    if ($value === '') {
                        continue;
                    }

                    if ($kind === ContactKind::Email && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        $validator->errors()->add("contacts.{$index}.value", 'Enter a valid email address.');
                    }

                    if ($kind !== null && $kind !== ContactKind::Email && preg_match('/^\+?[0-9\s\-().]{7,20}$/', $value) !== 1) {
                        $validator->errors()->add("contacts.{$index}.value", 'Enter a valid number.');
                    }
                }
            },
        ];
    }

    /** @return array{full_name: string, company: ?string, address: ?string, contacts: list<array{kind: string, value: string}>} */
    public function clientInput(): array
    {
        /** @var list<array{kind: string, value: string}> $contacts */
        $contacts = array_values(array_map(
            fn (array $contact): array => ['kind' => (string) $contact['kind'], 'value' => (string) $contact['value']],
            $this->validated('contacts'),
        ));

        return [
            'full_name' => (string) $this->validated('fullName'),
            'company' => $this->validated('company'),
            'address' => $this->validated('address'),
            'contacts' => $contacts,
        ];
    }
}
