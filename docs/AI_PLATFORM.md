# Tenant AI platform

PromptBot's AI module is tenant-scoped and optional. It uses Neuron AI for provider calls, structured output, streaming, multimodal content, tools, and embeddings while reusing PromptBot's knowledge permissions, connections, queues, roles, audit logs, and plan features.

## Production setup

1. Run `php artisan migrate --force` and `php artisan tenants:migrate --force`.
2. Seed `FeatureSeeder`, `PlanSeeder`, and `TenantAuthorizationSeeder` for the relevant databases.
3. Run workers for `ai-high`, `ai-default`, `ai-analysis`, `ai-evaluation`, and `ai-low` alongside the existing queues.
4. Run Laravel's scheduler every minute. `ai:maintain` expires approvals and applies each workspace's retention setting.
5. In a workspace, open **AI platform -> Providers**, add a provider, enter verified per-million-token prices when known, and run the health test.
6. For semantic search, choose **Tenant AI provider**, confirm the embedding dimensions for the configured model, and re-index each affected knowledge base.
7. Create an Agent, grant its `agent_key` explicit knowledge and connection access, assign channels/tools, and deploy an immutable version.

Unknown model prices remain blank. Cost budgets are denominated in USD and only count usage records carrying verified USD pricing. Other currencies remain separated in the Usage screen.

## Interactive capabilities

The playground uses a real Neuron stream over authenticated tenant SSE. A successful final result is persisted after the stream completes. A cancelled run is marked `cancelled`; a partial response is never counted as successful. Up to four JPEG, PNG, GIF, or WebP images (5 MB each) can be supplied to a provider/model advertising multimodal support. Image bytes are sent to the provider but only count and SHA-256 hashes enter run metadata.

Reasoning effort is an Agent-level safe parameter. PromptBot maps it to the selected provider's supported request shape and does not expose model chain-of-thought. Streaming only emits a generic reasoning status.

## Autonomous reply safety

Autonomous replies are off by default. Enabling them requires all of the following:

- `AI_AUTONOMOUS_REPLIES_ENABLED=true` at platform level;
- the plan feature `ai_autonomous_replies`;
- explicit workspace, Agent, and channel enablement;
- workspace human-review mode disabled;
- deployed autonomous Agent and channel assignment;
- no urgent/risk classification or pending tool approval;
- required grounding and citations;
- output guardrails passing;
- healthy provider, available budget, successful run, inbound message still latest, and a valid recipient.

Generation and sending are separate services. If a send gate fails, the generated suggestion remains available for human review. Every send decision stores its complete check set, Agent version, run, citations, and timestamp.

## Safety model

- Copilot output is a suggestion and is never sent silently.
- Retrieved content, conversations, historical summaries, and image text are fenced as untrusted data.
- Citation-required Agents return insufficient information without a model call when permitted retrieval is empty.
- High and critical connection actions require approval. Tool inputs are redacted; encrypted resume data is hidden.
- Provider calls and external actions happen outside database transactions.
- Private endpoints require both the platform environment gate and a tenant setting.

## Real local demo

When a local Ollama service is intentionally available, configure:

```env
AI_ALLOW_PRIVATE_PROVIDER_ENDPOINTS=true
AI_DEMO_OLLAMA_URL=http://127.0.0.1:11434/api
AI_DEMO_OLLAMA_CHAT_MODEL=phi4-mini:latest
AI_DEMO_OLLAMA_EMBEDDING_MODEL=nomic-embed-text:latest
KNOWLEDGE_EMBEDDING_PROVIDER=tenant_ai
KNOWLEDGE_AI_EMBEDDING_DIMENSIONS=768
```

Run the knowledge, connection, and AI demo seeders in one initialized demo tenant. `AIDemoSeeder` never creates fake credentials: it configures Ollama only when the explicit demo URL exists, performs a real health check, and otherwise requires an already healthy provider.

Focused real checks:

```bash
php artisan tenants:run ai:smoke --tenants=acme --option="agent=demo_support_copilot" --option="message=What is the documented refund period?"
php artisan tenants:run ai:smoke --tenants=acme --option="agent=demo_support_copilot" --option="message=What is the documented refund period?" --option="stream=1"
```

For multimodal smoke checks use `--agent=demo_vision_assistant --option="vision=1"`. CPU-only vision models may exceed the deliberate 120-second platform ceiling; that is a provider-capacity failure, and PromptBot records it safely.

The stakeholder flow is: configure/test provider -> create Agent -> grant knowledge -> assign tools/channels -> deploy -> receive message -> classify/summarize -> generate cited draft -> edit/send -> request/approve a high-risk tool -> inspect Usage/Logs -> run Evaluations.

See `.env.example` for all queue, timeout, retention, endpoint, autonomous, and demo variables. See `docs/AI_IMPLEMENTATION_REPORT.md` for the implementation inventory and verification evidence.
