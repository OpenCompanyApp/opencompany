<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use Illuminate\Http\Request;

class DataTableColumnController extends Controller
{
    public function index(string $tableId)
    {
        return DataTableColumn::where('table_id', $tableId)
            ->orderBy('order')
            ->get();
    }

    public function store(Request $request, string $tableId)
    {
        $table = DataTable::findOrFail($tableId);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,number,date,select,multiselect,checkbox,url,email,user,attachment',
        ]);

        $maxOrder = $table->columns()->max('order') ?? -1;

        $column = $table->columns()->create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'options' => $request->input('options'),
            'order' => $maxOrder + 1,
            'required' => $request->input('required', false),
        ]);

        return $column;
    }

    public function update(Request $request, string $tableId, string $columnId)
    {
        $column = DataTableColumn::where('table_id', $tableId)
            ->findOrFail($columnId);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('type')) {
            $data['type'] = $request->input('type');
        }
        if ($request->has('options')) {
            $data['options'] = $request->input('options');
        }
        if ($request->has('order')) {
            $data['order'] = $request->input('order');
        }
        if ($request->has('required')) {
            $data['required'] = $request->input('required');
        }

        $column->update($data);

        return $column;
    }

    public function destroy(string $tableId, string $columnId)
    {
        DataTableColumn::where('table_id', $tableId)
            ->findOrFail($columnId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, string $tableId)
    {
        $request->validate([
            'columnOrders' => 'required|array',
            'columnOrders.*.id' => 'required|string',
            'columnOrders.*.order' => 'required|integer',
        ]);

        foreach ($request->input('columnOrders') as $order) {
            DataTableColumn::where('table_id', $tableId)
                ->where('id', $order['id'])
                ->update(['order' => $order['order']]);
        }

        return response()->json(['success' => true]);
    }
}
