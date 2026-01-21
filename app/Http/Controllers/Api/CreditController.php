<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        $query = CreditTransaction::with(['user', 'task', 'approval']);

        if ($request->has('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
