<?php

namespace App\Http\Requests;

use App\Enums\PressSourceMatchType;
use App\Enums\ProcessingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PressSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'match_type' => ['required', Rule::enum(PressSourceMatchType::class)],
            'email' => [Rule::requiredIf($this->input('match_type') === PressSourceMatchType::ExactEmail->value), 'nullable', 'email:rfc', 'max:255'],
            'domain' => [Rule::requiredIf($this->input('match_type') === PressSourceMatchType::Domain->value), 'nullable', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', 'max:253'],
            'is_active' => ['required', 'boolean'],
            'processing_mode' => ['required', Rule::enum(ProcessingMode::class)],
            'default_category' => ['nullable', 'string', 'max:255'],
            'default_tags' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->email)) : null,
            'domain' => $this->filled('domain') ? mb_strtolower(trim((string) $this->domain, " \t\n\r\0\x0B.@")) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();
        $data['email'] = $data['match_type'] === PressSourceMatchType::ExactEmail->value ? $data['email'] : null;
        $data['domain'] = $data['match_type'] === PressSourceMatchType::Domain->value ? $data['domain'] : null;
        $data['default_tags'] = collect(explode(',', (string) ($data['default_tags'] ?? '')))
            ->map(fn (string $tag) => trim($tag))->filter()->unique()->values()->all();

        return $key === null ? $data : data_get($data, $key, $default);
    }
}
