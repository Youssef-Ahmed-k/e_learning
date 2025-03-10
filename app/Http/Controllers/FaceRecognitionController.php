<?php

namespace App\Http\Controllers;

use App\Models\FaceData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FaceRecognitionController extends Controller
{
    private $apiUrl = 'http://localhost:8001/';

    public function registerFace(Request $request)
    {
        $request->validate([
            'face_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5000',
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // store the face image
        $path = $request->file('face_image')->store('face_images', 'public');
        $fullPath = Storage::disk('public')->path($path);

        // call the face recognition API
        $response = Http::attach(
            'file',
            file_get_contents($fullPath),
            basename($fullPath)
        )->post($this->apiUrl . 'register_face/', [
            'name' => $user->id
        ]);

        if ($response->successful()) {
            // store face data
            FaceData::create([
                'user_id' => $user->id,
                'face_image_path' => $path,
                'face_embedding' => json_encode($response->json('face_embedding')),
            ]);

            return response()->json([
                'message' => 'Face registered successfully',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'message' => 'Something went wrong',
            'error' => $response->json()
        ], 500);
    }
}