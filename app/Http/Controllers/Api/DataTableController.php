<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataTable;
use Illuminate\Http\Request;

class DataTableController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DataTable>
     */
    public function index(): \Illuminate\Database\Eloquent\Collection
    {
        return DataTable::with(['creator', 'columns'])
            ->withCount('rows')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(string $id): DataTable
    {
        return DataTable::with(['creator', 'columns', 'views'])
            ->withCount('rows')
            ->findOrFail($id);
    }

    public function store(Request $request): DataTable
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $table = DataTable::create([
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
        $table = DataTable::findOrFail($id);

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
        DataTable::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
