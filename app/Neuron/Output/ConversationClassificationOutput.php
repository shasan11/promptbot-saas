<?php

namespace App\Neuron\Output;

use NeuronAI\StructuredOutput\SchemaProperty;

class ConversationClassificationOutput
{
    #[SchemaProperty(description: 'Short intent label using lowercase snake_case.', required: true, maxLength: 48)]
    public string $intent;

    #[SchemaProperty(description: 'Customer sentiment: positive, neutral, negative, or mixed.', required: true)]
    public string $sentiment;

    #[SchemaProperty(description: 'Urgency: low, normal, high, or urgent.', required: true)]
    public string $urgency;

    #[SchemaProperty(description: 'Detected ISO 639-1 language code.', required: true, maxLength: 12)]
    public string $language;

    #[SchemaProperty(description: 'Suggested support priority: low, normal, high, or urgent.', required: true)]
    public string $suggestedPriority;

    #[SchemaProperty(description: 'Short risk flags supported by the conversation. Empty when none.', required: true)]
    public array $riskFlags = [];
}
