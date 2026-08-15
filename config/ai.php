<?php

return [
    'enabled' => (bool) env('AI_ENABLED', true),

    'queues' => [
        'high' => env('AI_QUEUE_HIGH', 'ai-high'),
        'default' => env('AI_QUEUE', 'ai-default'),
        'analysis' => env('AI_ANALYSIS_QUEUE', 'ai-analysis'),
        'evaluation' => env('AI_EVALUATION_QUEUE', 'ai-evaluation'),
        'low' => env('AI_QUEUE_LOW', 'ai-low'),
    ],

    'runtime' => [
        'default_timeout_seconds' => (int) env('AI_DEFAULT_TIMEOUT', 45),
        'maximum_timeout_seconds' => 120,
        'max_retries' => (int) env('AI_MAX_RETRIES', 2),
        'retry_backoff_seconds' => [5, 20, 60],
        'max_tool_calls' => 10,
        'max_steps' => 15,
        'max_context_tokens' => 32000,
        'provider_test_timeout_seconds' => 15,
        'circuit_failure_threshold' => 5,
        'circuit_cooldown_seconds' => 120,
    ],

    'safety' => [
        'autonomous_replies_enabled' => (bool) env('AI_AUTONOMOUS_REPLIES_ENABLED', false),
        'require_approval_for_high_risk' => true,
        'require_approval_for_critical_risk' => true,
        'require_grounding_for_factual_answers' => true,
        'require_citations_by_default' => true,
        'unknown_answer_behavior' => 'insufficient_information',
        'max_input_characters' => 100000,
    ],

    'retention' => [
        'default_days' => (int) env('AI_LOG_RETENTION_DAYS', 90),
        'maximum_days' => 365,
    ],

    'rate_limits' => [
        'interactive_per_minute' => 20,
        'provider_tests_per_hour' => 10,
        'playground_per_minute' => 15,
        'evaluations_per_hour' => 5,
    ],

    'model_parameters' => [
        'temperature' => ['min' => 0.0, 'max' => 1.5, 'default' => 0.2],
        'max_tokens' => ['min' => 64, 'max' => 8192, 'default' => 1200],
        'top_p' => ['min' => 0.0, 'max' => 1.0, 'default' => 1.0],
    ],

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'driver' => 'openai_responses',
            'requires_api_key' => true,
            'capabilities' => ['chat', 'structured_output', 'tool_calling', 'streaming', 'multimodal', 'reasoning', 'embeddings'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'driver' => 'anthropic',
            'requires_api_key' => true,
            'capabilities' => ['chat', 'structured_output', 'tool_calling', 'streaming', 'multimodal', 'reasoning'],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'driver' => 'gemini',
            'requires_api_key' => true,
            'capabilities' => ['chat', 'structured_output', 'tool_calling', 'streaming', 'multimodal', 'reasoning', 'embeddings'],
        ],
        'openai_compatible' => [
            'label' => 'OpenAI-compatible',
            'driver' => 'openai_compatible',
            'requires_api_key' => true,
            'capabilities' => ['chat', 'structured_output', 'tool_calling', 'streaming'],
        ],
        'ollama' => [
            'label' => 'Ollama',
            'driver' => 'ollama',
            'requires_api_key' => false,
            'allow_private_endpoints' => (bool) env('AI_ALLOW_PRIVATE_PROVIDER_ENDPOINTS', false),
            'capabilities' => ['chat', 'structured_output', 'tool_calling', 'streaming', 'multimodal', 'embeddings'],
        ],
    ],

    'pricing' => [
        // Per-million-token prices are tenant-overridable in provider configuration.
        // Unknown model prices remain null; the application never invents cost.
        'models' => [],
    ],

    'observability' => [
        'inspector_ingestion_key' => env('INSPECTOR_INGESTION_KEY'),
    ],

    'demo' => [
        'ollama_url' => env('AI_DEMO_OLLAMA_URL'),
        'chat_model' => env('AI_DEMO_OLLAMA_CHAT_MODEL', 'gemma3:4b'),
        'embedding_model' => env('AI_DEMO_OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text:latest'),
    ],
];
