<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Enums\TrafficSource;

class AnalyticsController extends Controller
{
    /**
     * Track video view/session start and various events during playback
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'videoId' => 'required|string',
            'sessionId' => 'required|string',
            'event' => 'required|string|in:play,watch,complete,like,share',
            'watchTime' => 'nullable|integer',
            'completionRate' => 'nullable|numeric',
            'quality' => 'nullable|string',
            'ipAddress' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $videoId = $request->input('videoId');
        $sessionId = $request->input('sessionId');
        $event = $request->input('event');

        // Check if video exists
        $video = Video::find($videoId);
        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Parse user agent for device info
        $deviceInfo = $this->parseUserAgent($request->userAgent());

        // Determine traffic source from referrer
        $referrer = $request->header('referer') ?? '';
        $source = $this->determineSource($referrer);

        // Get user info if authenticated
        $userId = null;
        if (Auth::check()) {
            $userId = Auth::id();
        }

        // Find or create analytics record
        $analytics = Analytics::firstOrCreate([
            'videoId' => $videoId,
            'sessionId' => $sessionId,
        ], [
            'id' => (string) Str::uuid(),
            'userId' => $userId,
            'ipAddress' => $request->ip(),
            'userAgent' => $request->userAgent(),
            'device' => $deviceInfo,
            'country' => null, // You might want to add GeoIP lookup here
            'city' => null,
            'region' => null,
            'referrer' => $referrer,
            'source' => $source,
            'watchTime' => 0,
            'completionRate' => 0,
            'quality' => null,
            'events' => [],
            'isComplete' => false,
            'liked' => false,
            'shared' => false,
            'startedAt' => now(),
        ]);

        // Update based on event type
        $events = $analytics->events;
        $events[] = [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'details' => [
                'watchTime' => $request->input('watchTime'),
                'completionRate' => $request->input('completionRate'),
                'quality' => $request->input('quality'),
            ]
        ];

        $updateData = [
            'events' => $events
        ];

        // Update engagement metrics based on event
        switch ($event) {
            case 'play':
                // Start tracking
                break;
            case 'watch':
                $watchTime = $request->input('watchTime', 0);
                if ($watchTime > $analytics->watchTime) {
                    $updateData['watchTime'] = $watchTime;
                }
                $completionRate = $request->input('completionRate', 0);
                if ($completionRate > $analytics->completionRate) {
                    $updateData['completionRate'] = $completionRate;
                }
                $quality = $request->input('quality');
                if ($quality) {
                    $updateData['quality'] = $quality;
                }
                break;
            case 'complete':
                $updateData['isComplete'] = true;
                $updateData['completionRate'] = 100;
                break;
            case 'like':
                $updateData['liked'] = true;
                break;
            case 'share':
                $updateData['shared'] = true;
                break;
        }

        $analytics->update($updateData);

        return response()->json(['message' => 'Event tracked successfully', 'analyticsId' => $analytics->id]);
    }

    /**
     * Get analytics for a specific video
     *
     * @param string $videoId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoAnalytics($videoId, Request $request)
    {
        $video = Video::find($videoId);
        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        // Check if user owns the video
        if (Auth::check() && $video->userId !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $filters = [
            'startDate' => $request->query('startDate'),
            'endDate' => $request->query('endDate'),
        ];

        // Build query with optional date filters
        $query = Analytics::where('videoId', $videoId);

        if ($filters['startDate']) {
            $query->where('created_at', '>=', $filters['startDate']);
        }

        if ($filters['endDate']) {
            $query->where('created_at', '<=', $filters['endDate']);
        }

        $analytics = $query->get();

        // Calculate summary statistics
        $totalViews = $analytics->count();
        $totalWatchTime = $analytics->sum('watchTime');
        $avgWatchTime = $totalViews > 0 ? $totalWatchTime / $totalViews : 0;
        $completionRate = $analytics->avg('completionRate');
        $likes = $analytics->where('liked', true)->count();
        $shares = $analytics->where('shared', true)->count();
        $completions = $analytics->where('isComplete', true)->count();
        $completionPercentage = $totalViews > 0 ? ($completions / $totalViews) * 100 : 0;

        // Group by device type
        $byDevice = $analytics->groupBy('device.type')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
                'completionRate' => $items->avg('completionRate'),
            ];
        });

        // Group by source
        $bySource = $analytics->groupBy('source')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
                'completionRate' => $items->avg('completionRate'),
            ];
        });

        // Group by country
        $byCountry = $analytics->groupBy('country')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
                'completionRate' => $items->avg('completionRate'),
            ];
        });

        // Group by quality
        $byQuality = $analytics->groupBy('quality')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
            ];
        });

        // Generate time series data
        $timeSeries = $this->groupAnalyticsByTime($analytics, $request->query('groupBy', 'day'));

        return response()->json([
            'videoId' => $videoId,
            'summary' => [
                'totalViews' => $totalViews,
                'totalWatchTime' => $totalWatchTime,
                'averageWatchTime' => round($avgWatchTime, 2),
                'completionRate' => round($completionRate, 2),
                'completionPercentage' => round($completionPercentage, 2),
                'likes' => $likes,
                'shares' => $shares,
                'completions' => $completions,
            ],
            'breakdown' => [
                'byDevice' => $byDevice,
                'bySource' => $bySource,
                'byCountry' => $byCountry,
                'byQuality' => $byQuality,
            ],
            'timeSeries' => $timeSeries,
        ]);
    }

    /**
     * Get analytics summary for all user's videos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalyticsSummary(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userId = Auth::id();

        $filters = [
            'startDate' => $request->query('startDate'),
            'endDate' => $request->query('endDate'),
        ];

        // Get all videos owned by the user
        $userVideos = Video::where('userId', $userId)->pluck('id')->toArray();

        if (empty($userVideos)) {
            return response()->json([
                'summary' => [
                    'totalViews' => 0,
                    'totalWatchTime' => 0,
                    'averageWatchTime' => 0,
                    'totalLikes' => 0,
                    'totalShares' => 0,
                    'totalCompletions' => 0,
                ],
                'topVideos' => [],
                'breakdown' => [
                    'byDevice' => [],
                    'bySource' => [],
                    'byCountry' => [],
                ],
            ]);
        }

        // Build query with optional date filters
        $query = Analytics::whereIn('videoId', $userVideos);

        if ($filters['startDate']) {
            $query->where('created_at', '>=', $filters['startDate']);
        }

        if ($filters['endDate']) {
            $query->where('created_at', '<=', $filters['endDate']);
        }

        $analytics = $query->get();

        // Calculate summary metrics
        $totalViews = $analytics->count();
        $totalWatchTime = $analytics->sum('watchTime');
        $avgWatchTime = $totalViews > 0 ? $totalWatchTime / $totalViews : 0;
        $totalLikes = $analytics->where('liked', true)->count();
        $totalShares = $analytics->where('shared', true)->count();
        $totalCompletions = $analytics->where('isComplete', true)->count();

        // Group by device type
        $byDevice = $analytics->groupBy('device.type')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
            ];
        });

        // Group by source
        $bySource = $analytics->groupBy('source')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
            ];
        });

        // Group by country
        $byCountry = $analytics->groupBy('country')->map(function ($items) {
            return [
                'count' => $items->count(),
                'watchTime' => $items->sum('watchTime'),
                'avgWatchTime' => $items->avg('watchTime'),
            ];
        });

        // Get top videos by views
        $topVideos = Analytics::whereIn('videoId', $userVideos)
            ->select('videoId')
            ->with(['video:id,title'])
            ->groupBy('videoId')
            ->selectRaw('videoId, count(*) as viewCount')
            ->orderBy('viewCount', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'videoId' => $item->videoId,
                    'title' => $item->video ? $item->video->title : 'Unknown Video',
                    'viewCount' => $item->viewCount,
                ];
            });

        return response()->json([
            'summary' => [
                'totalViews' => $totalViews,
                'totalWatchTime' => $totalWatchTime,
                'averageWatchTime' => round($avgWatchTime, 2),
                'totalLikes' => $totalLikes,
                'totalShares' => $totalShares,
                'totalCompletions' => $totalCompletions,
            ],
            'topVideos' => $topVideos,
            'breakdown' => [
                'byDevice' => $byDevice,
                'bySource' => $bySource,
                'byCountry' => $byCountry,
            ],
        ]);
    }

    /**
     * Generate a unique session ID for tracking
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSession()
    {
        $sessionId = bin2hex(random_bytes(16)); // Generate a random session ID

        return response()->json([
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * Extracts device information (type, OS, browser) from user agent string
     *
     * @param string|null $userAgent
     * @return array
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'type' => 'unknown',
                'os' => 'unknown',
                'browser' => 'unknown',
            ];
        }

        // Detect device type
        $deviceType = 'desktop';
        if (preg_match('/mobile|android|iphone|ipod|ipad|iemobile|wpdesktop|blackberry/i', $userAgent)) {
            $deviceType = (preg_match('/tablet|ipad/i', $userAgent)) ? 'tablet' : 'mobile';
        }

        // Detect OS
        $os = 'unknown';
        if (preg_match('/windows|win32|win64|wow64/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'iOS';
        }

        // Detect browser
        $browser = 'unknown';
        if (preg_match('/chrome|chromium|crios/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox|fxios/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome|chromium|crios|android/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/edg|edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'Opera';
        }

        return [
            'type' => $deviceType,
            'os' => $os,
            'browser' => $browser,
        ];
    }

    /**
     * Determines traffic source from referrer URL
     *
     * @param string|null $referrer
     * @return string
     */
    private function determineSource(?string $referrer): string
    {
        if (empty($referrer)) {
            return TrafficSource::DIRECT->value;
        }

        // Check if it's a direct internal referrer (same domain)
        $currentDomain = request()->getHost();
        if (str_contains($referrer, $currentDomain)) {
            return TrafficSource::EMBED->value;
        }

        // Check for social media sources
        $socialMedia = [
            'facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'linkedin.com',
            'pinterest.com', 'reddit.com', 'tiktok.com', 'youtube.com'
        ];
        foreach ($socialMedia as $social) {
            if (str_contains($referrer, $social)) {
                return TrafficSource::SOCIAL->value;
            }
        }

        // Check for search engines
        $searchEngines = [
            'google.com', 'bing.com', 'yahoo.com', 'duckduckgo.com', 'baidu.com', 'yandex.ru'
        ];
        foreach ($searchEngines as $engine) {
            if (str_contains($referrer, $engine)) {
                return TrafficSource::SEARCH->value;
            }
        }

        // Check for embed sources (other domains)
        return TrafficSource::OTHER->value;
    }

    /**
     * Groups analytics data by time periods (hour, day, month)
     *
     * @param \Illuminate\Support\Collection $analytics
     * @param string $groupBy
     * @return array
     */
    private function groupAnalyticsByTime($analytics, string $groupBy = 'day'): array
    {
        $groups = [];

        foreach ($analytics as $item) {
            $date = $item->created_at;

            switch ($groupBy) {
                case 'hour':
                    $period = $date->format('Y-m-d H:00:00');
                    break;
                case 'month':
                    $period = $date->format('Y-m');
                    break;
                case 'day':
                default:
                    $period = $date->format('Y-m-d');
                    break;
            }

            if (!isset($groups[$period])) {
                $groups[$period] = [
                    'period' => $period,
                    'views' => 0,
                    'watchTime' => 0,
                    'completions' => 0,
                ];
            }

            $groups[$period]['views']++;
            $groups[$period]['watchTime'] += $item->watchTime;
            if ($item->isComplete) {
                $groups[$period]['completions']++;
            }
        }

        // Sort by period
        ksort($groups);

        return array_values($groups);
    }
}
