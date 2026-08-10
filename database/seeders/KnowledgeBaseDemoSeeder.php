<?php

namespace Database\Seeders;

use App\Enums\Knowledge\FaqStatus;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Enums\Knowledge\SyncFrequency;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeCollection;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\User;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Models\Knowledge\KnowledgeChunk;
use Illuminate\Database\Seeder;

/**
 * Realistic development data for the Knowledge Base module.
 *
 * Deliberately not lorem ipsum: the FAQs are genuinely answerable questions, so
 * the Retrieval Playground returns something meaningful the moment the seeder
 * finishes and a developer can see the module actually working. Content is
 * embedded here rather than left pending, so retrieval works without a queue
 * worker running.
 *
 * Idempotent — safe to re-run.
 */
class KnowledgeBaseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->orderBy('id')->first();

        $support = $this->knowledgeBase('Customer Support', 'Refund, shipping and account questions answered by customer-facing agents.', $owner);
        $product = $this->knowledgeBase('Product Documentation', 'How the product works — features, limits and troubleshooting.', $owner);
        $hr = $this->knowledgeBase('Internal HR', 'Employee policies. Deliberately NOT shared with customer-facing agents.', $owner, KnowledgeVisibility::Private);

        $this->collection($support, 'Policies');
        $this->collection($support, 'Shipping');
        $this->collection($product, 'Getting started');

        $this->seedFaqs($support, [
            ['How long is the refund period?', 'Customers may request a full refund within 30 days of purchase. Refunds are issued to the original payment method and take 5–10 business days to appear. Digital goods are final sale once downloaded.', 'Billing'],
            ['Can customers return products after 20 days?', 'Yes. The refund window is 30 days, so a return requested on day 20 is within policy and should be approved without escalation.', 'Billing'],
            ['What countries do we ship to?', 'We ship to 42 countries across the EU, UK, US, Canada and Australia. Orders to other destinations cannot currently be fulfilled.', 'Shipping'],
            ['How long does delivery take?', 'Standard delivery is 3 business days within the EU and 5 within the US. Orders placed before 2pm local time ship the same day. Express delivery is available at checkout.', 'Shipping'],
            ['How does a customer cancel their subscription?', 'Subscriptions can be cancelled at any time from Account → Billing → Cancel plan. Access continues until the end of the paid period; we do not pro-rate partial months.', 'Account'],
            ['What happens when a payment fails?', 'We retry a failed payment three times over six days. The account stays active during that window. After the third failure the subscription moves to past-due and access is suspended until payment succeeds.', 'Billing'],
        ]);

        $this->seedFaqs($product, [
            ['What are the plan limits?', 'Starter includes 5 seats and 10,000 monthly messages. Pro includes 25 seats and 100,000 messages. Enterprise is unlimited seats with a negotiated message volume.', 'Pricing'],
            ['How do I connect a knowledge base to an agent?', 'Open the agent, go to Knowledge, and grant it access to the knowledge bases it should use. Agents never receive access automatically — every grant is explicit.', 'Getting started'],
            ['Why is my document showing as failed?', 'The most common cause is a scanned PDF with no embedded text. Open the document and read the error message, which explains the specific cause and the fix.', 'Troubleshooting'],
        ]);

        $this->seedFaqs($hr, [
            ['How much annual leave do employees get?', 'All permanent staff accrue 25 days of annual leave per year, plus public holidays. Leave accrues monthly and up to 5 unused days may be carried into the next year.', 'Leave'],
            ['What is the expense limit for meals?', 'Meals are reimbursed up to 40 USD per day when travelling. Receipts are required for any single expense over 25 USD.', 'Expenses'],
        ]);

        $this->command?->info('Seeded 3 knowledge bases with 11 published FAQs, embedded and searchable.');
    }

    private function knowledgeBase(
        string $name,
        string $description,
        ?User $owner,
        KnowledgeVisibility $visibility = KnowledgeVisibility::Workspace,
    ): KnowledgeBase {
        $provider = app(EmbeddingProviderFactory::class)->default();

        return KnowledgeBase::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            [
                'name' => $name,
                'description' => $description,
                'status' => KnowledgeBaseStatus::Active,
                'visibility' => $visibility,
                'default_language' => 'en',
                'embedding_provider' => $provider->name(),
                'embedding_model' => $provider->model(),
                'embedding_dimensions' => $provider->dimensions(),
                'created_by' => $owner?->id,
                'updated_by' => $owner?->id,
            ]
        );
    }

    private function collection(KnowledgeBase $base, string $name): KnowledgeCollection
    {
        return KnowledgeCollection::firstOrCreate(
            ['knowledge_base_id' => $base->id, 'slug' => \Illuminate\Support\Str::slug($name)],
            ['name' => $name, 'depth' => 0, 'status' => 'active']
        );
    }

    /** @param  array<int, array{0: string, 1: string, 2: string}>  $rows */
    private function seedFaqs(KnowledgeBase $base, array $rows): void
    {
        $source = KnowledgeSource::firstOrCreate(
            ['knowledge_base_id' => $base->id, 'source_type' => SourceType::Faq->value, 'knowledge_collection_id' => null],
            [
                'name' => 'FAQs',
                'description' => 'Structured question-and-answer knowledge.',
                'status' => SourceStatus::Ready->value,
                'sync_frequency' => SyncFrequency::Manual->value,
            ]
        );

        $index = app(KnowledgeIndexService::class);
        $provider = app(EmbeddingProviderFactory::class)->forKnowledgeBase($base);

        foreach ($rows as [$question, $answer, $category]) {
            $faq = KnowledgeFaq::firstOrCreate(
                ['knowledge_base_id' => $base->id, 'question' => $question],
                [
                    'knowledge_source_id' => $source->id,
                    'answer' => $answer,
                    'category' => $category,
                    'language' => 'en',
                    'status' => FaqStatus::Published->value,
                ]
            );

            $index->syncFaqChunks($faq->refresh());
        }

        // Embed inline rather than dispatching, so a freshly seeded environment
        // is immediately searchable without a queue worker.
        $pending = KnowledgeChunk::query()
            ->where('knowledge_base_id', $base->id)
            ->where('embedding_status', KnowledgeChunk::EMBEDDING_PENDING)
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $result = $provider->embedBatch($pending->pluck('content')->all());

        foreach ($pending as $offset => $chunk) {
            $chunk->setVector($result->vectors[$offset], $result->provider, $result->model, (int) ($base->embedding_version ?: 1));
            $chunk->is_retrievable = true;
            $chunk->save();
        }

        app(\App\Services\Knowledge\KnowledgeStatisticsService::class)->refreshForSource($source->refresh());
    }
}
