<?php

namespace App\Http\Requests\Catalog;

use App\Catalog\CatalogListDefinition;
use App\Enums\CatalogList;
use App\Http\Controllers\Catalog\CatalogListController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Adding or changing one item of a list under Products: the list's own rules
 * (its definition's), checked against the item being changed (`{id}`), or
 * none when one is being added.
 */
class CatalogItemRequest extends FormRequest
{
    /** @var Model|null|false The item being changed, once looked up; false until then. */
    private Model|null|false $item = false;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return CatalogListDefinition<Model> */
    public function list(): CatalogListDefinition
    {
        return CatalogList::from((string) $this->route('list'))->definition();
    }

    /** The item being changed, or null when one is being added. */
    public function item(): ?Model
    {
        if ($this->item === false) {
            $id = $this->route('id');
            $this->item = is_string($id) ? CatalogListController::find($this->list(), $id) : null;
        }

        return $this->item;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->list()->prepare($this->input()));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->list()->rules($this->item());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->list()->messages();
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        $check = $this->list()->after();

        return $check === null ? [] : [fn (Validator $validator) => $check($validator, $this->item())];
    }

    /** @return array<string, mixed> */
    public function itemData(): array
    {
        return array_diff_key($this->validated(), ['thumbnail' => true]);
    }

    public function thumbnail(): ?UploadedFile
    {
        $file = $this->file('thumbnail');

        return $file instanceof UploadedFile ? $file : null;
    }
}
