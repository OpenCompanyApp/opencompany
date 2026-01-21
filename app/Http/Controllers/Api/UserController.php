<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::orderBy('name')->get();
    }

    public function agents()
    {
        return User::where('type', 'agent')->orderBy('name')->get();
    }

    public function show(string $id)
    {
        return User::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->only([
            'name',
            'email',
            'avatar',
            'status',
            'agent_type',
            'system_prompt',
        ]));

        return $user;
    }

    public function updatePresence(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'presence' => $request->input('presence'),
            'last_active_at' => now(),
        ]);

        return $user;
    }
}
