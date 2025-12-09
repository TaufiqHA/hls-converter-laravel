<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Video;
use App\Models\User;
use Illuminate\Support\Str;

class videoController extends Controller
{
    /**
     * Processes direct video uploads, creates video record, updates user storage, and adds to processing queue.
     *
     * POST `/api/videos/upload`
     * Headers: Authorization: Bearer {jwt-token}, Content-Type: multipart/form-data
     *
     * Request (Multipart Form Data):
     * - video: [video file]
     * - title: "My Video"
     * - description: "A sample video"
     * - privacy: "public"
     * - tags: ["tag1", "tag2"]
     *
     * Response (Success - 200):
     * {
     *   "message": "Video uploaded successfully",
     *   "video": {
     *     "id": "video-uuid",
     *     "title": "My Video",
     *     "status": "uploading",
     *     "originalFileName": "video.mp4",
     *     "originalFileSize": 104857600
     *   }
     * }
     */
    public function uploadVideo(Request $request)
    {
        $user = $request->user();

        // Check storage limit
        if ($user->storageUsed >= $user->storageLimit) {
            return response()->json(['error' => 'Storage limit exceeded'], 400);
        }

        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm|max:102400', // 100MB max
            'title' => 'required|string|max:200',
            'description' => 'sometimes|string',
            'privacy' => 'sometimes|in:public,private,unlisted,password',
            'tags' => 'sometimes|array',
            'tags.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $file = $request->file('video');
        $originalFileName = $file->getClientOriginalName();
        $originalFileSize = $file->getSize();

        // Check if user has enough storage remaining
        if ($user->storageUsed + $originalFileSize > $user->storageLimit) {
            return response()->json(['error' => 'Not enough storage space'], 400);
        }

        // Store the file
        $filePath = $file->store('uploads/originals', 'public');

        // Create video record
        $tags = $request->has('tags') ? $request->tags : [];
        // Ensure tags is always an array, even if empty or if it's a string representation of an array
        if (is_string($tags)) {
            // If it's a string representation of an array, try to decode it
            if ($tags === '[]' || $tags === '') {
                $tags = `{}`; // PostgreSQL empty array format
            } else {
                // Attempt to decode JSON string if it looks like one
                $decoded = json_decode($tags, true);
                $tags = is_array($decoded) ? $decoded : `{}`;
            }
        } else {
            $tags = is_array($tags) ? (count($tags) === 0 ? `{}` : $tags) : `{}`;
        }

        $video = Video::create([
            'id' => Str::uuid(),
            'userId' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'originalFileName' => $originalFileName,
            'originalFilePath' => $filePath,
            'originalFileSize' => $originalFileSize,
            'status' => 'uploading',
            'uploadType' => 'direct',
            'privacy' => $request->privacy ?? 'public',
            'tags' => $tags,
            'storageType' => 'local'
        ]);

        // Update user's storage used
        $user->increment('storageUsed', $originalFileSize);

        // Update video status and add to processing queue
        $statusService = new \App\Services\VideoStatusService();
        $statusService->handleSuccessfulUpload($video->id);

        return response()->json([
            'message' => 'Video uploaded successfully',
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'status' => $video->status,
                'originalFileName' => $video->originalFileName,
                'originalFileSize' => $video->originalFileSize
            ],
            'success' => true
        ]);
    }

    /**
     * Handles remote URL uploads with validation and Google Drive support, adds to queue for background processing.
     *
     * POST `/api/videos/remote-upload`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "url": "https://example.com/video.mp4",
     *   "title": "Remote Video",
     *   "description": "A video from remote URL",
     *   "privacy": "public"
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Remote video upload started",
     *   "video": {
     *     "id": "video-uuid",
     *     "title": "Remote Video",
     *     "status": "queued",
     *     "remoteUrl": "https://example.com/video.mp4"
     *   }
     * }
     */
    public function remoteUpload(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'title' => 'required|string|max:200',
            'description' => 'sometimes|string',
            'privacy' => 'sometimes|in:public,private,unlisted,password'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $tags = $request->has('tags') ? $request->tags : [];
        // Ensure tags is always an array, even if empty or if it's a string representation of an array
        if (is_string($tags)) {
            // If it's a string representation of an array, try to decode it
            if ($tags === '[]' || $tags === '') {
                $tags = `{}`; // PostgreSQL empty array format
            } else {
                // Attempt to decode JSON string if it looks like one
                $decoded = json_decode($tags, true);
                $tags = is_array($decoded) ? $decoded : `{}`;
            }
        } else {
            $tags = is_array($tags) ? (count($tags) === 0 ? `{}` : $tags) : `{}`;
        }

        $video = Video::create([
            'id' => Str::uuid(),
            'userId' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'originalFileName' => basename(parse_url($request->url, PHP_URL_PATH)) ?: 'remote_video',
            'remoteUrl' => $request->url,
            'status' => 'queued',
            'uploadType' => str_contains($request->url, 'drive.google.com') ? 'googledrive' : 'remote',
            'privacy' => $request->privacy ?? 'public',
            'tags' => $tags,
            'storageType' => 'local'
        ]);

        // Update video status and add to processing queue
        $statusService = new \App\Services\VideoStatusService();
        $statusService->handleSuccessfulUpload($video->id);

        return response()->json([
            'message' => 'Remote video upload started',
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'status' => $video->status,
                'remoteUrl' => $video->remoteUrl
            ]
        ]);
    }

    /**
     * Retrieves user's videos with pagination and filtering options.
     *
     * GET `/api/videos`
     * Headers: Authorization: Bearer {jwt-token}
     * Query Parameters:
     * - page (optional): Page number (default: 1)
     * - limit (optional): Items per page (default: 10)
     * - status (optional): Filter by status
     * - privacy (optional): Filter by privacy setting
     *
     * Response (Success - 200):
     * {
     *   "videos": [
     *     {
     *       "id": "video-uuid",
     *       "title": "My Video",
     *       "description": "A sample video",
     *       "originalFileName": "video.mp4",
     *       "originalFileSize": 104857600,
     *       "status": "completed",
     *       "processingProgress": 100,
     *       "privacy": "public",
     *       "views": 150,
     *       "likes": 10,
     *       "dislikes": 0,
     *       "duration": 120.5,
     *       "resolution": {"width": 1920, "height": 1080},
     *       "hlsPlaylistUrl": "http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8",
     *       "thumbnailPath": "/uploads/thumbnails/video-uuid.jpg",
     *       "createdAt": "2023-01-01T00:00:00.000Z"
     *     }
     *   ],
     *   "pagination": {
     *     "page": 1,
     *     "limit": 10,
     *     "total": 15,
     *     "totalPages": 2
     *   }
     * }
     */
    public function getVideos(Request $request)
    {
        $user = $request->user();

        $query = Video::where('userId', $user->id);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('privacy')) {
            $query->where('privacy', $request->privacy);
        }

        // Pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $videos = $query->paginate($limit, ['*'], 'page', $page);

        // Generate embed URLs for each video
        $appUrl = config('app.url');
        if (empty($appUrl) || $appUrl === 'http://localhost') {
            // Fallback to the request's origin if APP_URL is not properly set
            $appUrl = $request->getSchemeAndHttpHost();
        }

        return response()->json([
            'data' => [
                'videos' => $videos->getCollection()->map(function ($video) use ($appUrl, $request) {
                    // Generate proper URLs based on storage type for each video
                    $hlsPlaylistUrl = $video->hlsPlaylistUrl;
                    if ($video->storageType === 's3' && $video->s3PublicUrl) {
                        $hlsPlaylistUrl = $video->s3PublicUrl;
                    } elseif ($video->storageType === 'local' && $video->hlsPlaylistUrl && !str_contains($video->hlsPlaylistUrl, '/api/stream/')) {
                        // For local storage, if the hlsPlaylistUrl is a file path, convert it to streaming endpoint
                        $hlsPlaylistUrl = url("/api/stream/{$request->user()->id}/{$video->id}/playlist.m3u8");
                    } elseif ($video->storageType === 'local' && empty($video->hlsPlaylistUrl) && $video->status === 'completed') {
                        // If no hlsPlaylistUrl is set but video is completed, generate the streaming URL
                        $hlsPlaylistUrl = url("/api/stream/{$request->user()->id}/{$video->id}/playlist.m3u8");
                    }

                    return [
                        'id' => $video->id,
                        'title' => $video->title,
                        'description' => $video->description,
                        'originalFileName' => $video->originalFileName,
                        'originalFileSize' => $video->originalFileSize,
                        'status' => $video->status,
                        'processingProgress' => $video->processingProgress,
                        'privacy' => $video->privacy,
                        'views' => $video->views,
                        'likes' => $video->likes,
                        'dislikes' => $video->dislikes,
                        'duration' => $video->duration,
                        'resolution' => $video->resolution,
                        'hlsPlaylistUrl' => $hlsPlaylistUrl,
                        'thumbnailPath' => $video->thumbnailPath,
                        'embedUrl' => $appUrl . '/embed/' . $video->id,
                        'createdAt' => $video->created_at
                    ];
                }),
                'pagination' => [
                    'page' => $videos->currentPage(),
                    'limit' => $videos->perPage(),
                    'total' => $videos->total(),
                    'totalPages' => $videos->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Gets detailed information about a specific video with proper URL generation based on storage type (local/S3).
     *
     * GET `/api/videos/:id`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "video": {
     *     "id": "video-uuid",
     *     "title": "My Video",
     *     "description": "A sample video",
     *     "originalFileName": "video.mp4",
     *     "originalFileSize": 104857600,
     *     "hlsPath": "/uploads/hls/video-uuid",
     *     "hlsPlaylistUrl": "http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8",
     *     "qualityVariants": [
     *       {
     *         "resolution": "1080p",
     *         "url": "http://localhost:3000/api/stream/user-uuid/video-uuid/1080p.m3u8",
     *         "enabled": true
     *       }
     *     ],
     *     "thumbnailPath": "/uploads/thumbnails/video-uuid.jpg",
     *     "duration": 120.5,
     *     "resolution": {"width": 1920, "height": 1080},
     *     "fps": 30,
     *     "codec": {"video": "h264", "audio": "aac"},
     *     "status": "completed",
     *     "processingPhase": "completed",
     *     "processingProgress": 100,
     *     "downloadEnabled": true,
     *     "embedEnabled": true,
     *     "views": 150,
     *     "likes": 10,
     *     "dislikes": 0,
     *     "privacy": "public",
     *     "tags": ["tag1", "tag2"],
     *     "allowedDomains": ["example.com"],
     *     "watermark": {
     *       "enabled": true,
     *       "position": "bottom-right",
     *       "imagePath": "/uploads/watermarks/watermark.png",
     *       "opacity": 0.5
     *     },
     *     "subtitles": [
     *       {
     *         "id": "subtitle-uuid",
     *         "language": "en",
     *         "label": "English",
     *         "filePath": "/uploads/subtitles/subtitle.vtt"
     *       }
     *     ],
     *     "createdAt": "2023-01-01T00:00:00.000Z",
     *     "updatedAt": "2023-01-01T00:00:00.000Z"
     *   }
     * }
     */
    public function getVideo(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();
        // dd($video);

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Generate proper URLs based on storage type
        $hlsPlaylistUrl = $video->hlsPlaylistUrl;
        if ($video->storageType === 's3' && $video->s3PublicUrl) {
            $hlsPlaylistUrl = $video->s3PublicUrl;
        } elseif ($video->storageType === 'local' && $video->hlsPlaylistUrl && !str_contains($video->hlsPlaylistUrl, '/api/stream/')) {
            // For local storage, if the hlsPlaylistUrl is a file path, convert it to streaming endpoint
            $hlsPlaylistUrl = url("/api/stream/{$user->id}/{$video->id}/playlist.m3u8");
        } elseif ($video->storageType === 'local' && empty($video->hlsPlaylistUrl) && $video->status === 'completed') {
            // If no hlsPlaylistUrl is set but video is completed, generate the streaming URL
            $hlsPlaylistUrl = url("/api/stream/{$user->id}/{$video->id}/playlist.m3u8");
        }

        // Generate embed URL
        $appUrl = config('app.url');
        if (empty($appUrl) || $appUrl === 'http://localhost') {
            // Fallback to the request's origin if APP_URL is not properly set
            $appUrl = $request->getSchemeAndHttpHost();
        }
        $embedUrl = $appUrl . '/embed/' . $video->id;

        return response()->json([
            'data' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'originalFileName' => $video->originalFileName,
                'originalFileSize' => $video->originalFileSize,
                'hlsPath' => $video->hlsPath,
                'hlsPlaylistUrl' => $hlsPlaylistUrl,
                'qualityVariants' => $video->qualityVariants,
                'thumbnailPath' => $video->thumbnailPath,
                'duration' => $video->duration,
                'resolution' => $video->resolution,
                'fps' => $video->fps,
                'codec' => $video->codec,
                'status' => $video->status,
                'processingPhase' => $video->processingPhase,
                'processingProgress' => $video->processingProgress,
                'downloadEnabled' => $video->downloadEnabled,
                'embedEnabled' => $video->embedEnabled,
                'embedUrl' => $embedUrl,
                'views' => $video->views,
                'likes' => $video->likes,
                'dislikes' => $video->dislikes,
                'privacy' => $video->privacy,
                'tags' => $video->tags,
                'allowedDomains' => $video->allowedDomains,
                'watermark' => $video->watermark,
                'subtitles' => $video->subtitles,
                'createdAt' => $video->created_at,
                'updatedAt' => $video->updated_at
            ]
        ]);
    }

    /**
     * Updates video metadata and settings.
     *
     * PUT `/api/videos/:id`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "title": "Updated Video Title",
     *   "description": "Updated description",
     *   "privacy": "private",
     *   "tags": ["new-tag1", "new-tag2"],
     *   "allowedDomains": ["newdomain.com"]
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Video updated successfully",
     *   "video": {
     *     "id": "video-uuid",
     *     "title": "Updated Video Title",
     *     "description": "Updated description",
     *     "privacy": "private",
     *     "updatedAt": "2023-01-01T00:00:00.000Z"
     *   }
     * }
     */
    public function updateVideo(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'privacy' => 'sometimes|in:public,private,unlisted,password',
            'tags' => 'sometimes|array',
            'tags.*' => 'string',
            'allowedDomains' => 'sometimes|array',
            'allowedDomains.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = $request->only(['title', 'description', 'privacy']);

        if ($request->has('tags')) {
            $tags = $request->tags;
            if (is_string($tags)) {
                // If it's a string representation of an array, try to decode it
                if ($tags === '[]' || $tags === '') {
                    $tags = `{}`; // PostgreSQL empty array format
                } else {
                    // Attempt to decode JSON string if it looks like one
                    $decoded = json_decode($tags, true);
                    $tags = is_array($decoded) ? $decoded : `{}`;
                }
            } else {
                $tags = is_array($tags) ? (count($tags) === 0 ? `{}` : $tags) : `{}`;
            }
            $data['tags'] = $tags;
        }

        if ($request->has('allowedDomains')) {
            $allowedDomains = $request->allowedDomains;
            if (is_string($allowedDomains)) {
                // If it's a string representation of an array, try to decode it
                if ($allowedDomains === '[]' || $allowedDomains === '') {
                    $allowedDomains = `{}`; // PostgreSQL empty array format
                } else {
                    // Attempt to decode JSON string if it looks like one
                    $decoded = json_decode($allowedDomains, true);
                    $allowedDomains = is_array($decoded) ? $decoded : `{}`;
                }
            } else {
                $allowedDomains = is_array($allowedDomains) ? (count($allowedDomains) === 0 ? `{}` : $allowedDomains) : `{}`;
            }
            $data['allowedDomains'] = $allowedDomains;
        }

        $video->update($data);

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'privacy' => $video->privacy,
                'updatedAt' => $video->updated_at
            ]
        ]);
    }

    /**
     * Toggles download enable/disable for a video.
     *
     * PUT `/api/videos/:id/toggle-download`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Download setting updated",
     *   "downloadEnabled": true
     * }
     */
    public function toggleDownload(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $video->update([
            'downloadEnabled' => !$video->downloadEnabled
        ]);

        return response()->json([
            'message' => 'Download setting updated',
            'downloadEnabled' => $video->downloadEnabled
        ]);
    }

    /**
     * Toggles embed enable/disable for a video.
     *
     * PUT `/api/videos/:id/toggle-embed`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Embed setting updated",
     *   "embedEnabled": true
     * }
     */
    public function toggleEmbed(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $video->update([
            'embedEnabled' => !$video->embedEnabled
        ]);

        return response()->json([
            'message' => 'Embed setting updated',
            'embedEnabled' => $video->embedEnabled
        ]);
    }

    /**
     * Updates allowed domains for video embedding.
     *
     * PUT `/api/videos/:id/allowed-domains`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "allowedDomains": ["example.com", "mywebsite.com"]
     * }
     *
     * Response (Success - 200):
     * {
     *   "message": "Allowed domains updated",
     *   "allowedDomains": ["example.com", "mywebsite.com"]
     * }
     */
    public function updateAllowedDomains(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'allowedDomains' => 'required|array',
            'allowedDomains.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $allowedDomains = $request->allowedDomains;
        if (is_string($allowedDomains)) {
            // If it's a string representation of an array, try to decode it
            if ($allowedDomains === '[]' || $allowedDomains === '') {
                $allowedDomains = `{}`; // PostgreSQL empty array format
            } else {
                // Attempt to decode JSON string if it looks like one
                $decoded = json_decode($allowedDomains, true);
                $allowedDomains = is_array($decoded) ? $decoded : `{}`;
            }
        } else {
            $allowedDomains = is_array($allowedDomains) ? (count($allowedDomains) === 0 ? `{}` : $allowedDomains) : `{}`;
        }

        $video->update([
            'allowedDomains' => $allowedDomains
        ]);

        return response()->json([
            'message' => 'Allowed domains updated',
            'allowedDomains' => $video->allowedDomains
        ]);
    }

    /**
     * Deletes video with proper cleanup of files (local/S3) and updates user storage.
     *
     * DELETE `/api/videos/:id`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "message": "Video deleted successfully"
     * }
     */
    public function deleteVideo(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Update user's storage - subtract the video file size
        $user->decrement('storageUsed', $video->originalFileSize);

        // Delete files based on storage type
        if ($video->storageType === 'local') {
            if ($video->originalFilePath) {
                Storage::disk('local')->delete($video->originalFilePath);
            }
            if ($video->hlsPath) {
                Storage::deleteDirectory($video->hlsPath);
            }
            if ($video->thumbnailPath) {
                Storage::disk('local')->delete($video->thumbnailPath);
            }
        }
        // For S3 storage, you would delete from S3 here
        // else if ($video->storageType === 's3' && $video->s3Key) {
        //     Storage::disk('s3')->delete($video->s3Key);
        // }

        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully'
        ]);
    }

    /**
     * Retrieves video processing status and queue position.
     *
     * GET `/api/videos/:id/status`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "status": "completed",
     *   "processingPhase": "completed",
     *   "processingProgress": 100,
     *   "downloadProgress": 100,
     *   "convertProgress": 100,
     *   "queuePosition": 0,
     *   "processingStartedAt": "2023-01-01T00:00:00.000Z",
     *   "processingCompletedAt": "2023-01-01T00:00:00.000Z",
     *   "estimatedCompletion": null
     * }
     */
    public function getVideoStatus(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        return response()->json([
            'status' => $video->status,
            'processingPhase' => $video->processingPhase,
            'processingProgress' => $video->processingProgress,
            'downloadProgress' => $video->downloadProgress,
            'convertProgress' => $video->convertProgress,
            'queuePosition' => 0, // This would typically come from a queue system
            'processingStartedAt' => $video->processingStartedAt,
            'processingCompletedAt' => $video->processingCompletedAt,
            'estimatedCompletion' => null
        ]);
    }

    /**
     * Handles video file downloads with proper file serving based on storage type.
     *
     * GET `/api/videos/:id/download`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * File download (video file)
     */
    public function downloadVideo(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        if (!$video->downloadEnabled) {
            return response()->json(['error' => 'Download not allowed'], 403);
        }

        // Return file based on storage type
        if ($video->storageType === 'local' && $video->originalFilePath) {
            $path = storage_path('app/' . $video->originalFilePath);
            return response()->download($path, $video->originalFileName);
        }
        // For S3 storage, you would return a redirect or stream from S3
        // else if ($video->storageType === 's3' && $video->s3Key) {
        //     $url = Storage::disk('s3')->temporaryUrl($video->s3Key, now()->addMinutes(5));
        //     return redirect($url);
        // }

        return response()->json(['error' => 'File not found'], 404);
    }

    /**
     * Increments video view count.
     *
     * POST `/api/videos/:id/view`
     *
     * Response (Success - 200):
     * {
     *   "message": "View recorded successfully"
     * }
     */
    public function incrementViews(Request $request, $id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $video->increment('views');

        // In a real implementation, you'd track unique views and other analytics

        return response()->json([
            'message' => 'View recorded successfully'
        ]);
    }

    /**
     * Gets queue statistics (admin only).
     *
     * GET `/api/videos/queue/stats`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "queueSize": 10,
     *   "processingCount": 2,
     *   "waitingCount": 8,
     *   "averageWaitTime": 300
     * }
     */
    public function getQueueStats(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        // This would typically come from a queue system like Redis
        // For now, we'll simulate the data
        $queueSize = Video::whereIn('status', ['queued', 'processing'])->count();
        $processingCount = Video::where('status', 'processing')->count();
        $waitingCount = $queueSize - $processingCount;
        $averageWaitTime = rand(100, 600); // Simulated average wait time in seconds

        return response()->json([
            'queueSize' => $queueSize,
            'processingCount' => $processingCount,
            'waitingCount' => $waitingCount,
            'averageWaitTime' => $averageWaitTime
        ]);
    }

    /**
     * Start chunked upload session
     *
     * POST `/api/videos/chunk/start`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Request:
     * {
     *   "filename": "video.mp4"
     * }
     *
     * Response (Success - 200):
     * {
     *   "sessionId": "session-uuid",
     *   "uploadPath": "/uploads/chunks/session-uuid",
     *   "message": "Chunk upload session started"
     * }
     */
    public function startChunkUpload(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Create a session ID for this chunked upload
        $sessionId = Str::uuid();

        // Create directory for chunks
        $uploadPath = "uploads/chunks/{$sessionId}";
        Storage::makeDirectory($uploadPath);

        return response()->json([
            'sessionId' => $sessionId,
            'uploadPath' => "/{$uploadPath}",
            'message' => 'Chunk upload session started'
        ]);
    }

    /**
     * Upload chunk to existing session
     *
     * POST `/api/videos/chunk/upload`
     * Headers: Authorization: Bearer {jwt-token}, Content-Type: multipart/form-data
     *
     * Request (Multipart Form Data):
     * - sessionId: session-uuid
     * - chunkIndex: 0
     * - chunk: [file chunk]
     *
     * Response (Success - 200):
     * {
     *   "message": "Chunk uploaded successfully",
     *   "chunkIndex": 0,
     *   "sessionId": "session-uuid"
     * }
     */
    public function uploadChunk(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'sessionId' => 'required|string',
            'chunkIndex' => 'required|integer|min:0',
            'chunk' => 'required|file|mimes:mp4,mov,avi,mkv,webm'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $sessionId = $request->sessionId;
        $chunkIndex = $request->chunkIndex;
        $chunkFile = $request->file('chunk');

        // Validate session exists (in a real implementation you'd verify in DB)
        $uploadPath = "uploads/chunks/{$sessionId}";
        if (!Storage::exists($uploadPath)) {
            return response()->json(['error' => 'Invalid session'], 400);
        }

        // Store the chunk
        $chunkFileName = "chunk_{$chunkIndex}";
        $chunkPath = $uploadPath . '/' . $chunkFileName;
        $chunkFile->storeAs($uploadPath, $chunkFileName, 'local');

        return response()->json([
            'message' => 'Chunk uploaded successfully',
            'chunkIndex' => $chunkIndex,
            'sessionId' => $sessionId
        ]);
    }

    /**
     * Gets the embed URL for a specific video.
     *
     * GET `/api/videos/:id/embed-url`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "embedUrl": "http://localhost:8000/embed/video-id-uuid"
     * }
     */
    public function getEmbedUrl(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        if (!$video->embedEnabled) {
            return response()->json(['error' => 'Embed is not enabled for this video'], 403);
        }

        $appUrl = config('app.url');
        if (empty($appUrl) || $appUrl === 'http://localhost') {
            // Fallback to the request's origin if APP_URL is not properly set
            $appUrl = $request->getSchemeAndHttpHost();
        }

        $embedUrl = $appUrl . '/embed/' . $video->id;

        return response()->json([
            'embedUrl' => $embedUrl
        ]);
    }

    /**
     * Gets the embed code for a specific video.
     *
     * GET `/api/videos/:id/embed-code`
     * Headers: Authorization: Bearer {jwt-token}
     *
     * Response (Success - 200):
     * {
     *   "embedCode": "<iframe src='http://localhost:8000/embed/video-id-uuid' width='640' height='360' frameborder='0' allowfullscreen></iframe>"
     * }
     */
    public function getEmbedCode(Request $request, $id)
    {
        $user = $request->user();

        $video = Video::where('id', $id)->where('userId', $user->id)->first();

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        if (!$video->embedEnabled) {
            return response()->json(['error' => 'Embed is not enabled for this video'], 403);
        }

        $appUrl = config('app.url');
        if (empty($appUrl) || $appUrl === 'http://localhost') {
            // Fallback to the request's origin if APP_URL is not properly set
            $appUrl = $request->getSchemeAndHttpHost();
        }

        $embedUrl = $appUrl . '/embed/' . $video->id;

        $embedCode = "<iframe src='{$embedUrl}' width='640' height='360' frameborder='0' allowfullscreen allow='autoplay; encrypted-media'></iframe>";

        // Update the embedCode in the video record
        $video->embedCode = $embedCode;
        $video->save();

        return response()->json([
            'embedCode' => $embedCode
        ]);
    }
}
