<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataTable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTableController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DataTable>
     */
    public function index(): \Illuminate\Database\Eloquent\Collection
    {
        return DataTable::forWorkspace()->with(['creator', 'columns'])
            ->withCount('rows')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(string $id): DataTable
    {
        return DataTable::forWorkspace()->with(['creator', 'columns', 'views'])
            ->withCount('rows')
            ->findOrFail($id);
    }

    public function store(Request $request): DataTable
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $table = DataTable::create([
            'workspace_id' => workspace()->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'icon' => $request->input('icon'),
            'created_by' => $request->input('createdBy', auth()->id()),
        ]);

        // Create default columns if provided
        if ($request->has('columns')) {
            foreach ($request->input('columns') as $index => $column) {
                $table->columns()->create([
                    'name' => $column['name'],
                    'type' => $column['type'] ?? 'text',
                    'options' => $column['options'] ?? null,
                    'order' => $index,
                    'required' => $column['required'] ?? false,
                ]);
            }
        }

        return $table->load(['creator', 'columns']);
    }

    public function update(Request $request, string $id): DataTable
    {
        $table = DataTable::forWorkspace()->findOrFail($id);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }
        if ($request->has('icon')) {
            $data['icon'] = $request->input('icon');
        }

        $table->update($data);

        return $table->load(['creator', 'columns']);
    }

    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        DataTable::forWorkspace()->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Export a workspace-scoped table as JSON or CSV.
     *
     * The export reads only table-owned columns and rows, preserving the
     * workspace boundary enforced by the initial table lookup.
     */
    public function export(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $format = $request->string('format', 'json')->toString();
        $table = DataTable::forWorkspace()->with(['columns', 'rows'])->findOrFail($id);

        if ($format === 'json') {
            return response()->json([
                'table' => $table,
                'columns' => $table->columns,
                'rows' => $table->rows,
            ]);
        }

        abort_unless($format === 'csv', 422, 'Unsupported export format.');

        return response()->streamDownload(function () use ($table) {
            $output = fopen('php://output', 'w');
            $columns = $table->columns;
            fputcsv($output, $columns->pluck('name')->all());

            foreach ($table->rows as $row) {
                $data = $row->data ?? [];
                fputcsv($output, $columns->map(fn ($column) => $data[$column->id] ?? '')->all());
            }

            fclose($output);
        }, $table->name.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import rows from CSV or JSON into an existing table.
     */
    public function import(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
            'format' => 'required|string|in:csv,json',
        ]);

        $table = DataTable::forWorkspace()->with('columns')->findOrFail($id);
        $rows = [];

        if ($request->string('format')->toString() === 'json') {
            $decoded = json_decode($request->file('file')->get(), true);
            $rows = collect(is_array($decoded) ? ($decoded['rows'] ?? $decoded) : [])
                ->map(fn ($row) => $table->rows()->create([
                    'data' => $row['data'] ?? $row,
                    'created_by' => auth()->id(),
                ]))
                ->values()
                ->all();
        } else {
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $headers = $handle ? fgetcsv($handle) : false;
            $columnsByName = $table->columns->keyBy('name');

            while ($handle && ($values = fgetcsv($handle)) !== false) {
                $data = [];
                foreach (($headers ?: []) as $index => $header) {
                    $column = $columnsByName->get($header);
                    if ($column) {
                        $data[$column->id] = $values[$index] ?? null;
                    }
                }
                $rows[] = $table->rows()->create([
                    'data' => $data,
                    'created_by' => auth()->id(),
                ]);
            }
            if ($handle) {
                fclose($handle);
            }
        }

        return response()->json(['rows' => $rows]);
    }

    /**
     * Duplicate a table, including columns, views, and row data.
     */
    public function duplicate(string $id): DataTable
    {
        $source = DataTable::forWorkspace()->with(['columns', 'views', 'rows'])->findOrFail($id);

        $table = DataTable::create([
            'workspace_id' => workspace()->id,
            'name' => $source->name.' Copy',
            'description' => $source->description,
            'icon' => $source->icon,
            'created_by' => auth()->id(),
        ]);

        foreach ($source->columns as $column) {
            $table->columns()->create($column->only(['name', 'type', 'options', 'order', 'required']));
        }
        foreach ($source->views as $view) {
            $table->views()->create($view->only(['name', 'type', 'filters', 'sorts', 'hidden_columns', 'config']));
        }
        foreach ($source->rows as $row) {
            $table->rows()->create([
                'data' => $row->data,
                'created_by' => auth()->id(),
            ]);
        }

        return $table->load(['creator', 'columns', 'views'])->loadCount('rows');
    }
}
