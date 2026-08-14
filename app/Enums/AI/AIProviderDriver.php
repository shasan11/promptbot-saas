<?php

namespace App\Enums\AI;

enum AIProviderDriver: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case OpenRouter = 'openrouter';
    case Groq = 'groq';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
            self::Mistral => 'Mistral',
            self::Ollama => 'Ollama',
            self::Custom => 'Custom OpenAI-Compatible',
        };
    }

    /** Providers that speak the OpenAI chat-completions/embeddings HTTP shape. */
    public function isOpenAiCompatible(): bool
    {
        return match ($this) {
            self::OpenAI, self::OpenRouter, self::Groq, self::Mistral, self::Custom => true,
            default => false,
        };
    }

    public function requiresApiKey(): bool
    {
        return $this !== self::Ollama;
    }

    public function defaultBaseUrl(): ?string
    {
        return match ($this) {
            self::OpenAI => 'https://api.openai.com/v1',
            self::Anthropic => 'https://api.anthropic.com',
            self::Gemini => 'https://generativelanguage.googleapis.com',
            self::OpenRouter => 'https://openrouter.ai/api/v1',
            self::Groq => 'https://api.groq.com/openai/v1',
            self::Mistral => 'https://api.mistral.ai/v1',
            self::Ollama => 'http://localhost:11434',
            self::Custom => null,
        };
    }
}
