<?php

namespace App\Services\AI;

use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIPurpose;
use App\Models\AiModel;
use App\Services\AI\Exceptions\AIConfigurationMissingException;
use Illuminate\Support\Collection;

class AIModelResolver
{
    /** @return Collection<int, AiModel> ordered candidates, most-preferred first */
    public function chainFor(AIPurpose $purpose, AIModelCapability $capability): Collection
    {
        $assigned = AiModel::query()
            ->join('ai_model_assignments', 'ai_model_assignments.ai_model_id', '=', 'ai_models.id')
            ->join('ai_providers', 'ai_providers.id', '=', 'ai_models.ai_provider_id')
            ->where('ai_model_assignments.purpose', $purpose->value)
            ->where('ai_model_assignments.is_enabled', true)
            ->where('ai_models.is_enabled', true)
            ->where('ai_models.capability', $capability->value)
            ->where('ai_providers.is_enabled', true)
            ->orderBy('ai_model_assignments.priority')
            ->select('ai_models.*')
            ->get();

        if ($assigned->isNotEmpty()) {
            return $assigned;
        }

        $default = AiModel::query()
            ->join('ai_providers', 'ai_providers.id', '=', 'ai_models.ai_provider_id')
            ->where('ai_models.is_default_for_capability', true)
            ->where('ai_models.is_enabled', true)
            ->where('ai_models.capability', $capability->value)
            ->where('ai_providers.is_enabled', true)
            ->orderBy('ai_providers.priority')
            ->select('ai_models.*')
            ->get();

        if ($default->isEmpty()) {
            throw AIConfigurationMissingException::noModelsConfigured($purpose->value);
        }

        return $default;
    }
}
