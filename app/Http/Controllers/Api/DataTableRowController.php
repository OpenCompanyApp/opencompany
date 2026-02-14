<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableRow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DataTableRowController extends Controller
{
    /**
     * @return Collection<int, DataTableRow>|LengthAwarePaginator<int, DataTableRow>
     */
    public function index(Request $request, string $tableId): Collection|LengthAwarePaginator
    {
        $query = DataTableRow::where('table_id', $tableId)
            ->with('creator');

        // Simple search across all data fields
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('data', 'like', "%{$search}%");
        }

        // Pagination
        $perPage = $request->input('perPage', 50);
        if ($request->has('page')) {
            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function show(string $tableId, string $rowId): DataTableRow
    {
        return DataTableRow::where('table_id', $tableId)
            ->with('creator')
            ->findOrFail($rowId);
    }

    public function store(Request $request, string $tableId): DataTableRow
    {
        $table = DataTable::findOrFail($tableId);

        $request->validate([
            'data' => 'required|array',
        ]);

        $row = $table->rows()->create([
            'data' => $request->input('data'),
            'created_by' => $request->input('createdBy', auth()->id()),
        ]);

        return $row->load('creator');
    }

    public function update(Request $request, string $tableId, string $rowId): DataTableRow
    {
        $row = DataTableRow::where('table_id', $tableId)
            ->findOrFail($rowId);

        if ($request->has('data')) {
            // Merge with existing data or replace entirely
            $newData = $request->input('data');
            if ($request->input('merge', false)) {
                $newData = array_merge($row->data ?? [], $newData);
            }
            $row->update(['data' => $newData]);
        }

        return $row->load('creator');
    }

    public function destroy(string $tableId, string $rowId): JsonResponse
    {
        DataTableRow::where('table_id', $tableId)
            ->findOrFail($rowId)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return Collection<int, DataTableRow>
     */
    public function bulkCreate(Request $request, string $tableId): Collection
    {
        $table = DataTable::findOrFail($tableId);

        $request->validate([
            'rows' => 'required|array',
            'rows.*.data' => 'required|array',
        ]);

        $createdBy = $request->input('createdBy', auth()->id());
        $rows = [];

        foreach ($request->input('rows') as $rowData) {
            $rows[] = $table->rows()->create([
                'data' => $rowData['data'],
                'created_by' => $createdBy,
            ]);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, DataTableRow> $collection */
        $collection = (new DataTableRow)->newCollection($rows);

        return $collection->load('creator');
    }

    public function bulkDelete(Request $request, string $tableId): JsonResponse
    {
        $request->validate([
            'rowIds' => 'required|array',
            'rowIds.*' => 'required|string',
        ]);

        DataTableRow::where('table_id', $tableId)
            ->whereIn('id', $request->input('rowIds'))
            ->delete();

        return response()->json(['success' => true]);
    }
}
