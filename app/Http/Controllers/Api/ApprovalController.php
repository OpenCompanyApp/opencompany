<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'respondedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'requester_id' => $request->input('requesterId'),
            'amount' => $request->input('amount'),
            'status' => 'pending',
        ]);

        return $approval->load('requester');
    }

    public function update(Request $request, string $id)
    {
        $approval = ApprovalRequest::findOrFail($id);

        $approval->update([
            'status' => $request->input('status'),
            'responded_by_id' => $request->input('respondedById'),
            'responded_at' => now(),
        ]);

        return $approval->load(['requester', 'respondedBy']);
    }
}
