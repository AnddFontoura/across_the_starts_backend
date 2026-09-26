<?php

namespace App\Http\Controllers;

use App\Models\Base;
use App\Models\User;
use App\Services\PlanetPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected PlanetPlacementService $placement,
    ) {
    }

    /**
     * Register a new player and create their base.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.unique' => 'Este nome de usuário já está em uso. Escolha outro.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed cast on the model
        ]);

        // Every player starts with one 1000x1000 base, dropped at a random
        // spot in the galaxy (one of 30 quadrants, up to 1000 planets each).
        $base = Base::create(['user_id' => $user->id]);
        $this->placement->place($base);

        $token = $user->createToken('game')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'token' => $token,
        ], 201);
    }

    /**
     * Log in with email + password, returning an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas estão incorretas.'],
            ]);
        }

        // Ensure a base exists (covers users created before bases existed) and
        // that it has a galaxy position (covers accounts predating quadrants).
        $base = Base::firstOrCreate([
            'user_id' => $user->id,
            'kind' => 'terrestrial',
        ]);
        if ($base->quadrant === null || $base->slot === null) {
            $this->placement->place($base);
        }

        $token = $user->createToken('game')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'token' => $token,
        ]);
    }

    /**
     * Return the currently authenticated player.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
