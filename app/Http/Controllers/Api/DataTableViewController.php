<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableView;
use Illuminate\Http\Request;

class DataTableViewController extends Controller
{
    public function index(string $tableId)
    {
        return DataTableView::where('table_id', $tableId)
            ->orderBy('created_at')
            ->get();
    }

    public function store(Request $request, string $tableId)
    {
        $table = DataTable::findOrFail($tableId);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:grid,kanban,gallery,calendar',
        ]);

        $view = $table->views()->create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'filters' => $request->input('filters'),
            'sorts' => $request->input('sorts'),
            'hidden_columns' => $request->input('hiddenColumns'),
        ]);

        return $view;
    }

    public function update(Request $request, string $tableId, string $viewId)
    {
        $view = DataTableView::where('table_id', $tableId)
            ->findOrFail($viewId);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('type')) {
            $data['type'] = $request->input('type');
        }
        if ($request->has('filters')) {
            $data['filters'] = $request->input('filters');
        }
        if ($request->has('sorts')) {
            $data['sorts'] = $request->input('sorts');
        }
        if ($request->has('hiddenColumns')) {
            $data['hidden_columns'] = $request->input('hiddenColumns');
        }

        $view->update($data);

        return $view;
    }

    public function destroy(string $tableId, string $viewId)
    {
        DataTableView::where('table_id', $tableId)
            ->findOrFail($viewId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
