<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 50);

        return Activity::with(['actor'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
