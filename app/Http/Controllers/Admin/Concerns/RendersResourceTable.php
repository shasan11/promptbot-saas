<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

trait RendersResourceTable
{
    protected function tablePage(Request $request, string $title, string $table, array $columns, array $meta = []): Response
    {
        $query = DB::table($table);

        if ($request->string('search')->isNotEmpty()) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($inner) use ($columns, $search): void {
                foreach ($columns as $column) {
                    if (($column['searchable'] ?? false) && Schema::hasColumn($table, $column['key'])) {
                        $inner->orWhere($column['key'], 'like', $search);
                    }
                }
            });
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->string('direction', 'desc')->lower()->toString() === 'asc' ? 'asc' : 'desc';

        if (! Schema::hasColumn($table, $sort)) {
            $sort = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';
        }

        return Inertia::render('Admin/ResourceIndex', [
            'title' => $title,
            'table' => $table,
            'columns' => collect($columns)->map(fn ($column) => [
                'key' => $column['key'],
                'label' => $column['label'],
                'type' => $column['type'] ?? 'text',
            ])->values(),
            'records' => $query->orderBy($sort, $direction)->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction']),
            'meta' => $meta,
        ]);
    }
}
