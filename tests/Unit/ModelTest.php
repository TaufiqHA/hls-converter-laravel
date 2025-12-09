<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Video;
use App\Models\Setting;
use App\Models\Analytics;
use App\Enums\UserRole;
use App\Enums\VideoStatus;
use Tests\TestCase;

class ModelTest extends TestCase
{
    public function test_user_model_creation()
    {
        $user = new User([
            'id' => 'test-uuid',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::USER,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals(UserRole::USER, $user->role);
    }

    public function test_video_model_creation()
    {
        $video = new Video([
            'id' => 'test-video-uuid',
            'title' => 'Test Video',
            'status' => VideoStatus::UPLOADING,
            'processingProgress' => 0,
            'tags' => ['test', 'video'],
            'resolution' => ['width' => 1920, 'height' => 1080],
        ]);

        $this->assertInstanceOf(Video::class, $video);
        $this->assertEquals('Test Video', $video->title);
        $this->assertEquals(VideoStatus::UPLOADING, $video->status);
        $this->assertEquals(['test', 'video'], $video->tags);
        $this->assertEquals(['width' => 1920, 'height' => 1080], $video->resolution);
    }

    public function test_setting_model_creation()
    {
        $settings = new Setting([
            'id' => 'test-setting-uuid',
            'userId' => 'test-user-uuid',
            'playerSettings' => ['autoplay' => true, 'volume' => 80],
            'defaultDownloadEnabled' => true,
        ]);

        $this->assertInstanceOf(Setting::class, $settings);
        $this->assertEquals(['autoplay' => true, 'volume' => 80], $settings->playerSettings);
        $this->assertTrue($settings->defaultDownloadEnabled);
    }

    public function test_analytics_model_creation()
    {
        $analytics = new Analytics([
            'id' => 'test-analytics-uuid',
            'videoId' => 'test-video-uuid',
            'sessionId' => 'session-123',
            'source' => 'direct',
            'device' => ['type' => 'mobile', 'browser' => 'Chrome'],
        ]);

        $this->assertInstanceOf(Analytics::class, $analytics);
        $this->assertEquals('session-123', $analytics->sessionId);
        $this->assertEquals(['type' => 'mobile', 'browser' => 'Chrome'], $analytics->device);
    }
}