# Admin Controller Documentation

## Overview
This controller handles administrative functions including settings management, user management, and storage statistics.

## Functions and Endpoints

### Settings Management

#### 1. getAllSettings
- **Description**: Get all admin settings
- **Endpoint**: `GET /api/admin/settings`
- **Access**: Admin

#### 2. updateWebsiteSettings
- **Description**: Update website settings
- **Endpoint**: `PUT /api/admin/settings/website`
- **Access**: Admin

#### 3. updateFFmpegSettings
- **Description**: Update FFmpeg settings
- **Endpoint**: `PUT /api/admin/settings/ffmpeg`
- **Access**: Admin

#### 4. updateS3Settings
- **Description**: Update S3 storage settings
- **Endpoint**: `PUT /api/admin/settings/s3`
- **Access**: Admin

#### 5. updateRedisSettings
- **Description**: Update Redis settings
- **Endpoint**: `PUT /api/admin/settings/redis`
- **Access**: Admin

#### 6. updateRateLimitSettings
- **Description**: Update rate limiting settings
- **Endpoint**: `PUT /api/admin/settings/ratelimit`
- **Access**: Admin

#### 7. updateCorsSettings
- **Description**: Update CORS settings
- **Endpoint**: `PUT /api/admin/settings/cors`
- **Access**: Admin

#### 8. updateAnalyticsSettings
- **Description**: Update analytics settings
- **Endpoint**: `PUT /api/admin/settings/analytics`
- **Access**: Admin

#### 9. updateSecuritySettings
- **Description**: Update security settings
- **Endpoint**: `PUT /api/admin/settings/security`
- **Access**: Admin

#### 10. updateEmailSettings
- **Description**: Update email settings
- **Endpoint**: `PUT /api/admin/settings/email`
- **Access**: Admin

#### 11. getGoogleDriveSettings
- **Description**: Get Google Drive settings (admin level)
- **Endpoint**: `GET /api/admin/settings/googledrive`
- **Access**: Admin

#### 12. updateGoogleDriveSettings
- **Description**: Update Google Drive settings (admin level)
- **Endpoint**: `PUT /api/admin/settings/googledrive`
- **Access**: Admin

### Favicon Management

#### 13. uploadFavicon
- **Description**: Upload favicon
- **Endpoint**: `POST /api/admin/settings/favicon`
- **Access**: Admin

#### 14. getFavicon
- **Description**: Get favicon
- **Endpoint**: `GET /api/admin/favicon`
- **Access**: Public

#### 15. deleteFavicon
- **Description**: Delete favicon
- **Endpoint**: `DELETE /api/admin/settings/favicon`
- **Access**: Admin

### User Management

#### 16. getUsers
- **Description**: Get all users with pagination and filtering
- **Endpoint**: `GET /api/admin/users`
- **Access**: Admin

#### 17. getUser
- **Description**: Get single user details
- **Endpoint**: `GET /api/admin/users/:id`
- **Access**: Admin

#### 18. updateUser
- **Description**: Update user (admin)
- **Endpoint**: `PUT /api/admin/users/:id`
- **Access**: Admin

#### 19. toggleUserBan
- **Description**: Ban/Unban user
- **Endpoint**: `PUT /api/admin/users/:id/ban`
- **Access**: Admin

#### 20. updateUserStorage
- **Description**: Update user storage limit
- **Endpoint**: `PUT /api/admin/users/:id/storage`
- **Access**: Admin

#### 21. deleteUser
- **Description**: Delete user and all their data
- **Endpoint**: `DELETE /api/admin/users/:id`
- **Access**: Admin

#### 22. resetUserPassword
- **Description**: Reset user password (admin)
- **Endpoint**: `PUT /api/admin/users/:id/reset-password`
- **Access**: Admin

#### 23. toggleUserAds
- **Description**: Toggle ads for user (enable/disable ads on all user's videos)
- **Endpoint**: `PUT /api/admin/users/:id/ads`
- **Access**: Admin

#### 24. adminGetUserVideos
- **Description**: Get all videos for a specific user (admin)
- **Endpoint**: `GET /api/admin/videos`
- **Access**: Admin

### Storage Management

#### 25. getStorageStats
- **Description**: Get storage statistics
- **Endpoint**: `GET /api/admin/storage`
- **Access**: Admin

## Helper Functions

### getAdminSettings(userId)
- **Purpose**: Gets or creates admin settings for a specific admin user
- **Parameters**: userId - The ID of the admin user