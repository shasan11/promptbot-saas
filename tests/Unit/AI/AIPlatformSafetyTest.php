<?php

namespace Tests\Unit\AI;

use App\Neuron\Output\ConversationClassificationOutput;
use App\Services\AI\PromptService;
use App\Services\AI\ProviderErrorClassifier;
use App\Services\Tenant\TenantAuditLogService;
use App\Models\AI\Prompt;
use Illuminate\Validation\ValidationException;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Testing\FakeAIProvider;
use RuntimeException;
use Tests\TestCase;
use App\Models\AI\ProviderConfig;
use App\Services\AI\AIUsageCostService;
use App\Services\AI\AIOutputGuardrailService;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Enums\SourceType;

class AIPlatformSafetyTest extends TestCase
{
    public function test_provider_errors_do_not_expose_upstream_secrets(): void
    {
        $classified = (new ProviderErrorClassifier)->classify(new RuntimeException('request failed with sk-secret-value'));
        $this->assertSame('provider_error', $classified->safeCode);
        $this->assertStringNotContainsString('sk-secret-value', $classified->getMessage());
    }

    public function test_prompt_renderer_only_substitutes_declared_variables(): void
    {
        $prompt = new Prompt(['template' => 'Hello {{ customer_name }}', 'variables' => ['customer_name']]);
        $service = new PromptService(new TenantAuditLogService);
        $this->assertSame('Hello Ada', $service->render($prompt, ['customer_name' => 'Ada']));

        $this->expectException(ValidationException::class);
        $service->render($prompt, ['hidden_system_prompt' => 'leak']);
    }

    public function test_neuron_structured_classification_is_validated_without_network(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage(json_encode([
            'intent' => 'billing_question', 'sentiment' => 'neutral', 'urgency' => 'normal',
            'language' => 'en', 'suggestedPriority' => 'normal', 'riskFlags' => [],
        ])));
        $output = Agent::make()->setAiProvider($provider)->structured(new UserMessage('Billing question'), ConversationClassificationOutput::class, maxRetries: 0);
        $this->assertSame('billing_question', $output->intent);
        $provider->assertMethodCallCount('structured', 1);
    }

    public function test_verified_model_pricing_accounts_for_cached_and_reasoning_tokens(): void
    {
        $provider = new ProviderConfig(['configuration' => ['pricing' => ['models' => ['model-a' => [
            'currency' => 'USD', 'input_per_million' => 2, 'output_per_million' => 8,
            'cached_input_per_million' => 1, 'reasoning_per_million' => 10,
        ]]]]]);
        $estimate = (new AIUsageCostService)->estimate($provider, 'model-a', 1000, 500, 250, 100);
        $this->assertSame('USD', $estimate['currency']);
        $this->assertSame(0.00595, $estimate['cost']);
        $this->assertNull((new AIUsageCostService)->estimate($provider, 'unknown', 100, 100)['cost']);
    }

    public function test_autonomous_output_guardrail_blocks_unverified_side_effect_claims(): void
    {
        $guardrail = new AIOutputGuardrailService;
        $this->assertTrue($guardrail->inspectForAutonomousSend('You can request a refund within 30 days.')['safe']);
        $this->assertFalse($guardrail->inspectForAutonomousSend('We have already refunded your card.')['safe']);
    }

    public function test_neuron_streaming_emits_real_text_chunks_without_network(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Hello streamed world'));
        $handler = Agent::make()->setAiProvider($provider)->stream(new UserMessage('Hi'));
        $text = '';
        foreach ($handler->events() as $chunk) if ($chunk instanceof TextChunk) $text .= $chunk->content;
        $this->assertSame('Hello streamed world', $text);
        $this->assertSame('Hello streamed world', $handler->getMessage()->getContent());
    }

    public function test_neuron_multimodal_message_accepts_base64_image_content(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('A tiny image'));
        $message = new UserMessage('Describe the image');
        $message->addContent(new ImageContent('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8S8AAAAASUVORK5CYII=', SourceType::BASE64, 'image/png'));
        $reply = Agent::make()->setAiProvider($provider)->chat($message)->getMessage();
        $this->assertSame('A tiny image', $reply->getContent());
        $provider->assertMethodCallCount('chat', 1);
    }
}
