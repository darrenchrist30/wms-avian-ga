<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    // GET /api-tokens
    public function index()
    {
        $tokens = PersonalAccessToken::with('tokenable')
            ->where('tokenable_type', \App\Models\User::class)
            ->orderByDesc('created_at')
            ->get();

        return view('api-tokens.index', compact('tokens'));
    }

    // POST /api-tokens
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ], [
            'name.required' => 'Nama token wajib diisi.',
            'name.max'      => 'Nama token maksimal 100 karakter.',
        ]);

        $user      = auth()->user();
        $token     = $user->createToken($request->name);
        $plainText = $token->plainTextToken;

        return back()
            ->with('new_token', $plainText)
            ->with('new_token_name', $request->name)
            ->with('success', 'Token berhasil dibuat.');
    }

    // DELETE /api-tokens/{id}
    public function destroy($id)
    {
        $token = PersonalAccessToken::findOrFail($id);
        $name  = $token->name;
        $token->delete();

        return back()->with('success', "Token \"{$name}\" berhasil direvoke.");
    }
}
