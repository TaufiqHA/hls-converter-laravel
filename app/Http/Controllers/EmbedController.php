<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

class EmbedController extends Controller
{
    /**
     * Display the embedded video player for a specific video.
     *
     * GET `/embed/:id`
     *
     * Response (Success - 200):
     * Renders embed view with video player
     */
    public function showEmbed(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|string|exists:videos,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        $video = Video::find($id);

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Check if embedding is enabled
        if (!$video->embedEnabled) {
            return response()->json(['error' => 'Embedding is disabled for this video'], 403);
        }

        // Check if video privacy allows embedding
        if ($video->privacy === 'private') {
            return response()->json(['error' => 'Cannot embed private video'], 403);
        }

        // Check allowed domains if domain restriction is enabled
        $referer = $request->headers->get('referer');
        if (!empty($video->allowedDomains) && count($video->allowedDomains) > 0 && !empty($referer)) {
            $refererDomain = parse_url($referer, PHP_URL_HOST);
            if ($refererDomain && !in_array($refererDomain, $video->allowedDomains)) {
                return response()->json(['error' => 'Embedding not allowed from this domain'], 403);
            }
        }

        // Increment view count for embed views
        $video->increment('views');

        // Additional analytics tracking for embed views could be added here
        // For example, tracking the source as 'embed'

        // Get player settings for the specific user
        $userSetting = Setting::where('userId', $video->userId)->first();
        $settingArray = $userSetting ? $userSetting->toArray() : [];

        // Extract specific settings
        $playerSettings = $settingArray['playerSettings'] ?? [];
        $adsSettings = $settingArray['adsSettings'] ?? [];
        $subtitleSettings = $settingArray['subtitleSettings'] ?? [];

        // Convert video to array and update HLS URL to use streaming endpoint
        $videoArray = $video->toArray();

        // Update HLS playlist URL to use streaming endpoint if needed
        if (empty($videoArray['hlsPlaylistUrl'])) {
            // Generate streaming URL for this specific video
            $videoArray['hlsPlaylistUrl'] = url("/api/stream/{$video->userId}/{$video->id}/playlist.m3u8");
        } elseif (!str_contains($videoArray['hlsPlaylistUrl'], '/api/stream/')) {
            // If it's not already using the streaming endpoint, replace it
            $videoArray['hlsPlaylistUrl'] = url("/api/stream/{$video->userId}/{$video->id}/playlist.m3u8");
        }

        // Also ensure the hlsPlaylistUrl in the video model is updated for consistency
        $video->hlsPlaylistUrl = $videoArray['hlsPlaylistUrl'];

        // Return the embedded player view
        if (view()->exists('embed.player')) {
            return view('embed.player', [
                'video' => $videoArray,
                'playerSettings' => $playerSettings,
                'adsSettings' => $adsSettings,
                'subtitleSettings' => $subtitleSettings
            ]);
        } else {
            // Fallback response if view doesn't exist
            return response()->json(['error' => 'Embed player view not found'], 500);
        }
    }
}