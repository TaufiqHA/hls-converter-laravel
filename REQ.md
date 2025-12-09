# PostgreSQL Database Schema Documentation

## Overview
This document describes the PostgreSQL database schema for the HLS Video Converter API built with Node.js and Sequelize ORM.

## Tables

### 1. users
**Description:** Stores user account information and authentication data.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| id | UUID | PRIMARY KEY, NOT NULL | UUIDV4 | Unique identifier for each user |
| username | VARCHAR(50) | UNIQUE, NOT NULL | - | Unique username for login |
| email | VARCHAR(255) | UNIQUE, NOT NULL | - | User's email address |
| password | VARCHAR(255) | NOT NULL | - | Hashed password |
| role | ENUM('user', 'admin') | - | 'user' | User role for permissions |
| storageUsed | BIGINT | - | 0 | Amount of storage used in bytes |
| storageLimit | BIGINT | - | 5368709120 | Maximum storage allowed (5GB) |
| isActive | BOOLEAN | - | true | Account active status |
| apiKey | VARCHAR(64) | UNIQUE, NULL | - | API key for authentication |
| lastLoginAt | DATE | - | NULL | Timestamp of last login |
| adsDisabled | BOOLEAN | - | false | When true, ads will not be shown on videos uploaded by this user |
| createdAt | TIMESTAMP | NOT NULL | NOW() | Record creation timestamp |
| updatedAt | TIMESTAMP | NOT NULL | NOW() | Record update timestamp |

**Indexes:**
- Primary Key: `id`
- Unique: `username`, `email`, `apiKey`

### 2. videos
**Description:** Stores video file information, processing status, and metadata.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| id | UUID | PRIMARY KEY, NOT NULL | UUIDV4 | Unique identifier for each video |
| userId | UUID | REFERENCES users(id), ON DELETE CASCADE | NULL | Foreign key to user who uploaded the video |
| isGuestUpload | BOOLEAN | - | false | Indicates if video was uploaded by a guest |
| title | VARCHAR(200) | NOT NULL | - | Video title |
| description | TEXT | - | NULL | Video description |
| tags | ARRAY(VARCHAR) | - | [] | Array of tags for the video |
| originalFileName | VARCHAR(255) | NOT NULL | - | Original name of the uploaded file |
| originalFilePath | TEXT | NOT NULL | - | Path to the original uploaded file |
| originalFileSize | BIGINT | NOT NULL | - | Size of the original file in bytes |
| hlsPath | TEXT | - | NULL | Path to HLS output directory |
| hlsPlaylistUrl | TEXT | - | NULL | URL to the HLS playlist file |
| qualityVariants | JSONB | - | [] | JSON array with available quality variants |
| thumbnailPath | TEXT | - | NULL | Path to the thumbnail image |
| duration | FLOAT | - | 0 | Video duration in seconds |
| resolution | JSONB | - | {"width": 0, "height": 0} | Video resolution as JSON |
| fps | INTEGER | - | 30 | Frames per second |
| codec | JSONB | - | {"video": null, "audio": null} | Video and audio codec information |
| status | ENUM('uploading', 'queued', 'processing', 'completed', 'failed') | - | 'uploading' | Current processing status |
| processingPhase | ENUM('pending', 'downloading', 'converting', 'completed', 'failed') | - | 'pending' | Current processing phase |
| processingProgress | INTEGER | CHECK(0-100) | 0 | Processing progress percentage |
| downloadProgress | INTEGER | CHECK(0-100) | 0 | Download progress percentage |
| convertProgress | INTEGER | CHECK(0-100) | 0 | Conversion progress percentage |
| processingStartedAt | DATE | - | NULL | Timestamp when processing started |
| processingCompletedAt | DATE | - | NULL | Timestamp when processing completed |
| watermark | JSONB | - | See model | Watermark configuration as JSON |
| subtitles | JSONB | - | [] | Array of subtitle configurations |
| privacy | ENUM('public', 'private', 'unlisted', 'password') | - | 'public' | Video privacy setting |
| password | VARCHAR(255) | - | NULL | Hashed password for password-protected videos |
| allowedDomains | ARRAY(VARCHAR) | - | [] | Array of domains allowed to embed this video |
| downloadEnabled | BOOLEAN | - | true | Whether video download is enabled |
| embedEnabled | BOOLEAN | - | true | Whether video embedding is enabled |
| embedCode | TEXT | - | NULL | HTML embed code for the video player |
| views | INTEGER | - | 0 | Total number of views |
| uniqueViews | INTEGER | - | 0 | Number of unique viewers |
| totalWatchTime | BIGINT | - | 0 | Total watch time in seconds |
| averageWatchTime | FLOAT | - | 0 | Average watch time in seconds |
| likes | INTEGER | - | 0 | Number of likes |
| dislikes | INTEGER | - | 0 | Number of dislikes |
| uploadType | ENUM('direct', 'remote', 'chunked', 'googledrive') | NOT NULL | - | Type of upload |
| remoteUrl | TEXT | - | NULL | URL for remote file uploads |
| isChunkedUpload | BOOLEAN | - | false | Whether the upload was chunked |
| uploadSessionId | VARCHAR(64) | - | NULL | Session ID for chunked uploads |
| storageType | ENUM('local', 's3', 'minio') | - | 'local' | Storage type for the video |
| s3Key | TEXT | - | NULL | S3 key if stored on S3 |
| s3Bucket | VARCHAR(255) | - | NULL | S3 bucket name if stored on S3 |
| s3PublicUrl | TEXT | - | NULL | Stored S3/R2 public base URL |
| errorMessage | TEXT | - | NULL | Error message if processing failed |
| retryCount | INTEGER | - | 0 | Number of retry attempts |
| isModerated | BOOLEAN | - | false | Whether the video has been moderated |
| moderationStatus | ENUM('pending', 'approved', 'rejected') | - | 'approved' | Moderation status |
| moderationNotes | TEXT | - | NULL | Notes from the moderation process |
| publishedAt | DATE | - | NULL | Timestamp when video was published |
| createdAt | TIMESTAMP | NOT NULL | NOW() | Record creation timestamp |
| updatedAt | TIMESTAMP | NOT NULL | NOW() | Record update timestamp |

**Indexes:**
- Primary Key: `id`
- Composite: `userId`, `createdAt`
- Single: `status`, `privacy`, `views`
- GIN: `tags` (full-text search)

**Foreign Key Constraints:**
- `userId` → `users.id` (ON DELETE CASCADE)

### 3. settings
**Description:** Stores user-specific settings for the video player, ads, and other configurations.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| id | UUID | PRIMARY KEY, NOT NULL | UUIDV4 | Unique identifier for each setting record |
| userId | UUID | UNIQUE, REFERENCES users(id), ON DELETE CASCADE | NOT NULL | Foreign key to user who owns these settings |
| playerSettings | JSONB | - | See model | Player configuration settings |
| adsSettings | JSONB | - | See model | Advertising configuration settings |
| defaultWatermark | JSONB | - | See model | Default watermark configuration |
| defaultDownloadEnabled | BOOLEAN | - | true | Default download enabled setting |
| googleDriveSettings | JSONB | - | See model | Google Drive integration settings |
| subtitleSettings | JSONB | - | See model | Subtitle display settings |
| websiteSettings | JSONB | - | See model | Website configuration (admin only) |
| ffmpegSettings | JSONB | - | See model | FFmpeg processing settings (admin only) |
| s3Settings | JSONB | - | See model | S3 storage settings (admin only) |
| redisSettings | JSONB | - | See model | Redis queue settings (admin only) |
| rateLimitSettings | JSONB | - | See model | Rate limiting configuration (admin only) |
| corsSettings | JSONB | - | See model | CORS policy settings (admin only) |
| analyticsSettings | JSONB | - | See model | Analytics tracking settings (admin only) |
| securitySettings | JSONB | - | See model | Security configuration (admin only) |
| emailSettings | JSONB | - | See model | Email service settings (admin only) |
| createdAt | TIMESTAMP | NOT NULL | NOW() | Record creation timestamp |
| updatedAt | TIMESTAMP | NOT NULL | NOW() | Record update timestamp |

**Indexes:**
- Primary Key: `id`
- Unique: `userId`

**Foreign Key Constraints:**
- `userId` → `users.id` (ON DELETE CASCADE)

### 4. analytics
**Description:** Stores video analytics and user engagement data.

| Column | Type | Constraints | Default | Description |
|--------|------|-------------|---------|-------------|
| id | UUID | PRIMARY KEY, NOT NULL | UUIDV4 | Unique identifier for each analytics record |
| videoId | UUID | REFERENCES videos(id), ON DELETE CASCADE | NOT NULL | Foreign key to the video being watched |
| userId | UUID | REFERENCES users(id), ON DELETE SET NULL | NULL | Foreign key to the user watching the video |
| sessionId | VARCHAR(64) | NOT NULL | - | Unique session identifier |
| ipAddress | INET | - | NULL | IP address of the viewer |
| userAgent | TEXT | - | NULL | User agent string from the browser |
| device | JSONB | - | See model | Device information as JSON |
| country | VARCHAR(2) | - | NULL | Country code of the viewer |
| city | VARCHAR(100) | - | NULL | City name of the viewer |
| region | VARCHAR(100) | - | NULL | Region name of the viewer |
| referrer | TEXT | - | NULL | Referrer URL |
| source | ENUM('direct', 'embed', 'social', 'search', 'other') | - | 'direct' | Traffic source type |
| watchTime | INTEGER | - | 0 | Time spent watching in seconds |
| completionRate | FLOAT | CHECK(0-100) | 0 | Video completion percentage |
| quality | VARCHAR(10) | - | NULL | Quality setting used during playback |
| events | JSONB | - | [] | Array of tracked events |
| isComplete | BOOLEAN | - | false | Whether video was watched to completion |
| liked | BOOLEAN | - | false | Whether video was liked |
| shared | BOOLEAN | - | false | Whether video was shared |
| startedAt | DATE | - | NOW() | Timestamp when session started |
| endedAt | DATE | - | NULL | Timestamp when session ended |
| createdAt | TIMESTAMP | NOT NULL | NOW() | Record creation timestamp |
| updatedAt | TIMESTAMP | NOT NULL | NOW() | Record update timestamp |

**Indexes:**
- Primary Key: `id`
- Composite: `videoId`, `createdAt`
- Composite: `userId`, `createdAt`
- Single: `sessionId`, `source`, `country`
- GIN: `device` (JSONB index)

**Foreign Key Constraints:**
- `videoId` → `videos.id` (ON DELETE CASCADE)
- `userId` → `users.id` (ON DELETE SET NULL)

## Relationships

### One-to-Many Relationships
- **User → Video**: A user can upload multiple videos (`User.hasMany(Video)`)
- **User → Settings**: A user has one settings record (`User.hasOne(Settings)`)
- **Video → Analytics**: A video can have multiple analytics records (`Video.hasMany(Analytics)`)
- **User → Analytics**: A user can have multiple analytics records (`User.hasMany(Analytics)`)

### Many-to-One Relationships
- **Video → User**: A video belongs to one user (`Video.belongsTo(User)`)
- **Settings → User**: Settings belong to one user (`Settings.belongsTo(User)`)
- **Analytics → Video**: Analytics belong to one video (`Analytics.belongsTo(Video)`)
- **Analytics → User**: Analytics can belong to one user (optional) (`Analytics.belongsTo(User)`)

## Enums

### Video Status Enum
- `'uploading'`
- `'queued'`
- `'processing'`
- `'completed'`
- `'failed'`

### Video Processing Phase Enum
- `'pending'`
- `'downloading'`
- `'converting'`
- `'completed'`
- `'failed'`

### User Role Enum
- `'user'`
- `'admin'`

### Video Privacy Enum
- `'public'`
- `'private'`
- `'unlisted'`
- `'password'`

### Upload Type Enum
- `'direct'`
- `'remote'`
- `'chunked'`
- `'googledrive'`

### Storage Type Enum
- `'local'`
- `'s3'`
- `'minio'`

### Traffic Source Enum
- `'direct'`
- `'embed'`
- `'social'`
- `'search'`
- `'other'`

## Constraints

### Foreign Key Constraints
- `videos.userId` references `users.id` with CASCADE delete
- `settings.userId` references `users.id` with CASCADE delete
- `analytics.videoId` references `videos.id` with CASCADE delete
- `analytics.userId` references `users.id` with SET NULL on delete

### Validation Constraints
- `videos.processingProgress`, `downloadProgress`, `convertProgress` must be between 0-100
- `analytics.completionRate` must be between 0-100
- `users.username` length between 3-50 characters, alphanumeric with underscores only
- `users.email` must be a valid email format
- `users.password` length between 6-255 characters
- `videos.title` length up to 200 characters

### Unique Constraints
- `users.username` (unique)
- `users.email` (unique)
- `users.apiKey` (unique, nullable)
- `settings.userId` (unique)

## Special Features

### JSONB Columns
The database makes extensive use of PostgreSQL's JSONB data type for flexible configuration storage:
- `videos.qualityVariants`
- `videos.resolution`
- `videos.codec`
- `videos.watermark`
- `videos.subtitles`
- `settings.playerSettings`
- `settings.adsSettings`
- `settings.defaultWatermark`
- `settings.googleDriveSettings`
- `settings.subtitleSettings`
- And many more configuration objects

### Array Columns
- `videos.tags` - Array of text tags for search and categorization
- `videos.allowedDomains` - Array of domains allowed to embed the video
- `analytics.events` - Array of tracked events during the session

### Indexes
- GIN indexes for JSONB and array columns to optimize query performance
- Regular B-tree indexes for frequently queried fields
- Composite indexes for common query patterns

# API Endpoints Documentation

## Overview
This document describes all the API endpoints available in the HLS Video Converter API built with Node.js and Express.

## Base URL
`http://localhost:3000` (or as configured in your environment)

## Authentication
- Some endpoints require authentication via JWT token in the Authorization header
- Admin endpoints require admin role
- Public endpoints are accessible without authentication

## API Endpoints

### Authentication Endpoints (`/api/auth`)

#### POST `/api/auth/register`
Register a new user account.

**Request:**
```json
{
  "username": "johndoe",
  "email": "john@example.com",
  "password": "securepassword123"
}
```

**Response (Success - 201):**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": "uuid-string",
    "username": "johndoe",
    "email": "john@example.com",
    "role": "user",
    "storageUsed": 0,
    "storageLimit": 5368709120,
    "isActive": true,
    "createdAt": "2023-01-01T00:00:00.000Z"
  },
  "token": "jwt-token-string"
}
```

**Response (Error - 400):**
```json
{
  "error": "Validation error message"
}
```

#### POST `/api/auth/login`
Login and get JWT token.

**Request:**
```json
{
  "username": "johndoe",
  "password": "securepassword123"
}
```

**Response (Success - 200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": "uuid-string",
    "username": "johndoe",
    "email": "john@example.com",
    "role": "user",
    "storageUsed": 0,
    "storageLimit": 5368709120
  },
  "token": "jwt-token-string"
}
```

**Response (Error - 401):**
```json
{
  "error": "Invalid credentials"
}
```

#### GET `/api/auth/me`
Get current user information.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "user": {
    "id": "uuid-string",
    "username": "johndoe",
    "email": "john@example.com",
    "role": "user",
    "storageUsed": 0,
    "storageLimit": 5368709120,
    "isActive": true,
    "adsDisabled": false,
    "createdAt": "2023-01-01T00:00:00.000Z",
    "updatedAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### PUT `/api/auth/profile`
Update user profile information.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "username": "newusername",
  "email": "newemail@example.com"
}
```

**Response (Success - 200):**
```json
{
  "message": "Profile updated successfully",
  "user": {
    "id": "uuid-string",
    "username": "newusername",
    "email": "newemail@example.com",
    "updatedAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### PUT `/api/auth/password`
Change user password.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "currentPassword": "oldpassword123",
  "newPassword": "newpassword123"
}
```

**Response (Success - 200):**
```json
{
  "message": "Password changed successfully"
}
```

### Video Endpoints (`/api/videos`)

#### GET `/api/videos/queue/stats`
Get queue statistics.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "queueSize": 10,
  "processingCount": 2,
  "waitingCount": 8,
  "averageWaitTime": 300
}
```

#### POST `/api/videos/chunk-start`
Start a chunked video upload.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Request:**
```json
{
  "fileName": "video.mp4",
  "fileSize": 104857600,
  "totalChunks": 10,
  "contentType": "video/mp4"
}
```

**Response (Success - 200):**
```json
{
  "sessionId": "session-uuid",
  "uploadPath": "/uploads/chunks/session-uuid",
  "message": "Chunk upload session started"
}
```

#### POST `/api/videos/chunk-upload`
Upload a video chunk.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Request (Multipart Form Data):**
```
- chunk: [file chunk]
- sessionId: session-uuid
- chunkIndex: 0
- totalChunks: 10
```

**Response (Success - 200):**
```json
{
  "message": "Chunk uploaded successfully",
  "chunkIndex": 0,
  "sessionId": "session-uuid"
}
```

#### POST `/api/videos/chunk-complete`
Complete a chunked upload.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Request:**
```json
{
  "sessionId": "session-uuid",
  "fileName": "video.mp4",
  "originalFileName": "original-video.mp4",
  "title": "My Video",
  "description": "A sample video",
  "privacy": "public"
}
```

**Response (Success - 200):**
```json
{
  "message": "Chunked upload completed",
  "video": {
    "id": "video-uuid",
    "title": "My Video",
    "status": "uploading"
  }
}
```

#### POST `/api/videos/chunk-cancel`
Cancel a chunked upload.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Request:**
```json
{
  "sessionId": "session-uuid"
}
```

**Response (Success - 200):**
```json
{
  "message": "Chunked upload cancelled"
}
```

#### GET `/api/videos/chunk-status/:sessionId`
Check chunk upload status.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Response (Success - 200):**
```json
{
  "sessionId": "session-uuid",
  "uploadedChunks": 5,
  "totalChunks": 10,
  "status": "in-progress",
  "message": "5 out of 10 chunks uploaded"
}
```

#### POST `/api/videos/upload`
Upload a video file directly.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- file: [video file]
- title: "My Video"
- description: "A sample video"
- privacy: "public"
- tags: ["tag1", "tag2"]
```

**Response (Success - 200):**
```json
{
  "message": "Video uploaded successfully",
  "video": {
    "id": "video-uuid",
    "title": "My Video",
    "status": "uploading",
    "originalFileName": "video.mp4",
    "originalFileSize": 104857600
  }
}
```

#### POST `/api/videos/remote-upload`
Upload video from remote URL.

**Headers:**
```
Authorization: Bearer {jwt-token} (optional)
```

**Request:**
```json
{
  "url": "https://example.com/video.mp4",
  "title": "Remote Video",
  "description": "A video from remote URL",
  "privacy": "public"
}
```

**Response (Success - 200):**
```json
{
  "message": "Remote video upload started",
  "video": {
    "id": "video-uuid",
    "title": "Remote Video",
    "status": "queued",
    "remoteUrl": "https://example.com/video.mp4"
  }
}
```

#### GET `/api/videos`
Get list of user videos.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `status` (optional): Filter by status
- `privacy` (optional): Filter by privacy setting

**Response (Success - 200):**
```json
{
  "videos": [
    {
      "id": "video-uuid",
      "title": "My Video",
      "description": "A sample video",
      "originalFileName": "video.mp4",
      "originalFileSize": 104857600,
      "status": "completed",
      "processingProgress": 100,
      "privacy": "public",
      "views": 150,
      "likes": 10,
      "dislikes": 0,
      "duration": 120.5,
      "resolution": {"width": 1920, "height": 1080},
      "hlsPlaylistUrl": "http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8",
      "thumbnailPath": "/uploads/thumbnails/video-uuid.jpg",
      "createdAt": "2023-01-01T00:00:00.000Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 15,
    "totalPages": 2
  }
}
```

#### GET `/api/videos/:id`
Get detailed information about a video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "video": {
    "id": "video-uuid",
    "title": "My Video",
    "description": "A sample video",
    "originalFileName": "video.mp4",
    "originalFileSize": 104857600,
    "hlsPath": "/uploads/hls/video-uuid",
    "hlsPlaylistUrl": "http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8",
    "qualityVariants": [
      {
        "resolution": "1080p",
        "url": "http://localhost:3000/api/stream/user-uuid/video-uuid/1080p.m3u8",
        "enabled": true
      }
    ],
    "thumbnailPath": "/uploads/thumbnails/video-uuid.jpg",
    "duration": 120.5,
    "resolution": {"width": 1920, "height": 1080},
    "fps": 30,
    "codec": {"video": "h264", "audio": "aac"},
    "status": "completed",
    "processingPhase": "completed",
    "processingProgress": 100,
    "downloadEnabled": true,
    "embedEnabled": true,
    "views": 150,
    "likes": 10,
    "dislikes": 0,
    "privacy": "public",
    "tags": ["tag1", "tag2"],
    "allowedDomains": ["example.com"],
    "watermark": {
      "enabled": true,
      "position": "bottom-right",
      "imagePath": "/uploads/watermarks/watermark.png",
      "opacity": 0.5
    },
    "subtitles": [
      {
        "id": "subtitle-uuid",
        "language": "en",
        "label": "English",
        "filePath": "/uploads/subtitles/subtitle.vtt"
      }
    ],
    "createdAt": "2023-01-01T00:00:00.000Z",
    "updatedAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### PUT `/api/videos/:id`
Update video information.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "title": "Updated Video Title",
  "description": "Updated description",
  "privacy": "private",
  "tags": ["new-tag1", "new-tag2"],
  "allowedDomains": ["newdomain.com"]
}
```

**Response (Success - 200):**
```json
{
  "message": "Video updated successfully",
  "video": {
    "id": "video-uuid",
    "title": "Updated Video Title",
    "description": "Updated description",
    "privacy": "private",
    "updatedAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### DELETE `/api/videos/:id`
Delete a video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Video deleted successfully"
}
```

#### GET `/api/videos/:id/status`
Get video processing status.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "status": "completed",
  "processingPhase": "completed",
  "processingProgress": 100,
  "downloadProgress": 100,
  "convertProgress": 100,
  "queuePosition": 0,
  "processingStartedAt": "2023-01-01T00:00:00.000Z",
  "processingCompletedAt": "2023-01-01T00:00:00.000Z",
  "estimatedCompletion": null
}
```

#### POST `/api/videos/:id/view`
Increment video view count.

**Response (Success - 200):**
```json
{
  "message": "View recorded successfully"
}
```

#### GET `/api/videos/:id/download`
Download video file.

**Response (Success - 200):**
```
File download (video file)
```

#### GET `/api/videos/:id/watermark`
Get video watermark information.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "watermark": {
    "enabled": true,
    "position": "bottom-right",
    "imagePath": "/uploads/watermarks/watermark.png",
    "opacity": 0.5,
    "size": 50
  }
}
```

#### POST `/api/videos/:id/watermark`
Add watermark to video.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- watermarkFile: [image file]
- position: "bottom-right"
- opacity: 0.5
```

**Response (Success - 200):**
```json
{
  "message": "Watermark added successfully",
  "watermark": {
    "enabled": true,
    "position": "bottom-right",
    "imagePath": "/uploads/watermarks/watermark.png",
    "opacity": 0.5
  }
}
```

#### DELETE `/api/videos/:id/watermark`
Remove watermark from video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Watermark removed successfully"
}
```

#### POST `/api/videos/:id/reprocess`
Re-process video with new settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "qualitySettings": {
    "resolutions": ["1080p", "720p", "480p"],
    "bitrate": "high"
  },
  "watermark": {
    "enabled": true,
    "position": "bottom-right",
    "opacity": 0.5
  }
}
```

**Response (Success - 200):**
```json
{
  "message": "Video reprocessing started",
  "video": {
    "id": "video-uuid",
    "status": "queued",
    "processingPhase": "pending"
  }
}
```

#### POST `/api/videos/:id/subtitle`
Add subtitle to video.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- subtitleFile: [subtitle file in .vtt format]
- language: "en"
- label: "English"
```

**Response (Success - 200):**
```json
{
  "message": "Subtitle added successfully",
  "subtitle": {
    "id": "subtitle-uuid",
    "language": "en",
    "label": "English",
    "filePath": "/uploads/subtitles/subtitle.vtt"
  }
}
```

#### DELETE `/api/videos/:id/subtitle/:subtitleId`
Remove subtitle from video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Subtitle removed successfully"
}
```

#### POST `/api/videos/:id/quality`
Add quality variant to video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "resolutions": ["1080p", "720p", "480p"],
  "bitrate": "medium"
}
```

**Response (Success - 200):**
```json
{
  "message": "Quality variants added successfully",
  "qualityVariants": [
    {
      "resolution": "1080p",
      "url": "http://localhost:3000/api/stream/user-uuid/video-uuid/1080p.m3u8",
      "enabled": true
    }
  ]
}
```

#### PUT `/api/videos/:id/quality/toggle-all`
Toggle all quality variants on/off.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Quality variants updated successfully",
  "enabled": false
}
```

#### PATCH `/api/videos/:id/download`
Toggle download enabled/disabled.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Download setting updated",
  "downloadEnabled": false
}
```

#### PATCH `/api/videos/:id/embed`
Toggle embed enabled/disabled.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Embed setting updated",
  "embedEnabled": false
}
```

#### PUT `/api/videos/:id/allowed-domains`
Update allowed domains for embedding.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "allowedDomains": ["example.com", "mywebsite.com"]
}
```

**Response (Success - 200):**
```json
{
  "message": "Allowed domains updated",
  "allowedDomains": ["example.com", "mywebsite.com"]
}
```

### Settings Endpoints (`/api/settings`)

#### GET `/api/settings/googledrive/callback`
Google Drive OAuth callback.

**Query Parameters:**
- `code`: Authorization code from Google
- `state`: State parameter for security

**Response (Success - 302):**
```
Redirect to frontend with success message
```

#### GET `/api/settings`
Get user's settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "settings": {
    "id": "settings-uuid",
    "playerSettings": {
      "theme": "dark",
      "autoplay": false,
      "loop": false,
      "showControls": true,
      "defaultVolume": 80
    },
    "adsSettings": {
      "enabled": true,
      "frequency": 3,
      "skipOffset": 5
    },
    "defaultWatermark": {
      "enabled": false,
      "position": "bottom-right",
      "opacity": 0.5,
      "size": 50
    },
    "defaultDownloadEnabled": true,
    "googleDriveSettings": {
      "enabled": false,
      "lastSync": null
    },
    "subtitleSettings": {
      "defaultLanguage": "en",
      "fontSize": 14,
      "color": "#ffffff"
    },
    "createdAt": "2023-01-01T00:00:00.000Z",
    "updatedAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### PUT `/api/settings/player`
Update player settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "theme": "light",
  "autoplay": true,
  "loop": false,
  "showControls": true,
  "defaultVolume": 75
}
```

**Response (Success - 200):**
```json
{
  "message": "Player settings updated successfully",
  "settings": {
    "playerSettings": {
      "theme": "light",
      "autoplay": true,
      "loop": false,
      "showControls": true,
      "defaultVolume": 75
    }
  }
}
```

#### PUT `/api/settings/ads`
Update ads settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "frequency": 2,
  "skipOffset": 10
}
```

**Response (Success - 200):**
```json
{
  "message": "Ads settings updated successfully",
  "settings": {
    "adsSettings": {
      "enabled": true,
      "frequency": 2,
      "skipOffset": 10
    }
  }
}
```

#### PUT `/api/settings/watermark`
Update watermark settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data (if uploading image)
```

**Request:**
```json
{
  "enabled": true,
  "position": "bottom-right",
  "opacity": 0.7,
  "size": 60
}
```

**Response (Success - 200):**
```json
{
  "message": "Watermark settings updated successfully",
  "settings": {
    "defaultWatermark": {
      "enabled": true,
      "position": "bottom-right",
      "opacity": 0.7,
      "size": 60
    }
  }
}
```

#### PUT `/api/settings/download`
Update default download settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "defaultDownloadEnabled": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Download settings updated successfully",
  "settings": {
    "defaultDownloadEnabled": false
  }
}
```

#### PUT `/api/settings/googledrive`
Update Google Drive settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "folderId": "google-drive-folder-id"
}
```

**Response (Success - 200):**
```json
{
  "message": "Google Drive settings updated successfully",
  "settings": {
    "googleDriveSettings": {
      "enabled": true,
      "folderId": "google-drive-folder-id",
      "lastSync": null
    }
  }
}
```

#### GET `/api/settings/googledrive/auth-url`
Get Google Drive auth URL.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "authUrl": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...",
  "message": "Google Drive auth URL generated"
}
```

#### DELETE `/api/settings/googledrive/revoke`
Revoke Google Drive token.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Google Drive token revoked successfully"
}
```

#### PUT `/api/settings/subtitles`
Update subtitle settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "defaultLanguage": "es",
  "fontSize": 16,
  "color": "#ffff00",
  "background": "#00000080"
}
```

**Response (Success - 200):**
```json
{
  "message": "Subtitle settings updated successfully",
  "settings": {
    "subtitleSettings": {
      "defaultLanguage": "es",
      "fontSize": 16,
      "color": "#ffff00",
      "background": "#00000080"
    }
  }
}
```

#### GET `/api/settings/admin`
Get admin settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "settings": {
    "websiteSettings": {
      "siteName": "HLS Video Converter",
      "siteDescription": "Convert videos to HLS format",
      "siteUrl": "http://localhost:3000",
      "allowRegistrations": true
    },
    "ffmpegSettings": {
      "binaryPath": "/usr/bin/ffmpeg",
      "qualityPresets": "medium",
      "maxWorkers": 2
    },
    "s3Settings": {
      "enabled": false,
      "endpoint": "",
      "accessKeyId": "",
      "secretAccessKey": "",
      "bucket": ""
    },
    "emailSettings": {
      "enabled": false,
      "host": "smtp.gmail.com",
      "port": 587,
      "secure": false
    },
    "securitySettings": {
      "rateLimiting": true,
      "maxLoginAttempts": 5,
      "passwordRequirements": {
        "minLength": 8,
        "requireNumbers": true,
        "requireSymbols": true
      }
    }
  }
}
```

#### PUT `/api/settings/admin/ffmpeg`
Update FFmpeg settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "binaryPath": "/usr/local/bin/ffmpeg",
  "qualityPresets": "high",
  "maxWorkers": 4,
  "customArgs": [
    "-preset",
    "medium",
    "-crf",
    "20"
  ]
}
```

**Response (Success - 200):**
```json
{
  "message": "FFmpeg settings updated successfully",
  "settings": {
    "ffmpegSettings": {
      "binaryPath": "/usr/local/bin/ffmpeg",
      "qualityPresets": "high",
      "maxWorkers": 4,
      "customArgs": [
        "-preset",
        "medium",
        "-crf",
        "20"
      ]
    }
  }
}
```

#### PUT `/api/settings/admin/s3`
Update S3 settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "endpoint": "https://s3.example.com",
  "accessKeyId": "your-access-key",
  "secretAccessKey": "your-secret-key",
  "bucket": "your-bucket-name",
  "region": "us-east-1"
}
```

**Response (Success - 200):**
```json
{
  "message": "S3 settings updated successfully",
  "settings": {
    "s3Settings": {
      "enabled": true,
      "endpoint": "https://s3.example.com",
      "accessKeyId": "your-access-key",
      "secretAccessKey": "your-secret-key",
      "bucket": "your-bucket-name",
      "region": "us-east-1"
    }
  }
}
```

#### PUT `/api/settings/admin/website`
Update website settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "siteName": "My Video Platform",
  "siteDescription": "The best platform for video conversion",
  "siteUrl": "https://myplatform.com",
  "allowRegistrations": true,
  "maintenanceMode": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Website settings updated successfully",
  "settings": {
    "websiteSettings": {
      "siteName": "My Video Platform",
      "siteDescription": "The best platform for video conversion",
      "siteUrl": "https://myplatform.com",
      "allowRegistrations": true,
      "maintenanceMode": false
    }
  }
}
```

#### PUT `/api/settings/admin/security`
Update security settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "rateLimiting": true,
  "maxLoginAttempts": 3,
  "passwordRequirements": {
    "minLength": 10,
    "requireNumbers": true,
    "requireSymbols": true,
    "requireUppercase": true
  }
}
```

**Response (Success - 200):**
```json
{
  "message": "Security settings updated successfully",
  "settings": {
    "securitySettings": {
      "rateLimiting": true,
      "maxLoginAttempts": 3,
      "passwordRequirements": {
        "minLength": 10,
        "requireNumbers": true,
        "requireSymbols": true,
        "requireUppercase": true
      }
    }
  }
}
```

#### PUT `/api/settings/admin/email`
Update email settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "host": "smtp.gmail.com",
  "port": 587,
  "secure": false,
  "auth": {
    "user": "your-email@gmail.com",
    "pass": "your-app-password"
  },
  "from": "noreply@yourdomain.com"
}
```

**Response (Success - 200):**
```json
{
  "message": "Email settings updated successfully",
  "settings": {
    "emailSettings": {
      "enabled": true,
      "host": "smtp.gmail.com",
      "port": 587,
      "secure": false,
      "auth": {
        "user": "your-email@gmail.com",
        "pass": "your-app-password"
      },
      "from": "noreply@yourdomain.com"
    }
  }
}
```

#### POST `/api/settings/admin/email/test`
Test email settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "to": "test@example.com",
  "subject": "Test Email",
  "text": "This is a test email from the video platform."
}
```

**Response (Success - 200):**
```json
{
  "message": "Test email sent successfully"
}
```

#### POST `/api/settings/admin/favicon`
Upload favicon.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- favicon: [favicon image file]
```

**Response (Success - 200):**
```json
{
  "message": "Favicon uploaded successfully",
  "faviconPath": "/uploads/admin/favicon.png"
}
```

#### POST `/api/settings/admin/logo`
Upload logo.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- logo: [logo image file]
```

**Response (Success - 200):**
```json
{
  "message": "Logo uploaded successfully",
  "logoPath": "/uploads/admin/logo.png"
}
```

#### DELETE `/api/settings/admin/favicon`
Delete favicon.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Favicon deleted successfully"
}
```

#### DELETE `/api/settings/admin/logo`
Delete logo.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Logo deleted successfully"
}
```

### Analytics Endpoints (`/api/analytics`)

#### GET `/api/analytics/session`
Generate a new analytics session.

**Response (Success - 200):**
```json
{
  "sessionId": "session-uuid",
  "message": "Analytics session generated"
}
```

#### POST `/api/analytics/track`
Track an analytics event.

**Request:**
```json
{
  "videoId": "video-uuid",
  "sessionId": "session-uuid",
  "event": "play",
  "quality": "1080p",
  "currentTime": 30,
  "duration": 120,
  "watchTime": 30,
  "ipAddress": "192.168.1.1",
  "userAgent": "Mozilla/5.0...",
  "referrer": "https://example.com"
}
```

**Response (Success - 200):**
```json
{
  "message": "Event tracked successfully"
}
```

#### GET `/api/analytics/summary`
Get analytics summary.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Query Parameters:**
- `startDate` (optional): Start date for analytics period
- `endDate` (optional): End date for analytics period
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)

**Response (Success - 200):**
```json
{
  "summary": {
    "totalViews": 1500,
    "uniqueViews": 850,
    "totalWatchTime": 36000,
    "averageWatchTime": 24,
    "totalLikes": 50,
    "totalDislikes": 5,
    "topVideos": [
      {
        "id": "video-uuid",
        "title": "Top Video",
        "views": 300,
        "likes": 15,
        "dislikes": 0
      }
    ]
  },
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 1,
    "totalPages": 1
  }
}
```

#### GET `/api/analytics/video/:videoId`
Get analytics for a specific video.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Query Parameters:**
- `startDate` (optional): Start date for analytics period
- `endDate` (optional): End date for analytics period

**Response (Success - 200):**
```json
{
  "analytics": {
    "videoId": "video-uuid",
    "totalViews": 250,
    "uniqueViews": 180,
    "totalWatchTime": 8000,
    "averageWatchTime": 32,
    "completionRate": 65.5,
    "engagement": {
      "likes": 10,
      "dislikes": 2,
      "shares": 5,
      "comments": 8
    },
    "breakdown": {
      "byDevice": [
        {
          "device": "desktop",
          "views": 150,
          "percentage": 60
        },
        {
          "device": "mobile",
          "views": 100,
          "percentage": 40
        }
      ],
      "bySource": [
        {
          "source": "direct",
          "views": 100,
          "percentage": 40
        },
        {
          "source": "embed",
          "views": 80,
          "percentage": 32
        }
      ],
      "byCountry": [
        {
          "country": "US",
          "views": 120,
          "percentage": 48
        },
        {
          "country": "ID",
          "views": 80,
          "percentage": 32
        }
      ],
      "byQuality": [
        {
          "quality": "1080p",
          "views": 150,
          "percentage": 60
        },
        {
          "quality": "720p",
          "views": 100,
          "percentage": 40
        }
      ]
    }
  }
}
```

### Admin Endpoints (`/api/admin`)

#### GET `/api/admin/favicon`
Get website favicon.

**Response (Success - 200):**
```
Favicon image file
```

#### PUT `/api/admin/settings/redis`
Update Redis settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "host": "localhost",
  "port": 6379,
  "password": "redis-password",
  "db": 0,
  "prefix": "hls:"
}
```

**Response (Success - 200):**
```json
{
  "message": "Redis settings updated successfully",
  "settings": {
    "redisSettings": {
      "host": "localhost",
      "port": 6379,
      "password": "redis-password",
      "db": 0,
      "prefix": "hls:"
    }
  }
}
```

#### PUT `/api/admin/settings/ratelimit`
Update rate limiting settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "windowMs": 900000,
  "max": 100,
  "message": "Too many requests, please try again later."
}
```

**Response (Success - 200):**
```json
{
  "message": "Rate limiting settings updated successfully",
  "settings": {
    "rateLimitSettings": {
      "windowMs": 900000,
      "max": 100,
      "message": "Too many requests, please try again later."
    }
  }
}
```

#### PUT `/api/admin/settings/cors`
Update CORS settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "origin": ["http://localhost:3000", "https://mydomain.com"],
  "credentials": true,
  "methods": ["GET", "POST", "PUT", "DELETE"],
  "allowedHeaders": ["Content-Type", "Authorization"]
}
```

**Response (Success - 200):**
```json
{
  "message": "CORS settings updated successfully",
  "settings": {
    "corsSettings": {
      "origin": ["http://localhost:3000", "https://mydomain.com"],
      "credentials": true,
      "methods": ["GET", "POST", "PUT", "DELETE"],
      "allowedHeaders": ["Content-Type", "Authorization"]
    }
  }
}
```

#### PUT `/api/admin/settings/analytics`
Update analytics settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "trackAnonymously": false,
  "retentionPeriod": 365
}
```

**Response (Success - 200):**
```json
{
  "message": "Analytics settings updated successfully",
  "settings": {
    "analyticsSettings": {
      "enabled": true,
      "trackAnonymously": false,
      "retentionPeriod": 365
    }
  }
}
```

#### GET `/api/admin/settings/googledrive`
Get Google Drive settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "settings": {
    "googleDriveSettings": {
      "enabled": true,
      "clientId": "your-client-id",
      "clientSecret": "your-client-secret",
      "redirectUri": "http://localhost:3000/api/settings/googledrive/callback",
      "folderId": "your-folder-id"
    }
  }
}
```

#### PUT `/api/admin/settings/googledrive`
Update Google Drive settings.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "enabled": true,
  "clientId": "new-client-id",
  "clientSecret": "new-client-secret",
  "redirectUri": "http://localhost:3000/api/settings/googledrive/callback",
  "folderId": "new-folder-id"
}
```

**Response (Success - 200):**
```json
{
  "message": "Google Drive settings updated successfully",
  "settings": {
    "googleDriveSettings": {
      "enabled": true,
      "clientId": "new-client-id",
      "clientSecret": "new-client-secret",
      "redirectUri": "http://localhost:3000/api/settings/googledrive/callback",
      "folderId": "new-folder-id"
    }
  }
}
```

#### POST `/api/admin/settings/favicon`
Upload favicon.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- favicon: [favicon image file]
```

**Response (Success - 200):**
```json
{
  "message": "Favicon uploaded successfully",
  "faviconPath": "/uploads/admin/favicon.png"
}
```

#### DELETE `/api/admin/settings/favicon`
Delete favicon.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Favicon deleted successfully"
}
```

#### GET `/api/admin/users`
Get list of users.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search term for username or email
- `role` (optional): Filter by role (user/admin)
- `active` (optional): Filter by active status

**Response (Success - 200):**
```json
{
  "users": [
    {
      "id": "user-uuid",
      "username": "johndoe",
      "email": "john@example.com",
      "role": "user",
      "storageUsed": 1048576,
      "storageLimit": 5368709120,
      "isActive": true,
      "adsDisabled": false,
      "lastLoginAt": "2023-01-01T00:00:00.000Z",
      "createdAt": "2023-01-01T00:00:00.000Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 25,
    "totalPages": 3
  }
}
```

#### GET `/api/admin/users/:id`
Get specific user details.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "user": {
    "id": "user-uuid",
    "username": "johndoe",
    "email": "john@example.com",
    "role": "user",
    "storageUsed": 1048576,
    "storageLimit": 5368709120,
    "isActive": true,
    "adsDisabled": false,
    "lastLoginAt": "2023-01-01T00:00:00.000Z",
    "createdAt": "2023-01-01T00:00:00.000Z",
    "videos": [
      {
        "id": "video-uuid",
        "title": "User's Video",
        "status": "completed",
        "views": 150,
        "createdAt": "2023-01-01T00:00:00.000Z"
      }
    ]
  }
}
```

#### PUT `/api/admin/users/:id`
Update user information.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "username": "newusername",
  "email": "newemail@example.com",
  "role": "admin",
  "isActive": true
}
```

**Response (Success - 200):**
```json
{
  "message": "User updated successfully",
  "user": {
    "id": "user-uuid",
    "username": "newusername",
    "email": "newemail@example.com",
    "role": "admin",
    "isActive": true
  }
}
```

#### PUT `/api/admin/users/:id/ban`
Toggle user ban status.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "User ban status updated",
  "user": {
    "id": "user-uuid",
    "isActive": false
  }
}
```

#### PUT `/api/admin/users/:id/ads`
Toggle user ads (enable/disable ads on their videos).

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "adsDisabled": true
}
```

**Response (Success - 200):**
```json
{
  "message": "User ads setting updated",
  "user": {
    "id": "user-uuid",
    "adsDisabled": true
  }
}
```

#### PUT `/api/admin/users/:id/storage`
Update user storage limits.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "storageLimit": 10737418240
}
```

**Response (Success - 200):**
```json
{
  "message": "User storage limit updated",
  "user": {
    "id": "user-uuid",
    "storageLimit": 10737418240
  }
}
```

#### PUT `/api/admin/users/:id/reset-password`
Reset user password.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "newPassword": "newSecurePassword123"
}
```

**Response (Success - 200):**
```json
{
  "message": "User password reset successfully"
}
```

#### DELETE `/api/admin/users/:id`
Delete user account.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "User account deleted successfully"
}
```

#### GET `/api/admin/storage`
Get storage statistics.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "storage": {
    "totalUsed": 5368709120,
    "totalAvailable": 10737418240,
    "totalLimit": 21474836480,
    "usagePercentage": 50,
    "usersCount": 100,
    "videosCount": 500
  }
}
```

#### GET `/api/admin/videos`
Get videos for all users.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `status` (optional): Filter by status
- `userId` (optional): Filter by user ID
- `search` (optional): Search term for title or description

**Response (Success - 200):**
```json
{
  "videos": [
    {
      "id": "video-uuid",
      "title": "Admin Video",
      "description": "Video description",
      "userId": "user-uuid",
      "username": "johndoe",
      "status": "completed",
      "originalFileName": "video.mp4",
      "originalFileSize": 104857600,
      "views": 150,
      "createdAt": "2023-01-01T00:00:00.000Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 500,
    "totalPages": 50
  }
}
```

#### GET `/api/admin/backup`
List all backups.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "backups": [
    {
      "filename": "backup_20230101_120000.sql",
      "size": 1048576,
      "createdAt": "2023-01-01T12:00:00.000Z",
      "format": "sql",
      "status": "completed"
    }
  ]
}
```

#### POST `/api/admin/backup`
Create new backup.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "format": "sql",
  "includeMedia": true
}
```

**Response (Success - 200):**
```json
{
  "message": "Backup created successfully",
  "backup": {
    "filename": "backup_20230101_120000.sql",
    "size": 1048576,
    "createdAt": "2023-01-01T12:00:00.000Z",
    "format": "sql",
    "status": "completed"
  }
}
```

#### GET `/api/admin/backup/download/:filename`
Download backup file.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```
Backup file download
```

#### POST `/api/admin/backup/restore`
Restore from backup.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "filename": "backup_20230101_120000.sql"
}
```

**Response (Success - 200):**
```json
{
  "message": "Backup restore started",
  "status": "in-progress"
}
```

#### POST `/api/admin/backup/upload`
Upload and restore backup.

**Headers:**
```
Authorization: Bearer {jwt-token}
Content-Type: multipart/form-data
```

**Request (Multipart Form Data):**
```
- backupFile: [backup file]
```

**Response (Success - 200):**
```json
{
  "message": "Backup file uploaded and restore started",
  "filename": "uploaded_backup_20230101_120000.sql"
}
```

#### DELETE `/api/admin/backup/:filename`
Delete backup file.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Backup file deleted successfully"
}
```

#### GET `/api/admin/backup/status`
Get backup status.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "status": {
    "lastBackup": "2023-01-01T12:00:00.000Z",
    "isBackupRunning": false,
    "nextScheduledBackup": "2023-01-02T02:00:00.000Z",
    "backupDirectory": "/backups"
  }
}
```

#### GET `/api/admin/backup/stats`
Get backup statistics.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "stats": {
    "totalBackups": 30,
    "totalSize": 31457280,
    "oldestBackup": "2022-12-01T12:00:00.000Z",
    "newestBackup": "2023-01-01T12:00:00.000Z",
    "avgBackupSize": 1048576
  }
}
```

#### POST `/api/admin/backup/verify/:filename`
Verify specific backup.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Backup verification completed",
  "verified": true,
  "details": {
    "filename": "backup_20230101_120000.sql",
    "valid": true,
    "tables": 5,
    "records": 1250
  }
}
```

#### POST `/api/admin/backup/verify-all`
Verify all backups.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "message": "Backup verification completed",
  "results": [
    {
      "filename": "backup_20230101_120000.sql",
      "valid": true
    },
    {
      "filename": "backup_20221231_120000.sql",
      "valid": true
    }
  ]
}
```

#### GET `/api/admin/backup/file-stats/:filename`
Get backup file statistics.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "stats": {
    "filename": "backup_20230101_120000.sql",
    "size": 1048576,
    "createdAt": "2023-01-01T12:00:00.000Z",
    "tables": 5,
    "records": 1250,
    "verified": true
  }
}
```

#### POST `/api/admin/backup/cleanup`
Cleanup old backups.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "olderThanDays": 30
}
```

**Response (Success - 200):**
```json
{
  "message": "Backup cleanup completed",
  "deletedCount": 5,
  "spaceFreed": 5242880
}
```

#### GET `/api/admin/backup/management-status`
Get backup management status.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Response (Success - 200):**
```json
{
  "status": {
    "enabled": true,
    "schedule": "0 2 * * *",
    "retentionDays": 30,
    "lastRun": "2023-01-01T02:00:00.000Z",
    "nextRun": "2023-01-02T02:00:00.000Z"
  }
}
```

#### POST `/api/admin/backup/trigger`
Trigger backup.

**Headers:**
```
Authorization: Bearer {jwt-token}
```

**Request:**
```json
{
  "format": "sql",
  "includeMedia": false
}
```

**Response (Success - 200):**
```json
{
  "message": "Manual backup triggered",
  "status": "started",
  "filename": "manual_backup_20230101_153000.sql"
}
```

### Public Endpoints (`/api/public`)

#### GET `/api/public/info`
Get public website information.

**Response (Success - 200):**
```json
{
  "info": {
    "siteName": "HLS Video Converter",
    "siteDescription": "Convert and stream videos in HLS format",
    "siteUrl": "http://localhost:3000",
    "allowGuestUploads": true,
    "guestStorageLimit": 536870912,
    "maxFileSize": 5368709120,
    "allowedFileTypes": ["mp4", "mov", "avi", "mkv", "webm"],
    "maintenanceMode": false,
    "version": "1.0.0"
  }
}
```

#### GET `/api/public/video/:id`
Get public video information.

**Response (Success - 200):**
```json
{
  "video": {
    "id": "video-uuid",
    "title": "Public Video",
    "description": "Public video description",
    "userId": "user-uuid",
    "originalFileName": "video.mp4",
    "originalFileSize": 104857600,
    "hlsPlaylistUrl": "http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8",
    "qualityVariants": [
      {
        "resolution": "1080p",
        "url": "http://localhost:3000/api/stream/user-uuid/video-uuid/1080p.m3u8",
        "enabled": true
      }
    ],
    "thumbnailPath": "/uploads/thumbnails/video-uuid.jpg",
    "duration": 120.5,
    "resolution": {"width": 1920, "height": 1080},
    "fps": 30,
    "codec": {"video": "h264", "audio": "aac"},
    "status": "completed",
    "views": 150,
    "likes": 10,
    "dislikes": 0,
    "privacy": "public",
    "tags": ["tag1", "tag2"],
    "downloadEnabled": true,
    "embedEnabled": true,
    "embedCode": "<iframe src='http://localhost:3000/embed/video-uuid' width='640' height='360' frameborder='0' allowfullscreen></iframe>",
    "createdAt": "2023-01-01T00:00:00.000Z"
  }
}
```

#### GET `/api/public/video/:id/status`
Get public video processing status.

**Response (Success - 200):**
```json
{
  "status": {
    "status": "completed",
    "processingPhase": "completed",
    "processingProgress": 100,
    "downloadProgress": 100,
    "convertProgress": 100,
    "estimatedCompletion": null
  }
}
```

#### POST `/api/public/upload`
Guest upload endpoint (if enabled).

**Request (Multipart Form Data):**
```
- file: [video file]
- title: "Guest Video"
- description: "Uploaded by guest"
- password: "optional-password-for-private-video"
```

**Response (Success - 200):**
```json
{
  "message": "Video uploaded successfully",
  "video": {
    "id": "video-uuid",
    "title": "Guest Video",
    "originalFileName": "video.mp4",
    "originalFileSize": 104857600,
    "status": "uploading",
    "isGuestUpload": true
  }
}
```

#### POST `/api/public/remote-upload`
Guest remote upload (if enabled).

**Request:**
```json
{
  "url": "https://example.com/video.mp4",
  "title": "Remote Guest Video",
  "description": "Remote video uploaded by guest",
  "password": "optional-password"
}
```

**Response (Success - 200):**
```json
{
  "message": "Remote video upload started",
  "video": {
    "id": "video-uuid",
    "title": "Remote Guest Video",
    "status": "queued",
    "remoteUrl": "https://example.com/video.mp4",
    "isGuestUpload": true
  }
}
```

### Stream Endpoints (`/api/stream`)

#### GET `/api/stream/:userId/:videoId/*`
Stream HLS files through proxy.

**Query Parameters:**
- `expires` (optional): Unix timestamp for URL expiration
- `signature` (optional): Signature for signed URLs (if enabled)

**Response (Success - 200):**
```
HLS stream file (playlist.m3u8, .ts chunks, etc.)
```

**Response (Error - 403):**
```json
{
  "error": "Access denied"
}
```

**Response (Error - 404):**
```json
{
  "error": "Stream file not found"
}
```

### Other Endpoints

#### GET `/health`
Health check endpoint.

**Response (Success - 200):**
```json
{
  "status": "healthy",
  "timestamp": "2023-01-01T00:00:00.000Z",
  "version": "1.0.0",
  "database": "connected",
  "redis": "connected"
}
```

#### GET `/`
Root endpoint with API info.

**Response (Success - 200):**
```json
{
  "name": "HLS Video Converter API",
  "version": "1.0.0",
  "description": "API for converting and streaming videos in HLS format",
  "endpoints": {
    "auth": "/api/auth",
    "videos": "/api/videos",
    "settings": "/api/settings",
    "analytics": "/api/analytics",
    "admin": "/api/admin",
    "public": "/api/public",
    "stream": "/api/stream"
  },
  "documentation": "/docs",
  "health": "/health"
}
```

#### GET `/embed/:videoId`
Render embed player page.

**Response (Success - 200):**
```html
<!DOCTYPE html>
<html>
<head>
    <title>Video Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/css/player.css">
</head>
<body>
    <div id="player-container">
        <!-- Video player HTML with embedded video -->
        <video id="video-player" controls width="100%" height="100%">
            <source src="http://localhost:3000/api/stream/user-uuid/video-uuid/playlist.m3u8" type="application/x-mpegURL">
            Your browser does not support the video tag.
        </video>
    </div>
    <script src="/js/player.js"></script>
</body>
</html>
```

#### GET `/favicon.ico`
Redirect to admin favicon.

**Response (Success - 302):**
```
Redirect to /api/admin/favicon
```

## Access Levels

- **Public**: Accessible without authentication
- **Protected**: Requires valid JWT token in Authorization header
- **Protected + Admin**: Requires valid JWT token AND admin role
- **Optional Auth**: May require authentication depending on system settings (e.g., guest uploads)

## HTTP Status Codes

- `200`: Success
- `201`: Created
- `400`: Bad Request (validation error)
- `401`: Unauthorized (no token or invalid token)
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found
- `429`: Too Many Requests (rate limit exceeded)
- `500`: Internal Server Error

# Controller Logic Documentation

## Overview
This section documents the logic implemented in each controller file for the HLS Video Converter API.

## Controllers

### authController.js
Handles user authentication operations including registration, login, and profile management.

**Functions:**
- `register`: Creates a new user account with validation and checks if registration is enabled in admin settings. Creates default settings for new users and sends a welcome email.
- `login`: Authenticates user login, validates credentials, updates last login timestamp, and generates JWT token.
- `getMe`: Retrieves current logged-in user information (excluding password).
- `updateProfile`: Updates user profile information (username, email).
- `changePassword`: Changes user password with validation of current password.

### videoController.js
Handles video operations including upload, management, processing status, and download.

**Functions:**
- `uploadVideo`: Processes direct video uploads, creates video record, updates user storage, and adds to processing queue.
- `remoteUpload`: Handles remote URL uploads with validation and Google Drive support, adds to queue for background processing.
- `getVideos`: Retrieves user's videos with pagination and filtering options.
- `getVideo`: Gets detailed information about a specific video with proper URL generation based on storage type (local/S3).
- `updateVideo`: Updates video metadata and settings.
- `toggleDownload`: Toggles download enable/disable for a video.
- `toggleEmbed`: Toggles embed enable/disable for a video.
- `updateAllowedDomains`: Updates allowed domains for video embedding.
- `deleteVideo`: Deletes video with proper cleanup of files (local/S3) and updates user storage.
- `getVideoStatus`: Retrieves video processing status and queue position.
- `downloadVideo`: Handles video file downloads with proper file serving based on storage type.
- `incrementViews`: Increments video view count.
- `getQueueStats`: Gets queue statistics (admin only).

### settingsController.js
Handles user and admin settings for player, ads, watermarks, Google Drive, subtitles, and various system settings.

**Functions:**
- `getSettings`: Retrieves user settings with fallback to create defaults.
- `updatePlayerSettings`: Updates player configuration settings.
- `updateAdsSettings`: Updates advertising configuration settings.
- `updateWatermarkSettings`: Updates default watermark settings with image upload support.
- `updateDownloadSettings`: Updates default download settings.
- `updateGoogleDriveSettings`: Updates Google Drive integration settings.
- `updateSubtitleSettings`: Updates subtitle configuration settings.
- `getAdminSettings`: Retrieves admin system settings.
- `updateFFmpegSettings`: Updates FFmpeg processing settings.
- `updateS3Settings`: Updates S3 storage settings.
- `updateWebsiteSettings`: Updates website configuration settings.
- `updateSecuritySettings`: Updates security configuration settings.
- `uploadFavicon`: Uploads website favicon.
- `uploadLogo`: Uploads website logo.
- `deleteFavicon`: Deletes website favicon.
- `deleteLogo`: Deletes website logo.
- `updateEmailSettings`: Updates email service settings.
- `testEmailSettings`: Tests email configuration.
- `getGoogleDriveAuthUrl`: Generates Google Drive OAuth URL.
- `handleGoogleDriveCallback`: Handles Google Drive OAuth callback.
- `revokeGoogleDriveToken`: Revokes Google Drive OAuth token.

### analyticsController.js
Handles video analytics tracking, statistics, and session management.

**Functions:**
- `trackEvent`: Tracks video viewing events and engagement metrics.
- `getVideoAnalytics`: Retrieves analytical data for a specific video with breakdowns by device, source, country, and quality.
- `getAnalyticsSummary`: Gets analytical summary for all user videos with top videos and engagement metrics.
- `generateSession`: Generates a unique analytics session ID.

### adminController.js
Handles administrative operations including user management, system settings, and storage management.

**Functions:**
- `getAllSettings`: Gets all admin system settings.
- `updateWebsiteSettings`: Updates website configuration.
- `updateFFmpegSettings`: Updates FFmpeg processing settings.
- `updateS3Settings`: Updates S3 storage settings.
- `updateRedisSettings`: Updates Redis configuration.
- `updateRateLimitSettings`: Updates rate limiting configuration.
- `updateCorsSettings`: Updates CORS settings.
- `updateAnalyticsSettings`: Updates analytics configuration.
- `updateSecuritySettings`: Updates security settings.
- `updateEmailSettings`: Updates email settings.
- `getGoogleDriveSettings`: Gets Google Drive settings.
- `updateGoogleDriveSettings`: Updates Google Drive settings.
- `uploadFavicon`: Uploads admin favicon.
- `getFavicon`: Serves website favicon from local or S3 storage.
- `deleteFavicon`: Deletes admin favicon.
- `getUsers`: Retrieves list of all users with pagination and filtering.
- `getUser`: Gets detailed information about a specific user.
- `updateUser`: Updates user information.
- `toggleUserBan`: Bans/unbans a user account.
- `updateUserStorage`: Updates user storage limits.
- `deleteUser`: Deletes a user account and all associated data.
- `getStorageStats`: Gets overall storage statistics.
- `resetUserPassword`: Resets user password.
- `toggleUserAds`: Toggles ads for a user's videos.
- `adminGetUserVideos`: Gets all videos for a specific user.

### chunkUploadController.js
Handles chunked video upload functionality for large files.

**Functions:**
- `startChunkUpload`: Initializes a chunked upload session.
- `uploadChunk`: Uploads and saves an individual chunk.
- `completeChunkUpload`: Completes the chunked upload and adds to processing queue.
- `cancelChunkUpload`: Cancels and cleans up a chunked upload session.
- `getChunkUploadStatus`: Gets the current status of a chunked upload session.

### mediaController.js
Handles media-specific operations including watermarks, subtitles, and quality variants.

**Functions:**
- `addWatermark`: Adds watermark settings to a video with text or image support.
- `removeWatermark`: Removes watermark from a video.
- `addSubtitle`: Adds subtitle file to a video with S3 support.
- `removeSubtitle`: Removes subtitle from a video.
- `addQualityVariant`: Adds or updates video quality variant settings.
- `toggleAllQualities`: Enables/disables all quality variants.
- `getWatermark`: Gets watermark settings for a video.
- `reprocessVideo`: Re-processes video with current settings.

### publicController.js
Handles public API endpoints for website information and video access.

**Functions:**
- `getPublicWebsiteInfo`: Gets public website information from admin settings.
- `getPublicVideo`: Gets public video information with proper URL generation.
- `getPublicVideoStatus`: Gets public video processing status.

### embedController.js
Handles video embedding functionality and display.

**Functions:**
- `renderEmbed`: Renders the embedded video player page with proper URL generation.
- `getEmbedCode`: Gets embed code for a video.

### backupController.js
Handles database backup and restore operations.

**Functions:**
- `createBackup`: Creates a database backup in SQL or JSON format.
- `restoreBackup`: Restores database from a backup file.
- `listBackups`: Lists all available backup files.
- `downloadBackup`: Downloads a backup file.
- `deleteBackup`: Deletes a backup file.
- `uploadAndRestore`: Uploads and restores from a backup file.

### backupMonitoringController.js
Handles backup system monitoring and verification.

**Functions:**
- `getBackupStatus`: Gets backup system status.
- `getBackupStats`: Gets detailed backup statistics.
- `verifyBackup`: Verifies a specific backup file.
- `verifyAllBackups`: Verifies all backup files.
- `getBackupFileStats`: Gets detailed statistics for a specific backup file.
- `cleanupBackups`: Runs manual backup cleanup.
- `getManagementStatus`: Gets backup management status.
- `triggerBackup`: Triggers a manual backup.

# SQL Queries Documentation

## Overview
This section documents direct SQL queries used in the HLS Video Converter API. Most database operations are handled through Sequelize ORM, but some direct queries are used for specific operations.

## Direct SQL Queries

### Migration: Create System User (`migrations/20250125-create-system-user.js`)

**SELECT Query:**
```sql
SELECT id FROM users WHERE id = '00000000-0000-0000-0000-000000000000'
```
Purpose: Check if the system user already exists before creating it.

**INSERT Query:**
```sql
INSERT INTO users (id, username, email, password, role, "storageUsed", "storageLimit", "isActive", "createdAt", "updatedAt")
VALUES (
  '00000000-0000-0000-0000-000000000000',
  'system',
  'system@internal.local',
  '[hashed_password]',
  'admin',
  0,
  0,
  false,
  NOW(),
  NOW()
)
```
Purpose: Create a special system user with admin role for internal settings management.

**DELETE Query:**
```sql
DELETE FROM users WHERE id = '00000000-0000-0000-0000-000000000000'
```
Purpose: Remove the system user during migration rollback.

### Migration: Add Processing Phase (`migrations/20250130-add-processing-phase.js`)

**CREATE TYPE Query:**
```sql
DO $$ BEGIN
  CREATE TYPE "enum_videos_processingPhase" AS ENUM ('pending', 'downloading', 'converting', 'completed', 'failed');
EXCEPTION
  WHEN duplicate_object THEN null;
END $$;
```
Purpose: Create a custom ENUM type for video processing phases.

**DROP TYPE Query:**
```sql
DROP TYPE IF EXISTS "enum_videos_processingPhase";
```
Purpose: Remove the ENUM type during migration rollback.

### Migration: Add Google Drive Upload Type (`migrations/20250125-add-googledrive-upload-type.js`)

**ALTER TYPE Query:**
```sql
ALTER TYPE "enum_videos_uploadType" ADD VALUE IF NOT EXISTS 'googledrive';
```
Purpose: Add 'googledrive' as a new value to the existing upload type ENUM.

### Backup Service (`services/backupService.js`)

**Connection Termination Query:**
```sql
SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = '[database_name]'
AND pid <> pg_backend_pid();
```
Purpose: Terminate active database connections before restoring a backup (allows the restore process to proceed).

**Table Listing Query:**
```sql
SELECT tablename FROM pg_tables
WHERE schemaname = 'public'
AND tablename != 'SequelizeMeta'
```
Purpose: Get all user table names for JSON backup export, excluding the Sequelize migration tracking table.

**Data Export Query:**
```sql
SELECT * FROM "[tablename]"
```
Purpose: Export all data from a specific table during JSON backup creation.

**Data Truncation Query:**
```sql
TRUNCATE TABLE "[tablename]" CASCADE
```
Purpose: Clear all data from a table during JSON backup restore before inserting new data.

**Data Insertion Query:**
```sql
INSERT INTO "[tablename]" ("[column1]", "[column2]", ...)
VALUES ([value1], [value2], ...)
```
Purpose: Insert data into a table during JSON backup restore.

**Session Replication Role Queries:**
```sql
SET session_replication_role = replica;
```
Purpose: Temporarily disable foreign key checks during backup restore to avoid constraint violations.

```sql
SET session_replication_role = DEFAULT;
```
Purpose: Re-enable foreign key checks after backup restore is complete.