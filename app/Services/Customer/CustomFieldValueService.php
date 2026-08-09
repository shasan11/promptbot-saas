<?php

namespace App\Services\Customer;

use App\Models\Customer\CustomField;
use App\Models\Customer\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomFieldValueService
{
    public function validateAndStore(Model $resource, string $resourceType, array $submitted): void
    {
        $fields = CustomField::query()->where('resource_type', $resourceType)->where('active', true)->orderBy('display_order')->get();

        $errors = [];
        foreach ($fields as $field) {
            $value = $submitted[$field->key] ?? $field->default_value;
            $rules = $this->rulesFor($field);
            $validator = Validator::make(['value' => $value], ['value' => $rules]);

            if ($validator->fails()) {
                $errors["custom_fields.{$field->key}"] = $validator->errors()->get('value');
                continue;
            }

            CustomFieldValue::query()->updateOrCreate([
                'custom_field_id' => $field->id,
                'resource_type' => $resourceType,
                'resource_id' => $resource->getKey(),
            ], ['value' => $value]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function rulesFor(CustomField $field): array
    {
        $rules = $field->required ? ['required'] : ['nullable'];
        $typeRules = match ($field->field_type) {
            'integer' => ['integer'],
            'decimal', 'currency' => ['numeric'],
            'boolean' => ['boolean'],
            'date' => ['date_format:Y-m-d'],
            'datetime' => ['date'],
            'email' => ['email:rfc'],
            'url' => ['url:http,https'],
            'single_select' => ['string', 'in:'.implode(',', $field->options ?? [])],
            'multi_select' => ['array'],
            default => ['string'],
        };

        return array_merge($rules, $typeRules, $field->validation['rules'] ?? []);
    }
}
