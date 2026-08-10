# AI platform implementation report

## 1-6. Architecture and persistence

1. **Architecture:** tenant-domain services under `app/Services/AI`, thin tenant controllers, Neuron runtime/adapters, tenant queue jobs, and Inertia React administration pages.
2. **Neuron package:** `neuron-core/neuron-ai` 3.16.x, locked by Composer.
3. **Migration:** `database/migrations/tenant/0001_01_01_000027_create_tenant_ai_platform_tables.php`.
4. **Tables:** provider configs, prompts/versions, agents/versions/tools/channels, runs, usage, suggestions, insights, approvals, tool calls, feedback, evaluation suites/cases/runs/results.
5. **Models:** the corresponding tenant models live in `app/Models/AI` with encrypted credentials and public UUID binding.
6. **Services:** provider resolution/health/circuit/errors, budgets/pricing, Agent configuration/runtime, knowledge/tool composition, approvals, copilots, guardrails, autonomous sending, settings, prompts, and feedback/evaluation support.

## 7-12. Execution surface

7. **Jobs:** conversation/ticket analysis, autonomous reply, evaluation suite, and retention/approval maintenance jobs use dedicated AI queues and tenant middleware.
8. **Routes:** tenant administration `/ai`, bounded copilot/report/customer endpoints, and scoped `/tenant-api/v1/ai/agents` endpoints.
9. **Controllers:** providers, Agents, playground, prompts, approvals, settings, operations, evaluations, inbox/ticket/customer/report features, and developer API.
10. **React:** native PromptBot pages for Overview, Agents, Playground, Prompts, Providers, Approvals, Evaluations, Usage, Logs, and Settings plus embedded copilot panels.
11. **Providers:** OpenAI Responses, Anthropic, Gemini, OpenAI-compatible, and Ollama with capability checks, endpoint safety, health tests, encrypted credentials, and bounded parameters.
12. **Agent capabilities:** immutable deploy versions, compare/restore into draft, grounding/citations, structured classification, real streaming, images, reasoning effort, bounded context, tools, channel assignment, and explicit deployment modes.

## 13-20. Grounding, tools, product, and controls

13. **Knowledge:** existing `knowledge_access_grants` are authoritative; real Neuron embeddings use existing MySQL vector storage and preserve source/page citation lineage.
14. **Tools/MCP:** only active, AI-enabled `ConnectionAction` records explicitly assigned and granted to an Agent become Neuron tools.
15. **Approval:** risk policy is server-owned; high/critical actions persist an encrypted resumable approval, notify approvers, lock decisions, and execute idempotently after approval.
16. **Inbox:** summary, classification, cited draft, rewrite, translation, next actions, persisted insights/suggestions, accept/reject feedback, and rolling summary context.
17. **Automation:** analysis queues after commit for conversations/tickets without creating message feedback loops.
18. **Permissions:** granular provider/Agent/copilot/tool/approval/evaluation/usage/log/settings permissions seeded into default tenant roles.
19. **Plan limits:** `ai_platform`, `ai_monthly_tokens`, `ai_agents`, and opt-in `ai_autonomous_replies` features.
20. **Security:** tenant databases, encrypted/hidden secrets, SSRF guards, prompt fences, redaction, idempotency, approval gates, output guardrails, budgets, rate limits, and safe provider errors.

## 21-30. Quality and operations

21. **Evaluations:** tenant suites with grounding, hallucination, injection, classification, multilingual, approval, regex/content/citation/latency assertions and stored results.
22. **PHPUnit:** focused safety coverage uses Neuron `FakeAIProvider` for structured output, streaming, multimodal content, pricing, prompt allowlists, error redaction, and autonomous guardrails.
23. **Queues:** `ai-high`, `ai-default`, `ai-analysis`, `ai-evaluation`, and `ai-low`.
24. **Scheduler:** daily `ai:maintain` expires approvals and removes retained logs according to tenant policy.
25. **Environment:** documented in `.env.example`; tenant provider keys remain encrypted in tenant databases, never `.env`.
26. **Documentation:** `docs/AI_PLATFORM.md`, this report, README link, configuration comments, and honest demo seeder.
27. **Known operational limit:** provider/model hardware must answer within the Agent ceiling (maximum 120 seconds). Unknown prices remain unknown. Autonomous mode remains opt-in at every layer.
28. **Focused results:** production frontend build, route cache, Composer validation, AI PHP lint, focused unit tests, real Ollama health, grounded chat, semantic embeddings, and real Neuron stream.
29. **Build:** `npm run build` is the frontend release gate.
30. **Stakeholder demo:** follow the exact ordered flow in `docs/AI_PLATFORM.md`; the seeded `acme` workspace includes a healthy local provider, cited knowledge, deployed text/vision Agents, channel, low/high-risk tool assignments, and a sample inbound conversation when the documented local Ollama variables are present.
