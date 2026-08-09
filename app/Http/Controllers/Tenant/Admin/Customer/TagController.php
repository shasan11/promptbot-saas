<?php

namespace App\Http\Controllers\Tenant\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('tags.manage'), 403);
        return Inertia::render('Tenant/Admin/Customers/Tags/Index', ['tags' => Tag::query()->withCount(['contacts', 'companies'])->orderBy('name')->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('tags.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:tags,name'], 'description' => ['nullable', 'string', 'max:1000'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        Tag::create(array_merge($data, ['slug' => $this->uniqueSlug($data['name']), 'created_by' => $request->user('tenant')->id]));
        return back()->with('status', 'Tag created.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('tags.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('tags')->ignore($tag->id)], 'description' => ['nullable', 'string', 'max:1000'], 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'status' => ['required', 'in:active,archived']]);
        $tag->update($data); return back()->with('status', 'Tag updated.');
    }

    public function merge(Request $request, Tag $tag): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('tags.manage'), 403);
        $data = $request->validate(['destination_id' => ['required', 'exists:tags,id', Rule::notIn([$tag->id])]]);
        DB::transaction(function () use ($tag, $data): void {
            DB::table('taggables')->where('tag_id', $tag->id)->get()->each(fn ($row) => DB::table('taggables')->insertOrIgnore(['tag_id' => $data['destination_id'], 'taggable_type' => $row->taggable_type, 'taggable_id' => $row->taggable_id, 'assigned_by' => $row->assigned_by, 'created_at' => $row->created_at]));
            $tag->delete();
        });
        return back()->with('status', 'Tags merged.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name); $slug = $base; $suffix = 1;
        while (Tag::query()->where('slug', $slug)->exists()) $slug = $base.'-'.++$suffix;
        return $slug;
    }
}
