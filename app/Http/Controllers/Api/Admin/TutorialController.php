<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tutorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TutorialController extends Controller
{
    /**
     * Display a listing of tutorials.
     */
    public function index(): JsonResponse
    {
        $tutorials = Tutorial::latest()->get()->map(function ($tutorial) {
            if ($tutorial->video_type === 'file') {
                $tutorial->video_url = url($tutorial->video_source);
            } else {
                $tutorial->video_url = $tutorial->video_source;
            }
            return $tutorial;
        });

        return response()->json([
            'success' => true,
            'data' => $tutorials,
        ]);
    }

    /**
     * Store a newly created tutorial.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm', 'max:51200'],
            'video_link' => ['nullable', 'string'],
        ]);

        $hasFile = $request->hasFile('video_file');
        $hasLink = !empty(trim($request->input('video_link', '')));

        // Mutual exclusivity check: exactly one of video_file or video_link must be supplied
        if (($hasFile && $hasLink) || (!$hasFile && !$hasLink)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih salah satu antara unggah file video atau masukkan link video.',
                'errors' => [
                    'video_file' => ['Pilih salah satu antara unggah file video atau masukkan link video.'],
                    'video_link' => ['Pilih salah satu antara unggah file video atau masukkan link video.'],
                ],
            ], 422);
        }

        if ($hasFile) {
            $file = $request->file('video_file');
            $uploadDir = public_path('uploads/tutorials');

            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true, true);
            }

            $extension = $file->getClientOriginalExtension() ?: 'mp4';
            $filename = time() . '_' . Str::random(10) . '.' . $extension;

            $file->move($uploadDir, $filename);

            $videoType = 'file';
            $videoSource = '/uploads/tutorials/' . $filename;
        } else {
            $parsed = $this->parseVideoLink((string) $request->input('video_link'));
            $videoType = $parsed['video_type'];
            $videoSource = $parsed['video_source'];
        }

        $tutorial = Tutorial::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_type' => $videoType,
            'video_source' => $videoSource,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial berhasil ditambahkan.',
            'data' => $tutorial,
        ], 201);
    }

    /**
     * Remove the specified tutorial from storage.
     */
    public function destroy(Tutorial $tutorial): JsonResponse
    {
        if ($tutorial->video_type === 'file' && !empty($tutorial->video_source)) {
            $relativePath = ltrim((string) parse_url($tutorial->video_source, PHP_URL_PATH), '/');
            $physicalPath = public_path($relativePath);

            if (File::exists($physicalPath)) {
                File::delete($physicalPath);
            }
        }

        $tutorial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tutorial berhasil dihapus.',
        ]);
    }

    /**
     * Parse and normalize external video link (YouTube, Google Drive, or generic link).
     */
    private function parseVideoLink(string $url): array
    {
        $url = trim($url);

        // YouTube URL matching (watch, share, shorts, live, embed)
        if (preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?|shorts|live)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $url, $matches)) {
            return [
                'video_type' => 'youtube',
                'video_source' => 'https://www.youtube.com/embed/' . $matches[1],
            ];
        }

        // Google Drive URL matching (/file/d/ID, open?id=ID, uc?id=ID)
        if (preg_match('/drive\.google\.com\/(?:file\/d\/([a-zA-Z0-9_-]+)|(?:open|uc)\?(?:[\w=&]*[?&])?id=([a-zA-Z0-9_-]+))/i', $url, $matches)) {
            $driveId = !empty($matches[1]) ? $matches[1] : $matches[2];
            return [
                'video_type' => 'gdrive',
                'video_source' => 'https://drive.google.com/file/d/' . $driveId . '/preview',
            ];
        }

        return [
            'video_type' => 'link',
            'video_source' => $url,
        ];
    }
}
