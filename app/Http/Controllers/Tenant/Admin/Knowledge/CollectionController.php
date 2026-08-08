<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Models\Knowledge\KnowledgeCollection;
use App\Services\Knowledge\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CollectionController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(private readonly KnowledgeBaseService $bases) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeCollection::class);

        $query = KnowledgeCollection::query()
            ->with(['knowledgeBase:id,uuid,name', 'parent:id,name'])
            ->withCount('documents')
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Collections/Index', [
            'collections' => $query->orderBy('knowledge_base_id')->orderBy('depth')->orderBy('sort_order')->get(),
            'bases' => $this->selectableBases(AccessLevel::Contribute),
            'filters' => $request->only(['knowledge_base']),
            'maxDepth' => KnowledgeCollection::MAX_DEPTH + 1,
            'can' => ['create' => Gate::allows('create', KnowledgeCollection::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeCollection::class);

        $data = $request->validate([
            'knowledge_base' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $base = $this->resolveBase($data['knowledge_base'], AccessLevel::Contribute);

        try {
            $this->bases->createCollection($base, $data, $request->user('tenant'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Collection created.');
    }

    public function update(Request $request, string $collection): RedirectResponse
    {
        $record = $this->resolveCollection($collection);
        Gate::authorize('update', $record);

        $record->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,archived'],
        ]));

        return back()->with('status', 'Collection updated.');
    }

    public function destroy(string $collection): RedirectResponse
    {
        $record = $this->resolveCollection($collection);
        Gate::authorize('delete', $record);

        // Documents outlive their collection: filing is organisational, and
        // deleting a folder must not destroy the knowledge inside it. They fall
        // back to the base root and stay retrievable.
        $record->documents()->update(['knowledge_collection_id' => null]);
        $record->sources()->update(['knowledge_collection_id' => null]);
        $record->faqs()->update(['knowledge_collection_id' => null]);
        $record->children()->update(['parent_id' => $record->parent_id]);

        $record->delete();

        return back()->with('status', 'Collection deleted. Its documents moved to the knowledge base root and remain searchable.');
    }

    private function resolveCollection(string $uuid): KnowledgeCollection
    {
        $collection = KnowledgeCollection::query()->where('uuid', $uuid)->first();

        if (! $collection || ! in_array($collection->knowledge_base_id, $this->allowedBaseIds(AccessLevel::Contribute), true)) {
            throw new NotFoundHttpException;
        }

        return $collection;
    }
}
