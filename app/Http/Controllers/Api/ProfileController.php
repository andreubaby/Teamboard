<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'unique:users,email,' . $user->id],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->hasFile('avatar')) {
            // 1. Obtener el path original (sin URL) para borrar el archivo físico
            $oldPath = $user->getRawOriginal('avatar_url');
            if ($oldPath && \Storage::disk('public')->exists($oldPath)) {
                \Storage::disk('public')->delete($oldPath);
            }

            // 2. Guardar en la carpeta 'avatars' dentro del disco público
            $path = $request->file('avatar')->store('avatars', 'public');

            // 3. Guardar solo el path relativo
            $user->avatar_url = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado con éxito',
            'user' => $user->fresh() // Fuerza al modelo a usar el accessor con la nueva URL
        ]);
    }
}
