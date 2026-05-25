# Ratepoint System - Production API Documentation

**Version:** 2.0 (Enterprise)  
**Last Updated:** May 2026  
**Status:** Production-Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Authentication](#authentication)
4. [Security Best Practices](#security-best-practices)
5. [API Endpoints](#api-endpoints)
6. [Pagination](#pagination)
7. [Validation & Error Handling](#validation--error-handling)
8. [Rate Limiting](#rate-limiting)
9. [Response Format](#response-format)
10. [Offline-First Strategy](#offline-first-strategy)
11. [Real-Time Features](#real-time-features)
12. [Mobile Best Practices](#mobile-best-practices)
13. [Deployment & Scaling](#deployment--scaling)

---

## Overview

**Ratepoint System** is a municipal tax/revenue collection platform designed for semi-connected field operations in Africa. The API supports:

- 🔐 **Secure authentication** with refresh tokens
- 📱 **Offline-first mobile operations** with automatic sync
- 📍 **GPS-based field tracking** and geo-fencing
- 📊 **Real-time admin dashboards** with live monitoring
- 🌐 **Low-bandwidth operation** with efficient pagination
- 🔔 **Notification system** for agents and administrators

### Design Principles

✅ **Mobile-First:** Built for Flutter, optimized for 2G/3G networks  
✅ **Offline Resilience:** Works without internet; syncs when connected  
✅ **Security-First:** Token-based auth, encrypted payloads, role-based access  
✅ **Scalable:** Pagination, caching, optimized queries  
✅ **Auditable:** Complete logging of all transactions  

---

## Quick Start

### 1. Environment Setup

```bash
# PRODUCTION CONFIGURATION (use HTTPS always)
BASE_URL = https://revenue.municipality.gov.gh/api/v1
APP_ENV = production
API_VERSION = 2.0
```

### 2. Agent Login

```bash
curl -X POST https://revenue.municipality.gov.gh/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "agent@example.com",
    "password": "secure_password",
    "device_id": "device-uuid-123"
  }'
```

### 3. Use Access Token

```bash
curl -X GET https://revenue.municipality.gov.gh/api/v1/agent/businesses \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR..."
```

### 4. Refresh Token When Expired

```bash
curl -X POST https://revenue.municipality.gov.gh/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "refresh_token_string"
  }'
```

---

## Authentication

### Token Architecture

The API uses a **two-token system** for enterprise-grade security:

| Token Type | Purpose | Lifespan | Storage |
|-----------|---------|----------|---------|
| **Access Token** | API requests | 2 hours | RAM / Volatile |
| **Refresh Token** | Get new access token | 30 days | Secure Storage |
| **Device Token** | Device identification | 1 year | Device-specific |

### Token Generation & Storage

**Server generates on login:**
1. **Access Token** (JWT or opaque string) - Used for all API calls
2. **Refresh Token** - Stored securely in database
3. **Device Token** - Identifies device; prevents token sharing

**Mobile app stores securely:**
- Access Token → RAM only (cleared on app close)
- Refresh Token → Keychain/Secure Enclave (iOS) or EncryptedSharedPreferences (Android)
- Device Token → Secure storage

### 1. POST /auth/login

**Endpoint:** `POST /auth/login`  
**Authentication:** None  
**Rate Limit:** 5 attempts per 15 minutes per IP

**Request:**
```json
{
  "email": "agent@example.com",
  "password": "password123",
  "device_id": "device-uuid-123",
  "device_name": "Samsung Galaxy A12",
  "os_version": "11.0",
  "app_version": "1.2.3"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "agent@example.com",
      "role": "field_agent",
      "phone_number": "+233241234567",
      "zone": {
        "id": 2,
        "name": "Zone B"
      },
      "is_active": true,
      "permissions": ["collect_revenue", "view_assigned_businesses"]
    },
    "tokens": {
      "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
      "refresh_token": "refresh_token_long_string_here",
      "token_type": "Bearer",
      "expires_in": 7200,
      "device_token": "device-token-for-security"
    },
    "settings": {
      "require_gps_verification": true,
      "gps_threshold_meters": 500,
      "heartbeat_interval_seconds": 60,
      "offline_sync_batch_size": 50
    }
  }
}
```

**Validation Errors (422):**
```json
{
  "success": false,
  "error": "Validation error",
  "errors": {
    "email": ["Email is required", "Email must be valid format"],
    "password": ["Password is required"]
  }
}
```

**Error Cases:**
- 401: Invalid credentials
- 403: Account disabled or role not authorized for mobile
- 429: Too many login attempts
- 500: Server error

### 2. POST /auth/refresh

**Endpoint:** `POST /auth/refresh`  
**Authentication:** Refresh token  
**Rate Limit:** 10 per minute per device

**Purpose:** Get new access token before expiry or after expiration

**Request:**
```json
{
  "refresh_token": "refresh_token_string_here",
  "device_id": "device-uuid-123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "access_token": "new_access_token_jwt_here",
    "refresh_token": "new_refresh_token_here",
    "token_type": "Bearer",
    "expires_in": 7200
  }
}
```

**Error Cases:**
- 401: Invalid or expired refresh token
- 403: Device mismatch (token used on different device)
- 429: Rate limit exceeded

### 3. POST /auth/logout

**Endpoint:** `POST /auth/logout`  
**Authentication:** Required (Bearer token)

**Request:** Empty body

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Server Actions:**
- Revokes access token
- Revokes refresh token
- Logs logout event
- Clears agent location (marks offline)

### 4. GET /auth/me

**Endpoint:** `GET /auth/me`  
**Authentication:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "name": "John Doe",
    "email": "agent@example.com",
    "role": "field_agent",
    "phone_number": "+233241234567",
    "zone": { "id": 2, "name": "Zone B" },
    "is_active": true,
    "last_login_at": "2026-05-22T14:30:00Z",
    "last_login_device": "Samsung Galaxy A12"
  }
}
```

---

## Security Best Practices

### HTTPS/TLS (Mandatory)

```
ALL endpoints MUST use HTTPS with:
✅ TLS 1.2 minimum (TLS 1.3 preferred)
✅ Strong cipher suites
✅ Valid SSL certificate
✅ HSTS header: Strict-Transport-Security: max-age=31536000
```

### Access Token Security

```
✅ JWT format with RS256 algorithm (public/private key signing)
✅ Short lifespan: 2 hours maximum
✅ One-time use: Cannot reuse after refresh
✅ Signed payload prevents tampering
✅ Never sent in URL parameters
✅ Always sent in Authorization header only
```

### Refresh Token Security

```
✅ Opaque token (not JWT): Use random 64+ character string
✅ Hashed in database with argon2
✅ Device-binding: Token is specific to device_id
✅ Rotation: New refresh token issued with each access token refresh
✅ Revocation: Can be revoked immediately
✅ Stored only in secure device storage
```

### Mobile Storage Recommendations

| Platform | Access Token | Refresh Token | Device ID |
|----------|-------------|---------------|-----------|
| **iOS** | RAM only | iOS Keychain | Keychain |
| **Android** | RAM only | EncryptedSharedPreferences | SharedPreferences |
| **Web** | sessionStorage | httpOnly Cookie | localStorage |

### API Key Rotation

```
✅ Refresh tokens rotated every 30 days (user re-login)
✅ Access tokens rotated every 2 hours (automatic)
✅ Device tokens rotated every 90 days
✅ Service keys rotated every 6 months
```

### Suspicious Activity Detection

```
✅ Multiple failed logins from same IP → Block for 30 min
✅ Token used from different device → Revoke & force re-login
✅ Abnormal activity pattern → Flag in audit log
✅ GPS spoofing detected → Flag but allow (record suspicious)
✅ Impossible travel speed → Flag (location change in <5 min across far distances)
```

---

## API Endpoints

### Authentication Endpoints

| Method | Endpoint | Auth Required | Purpose |
|--------|----------|--------------|---------|
| POST | `/auth/login` | ❌ | Agent login |
| POST | `/auth/refresh` | ❌ | Get new access token |
| POST | `/auth/logout` | ✅ | Logout & revoke tokens |
| GET | `/auth/me` | ✅ | Get current user profile |

### Agent Endpoints

| Method | Endpoint | Auth Required | Purpose |
|--------|----------|--------------|---------|
| GET | `/agent/businesses` | ✅ | List assigned businesses |
| POST | `/agent/collections` | ✅ | Record collection |
| POST | `/agent/collections/bulk` | ✅ | Sync multiple collections |
| GET | `/agent/collections` | ✅ | View collection history |
| POST | `/agent/location` | ✅ | Update GPS location |
| POST | `/agent/heartbeat` | ✅ | Keep-alive ping |
| GET | `/agent/notifications` | ✅ | Fetch notifications |
| PATCH | `/agent/notifications/{id}/read` | ✅ | Mark notification as read |
| GET | `/agent/stats` | ✅ | Performance statistics |
| GET | `/agent/collections/{id}/receipt` | ✅ | Get collection receipt |
| GET | `/public/receipts/{receipt_number}/verify` | ❌ | Verify receipt authenticity |

### Admin Endpoints

| Method | Endpoint | Auth Required | Admin Only | Purpose |
|--------|----------|--------------|-----------|---------|
| GET | `/admin/agents/live` | ✅ | ✅ | Real-time agent status |
| GET | `/admin/agents/locations` | ✅ | ✅ | Agent GPS coordinates |
| GET | `/admin/collections/live` | ✅ | ✅ | Live collection feed |
| GET | `/admin/dashboard/stats` | ✅ | ✅ | Dashboard metrics |
| POST | `/admin/notifications/send` | ✅ | ✅ | Send notification |
| GET | `/admin/reports/revenue-trends` | ✅ | ✅ | Revenue trends |
| GET | `/admin/reports/zone-performance` | ✅ | ✅ | Zone analytics |
| GET | `/admin/reports/agent-rankings` | ✅ | ✅ | Agent rankings |
| GET | `/admin/suspicious-activities` | ✅ | ✅ | Suspicious activity log |
| POST | `/admin/reports/export` | ✅ | ✅ | Export data (CSV/PDF) |

---

## Pagination

Applied to endpoints that return lists of items that may grow large.

### Paginated Endpoints

- `GET /agent/businesses`
- `GET /agent/collections`
- `GET /admin/collections/live`
- `GET /agent/notifications`
- `GET /admin/agents/live`

### Query Parameters

```
?page=1           (current page, default 1)
?per_page=20      (items per page, default 20, max 100)
```

### Response Format

```json
{
  "success": true,
  "data": [
    { /* item 1 */ },
    { /* item 2 */ }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 487,
    "last_page": 25,
    "from": 1,
    "to": 20,
    "has_more": true
  },
  "links": {
    "first": "https://api.example.com/agent/businesses?page=1&per_page=20",
    "last": "https://api.example.com/agent/businesses?page=25&per_page=20",
    "next": "https://api.example.com/agent/businesses?page=2&per_page=20",
    "prev": null
  }
}
```

### Example Requests

```bash
# Get first page (default)
GET /agent/businesses?page=1&per_page=10

# Get specific page
GET /agent/businesses?page=3&per_page=20

# Efficient for mobile (small batch)
GET /agent/collections?page=1&per_page=5
```

---

## Validation & Error Handling

### Consistent Error Response Format

All validation errors follow this structure:

```json
{
  "success": false,
  "error": "Error type identifier",
  "message": "Human-readable error message",
  "errors": {
    "field_name": [
      "Error reason 1",
      "Error reason 2"
    ]
  },
  "request_id": "req_550e8400-e29b-41d4-a716-446655440000",
  "timestamp": "2026-05-23T15:30:00Z"
}
```

### Validation Error Examples

**Missing Required Fields (422):**
```json
{
  "success": false,
  "error": "Validation error",
  "message": "Validation failed for 2 fields",
  "errors": {
    "amount": ["Amount is required", "Amount must be greater than 0"],
    "gps_lat": ["GPS latitude is required"]
  }
}
```

**Invalid Data Type (422):**
```json
{
  "success": false,
  "error": "Validation error",
  "message": "Invalid data provided",
  "errors": {
    "amount": ["Amount must be a valid decimal number"],
    "battery_level": ["Battery level must be an integer between 0 and 100"]
  }
}
```

**Business Not Found (404):**
```json
{
  "success": false,
  "error": "Not found",
  "message": "Business with ID 999 not found in your zone",
  "request_id": "req_550e8400-e29b-41d4-a716-446655440000"
}
```

### Field Validation Rules

#### Amount Fields
- ✅ Decimal format: `150.50`
- ✅ Range: 0.01 to 999,999.99
- ✅ Precision: Max 2 decimal places
- ✅ Required for collection endpoints

#### GPS Coordinates
- ✅ Latitude: -90 to 90
- ✅ Longitude: -180 to 180
- ✅ Precision: At least 4 decimal places
- ✅ Required for location/collection endpoints

#### Timestamps
- ✅ ISO 8601 format: `2026-05-23T15:30:00Z`
- ✅ UTC timezone only
- ✅ No milliseconds required (but accepted)
- ✅ Cannot be in future

#### Offline Sync IDs
- ✅ Format: UUID v4 or unique string
- ✅ Length: 20-36 characters
- ✅ Used for deduplication
- ✅ Must be unique per device per collection

---

## Rate Limiting

### Rate Limit Rules

| Endpoint | Limit | Window | Purpose |
|----------|-------|--------|---------|
| `POST /auth/login` | 5 | 15 min | Prevent brute force |
| `POST /auth/refresh` | 10 | 1 min | Prevent token abuse |
| `POST /agent/heartbeat` | 60 | 1 min | Prevent spam (1/sec) |
| `POST /agent/location` | 30 | 1 min | Prevent location spam |
| `POST /agent/collections` | 100 | 5 min | Prevent collection spam |
| `POST /admin/notifications/send` | 50 | 1 min | Prevent notification spam |
| `GET /agent/collections` | 100 | 1 min | API abuse protection |
| Default (other GET) | 300 | 1 min | Standard protection |

### Rate Limit Headers

Every response includes these headers:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1685015400
```

### Rate Limit Exceeded Response (429)

```json
{
  "success": false,
  "error": "Too many requests",
  "message": "You have exceeded the rate limit. Please retry after 45 seconds.",
  "retry_after": 45
}
```

### Exponential Backoff Strategy

Mobile app should implement:

```
Retry 1: Wait 1 second
Retry 2: Wait 2 seconds
Retry 3: Wait 4 seconds
Retry 4: Wait 8 seconds
Retry 5: Wait 16 seconds
Max: Stop after 5 retries
```

---

## Response Format

### Standard Success Response

```json
{
  "success": true,
  "message": "Operation description",
  "data": {
    /* endpoint-specific payload */
  },
  "metadata": {
    "request_id": "req_550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2026-05-23T15:30:00Z",
    "version": "2.0"
  }
}
```

### Standard Error Response

```json
{
  "success": false,
  "error": "Error type",
  "message": "User-friendly error message",
  "errors": {},
  "metadata": {
    "request_id": "req_550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2026-05-23T15:30:00Z"
  }
}
```

### Money Format

All monetary values use **decimal format with 2 decimal places**:

```json
{
  "amount": 1500.50,        ✅ Correct
  "total": 2345.99,         ✅ Correct
  "fee_amount": 50.00       ✅ Correct
}
```

NOT:
```json
{
  "amount": 150050,          ❌ Wrong (integer cents)
  "total": "2345.99",        ❌ Wrong (string)
  "fee_amount": 50           ❌ Wrong (no decimals)
}
```

### Timestamp Format

All timestamps use **ISO 8601 UTC**:

```json
{
  "created_at": "2026-05-23T15:30:00Z",      ✅ Correct
  "last_login_at": "2026-05-23T14:15:30Z",   ✅ Correct
  "collected_at": "2026-05-23T10:00:00Z"     ✅ Correct
}
```

NOT:
```json
{
  "created_at": "2026-05-23 15:30:00",              ❌ Wrong format
  "last_login_at": "May 23, 2026 2:30 PM",         ❌ Wrong format
  "collected_at": 1685010000                        ❌ Wrong format (Unix)
}
```

---

## Offline-First Strategy

The mobile app is designed to work completely offline with automatic sync.

### Core Concept

1. **Collect data locally** → SQLite database on device
2. **Sync when connected** → Send batch to server
3. **Resolve conflicts** → Handle duplicates & updates
4. **Maintain offline state** → Local cache for offline mode

### Offline Sync System

#### Sync States

```
pending     - Not yet synced to server
syncing     - Currently in progress
synced      - Successfully synced
conflict    - Server rejected (duplicate/validation error)
failed      - Network error (will retry)
```

#### Deduplication (offline_sync_id)

Mobile app generates unique ID per collection:

```dart
String offlineSyncId = Uuid().v4();  // Device generates UUID
```

Server uses this to prevent duplicates:

```sql
SELECT * FROM collections 
WHERE offline_sync_id = ?
```

#### Bulk Sync Endpoint

**POST /agent/collections/bulk**

```json
{
  "collections": [
    {
      "business_id": 1,
      "amount": 150.50,
      "payment_method": "cash",
      "gps_lat": 5.60370,
      "gps_lng": -0.18700,
      "offline_sync_id": "550e8400-e29b-41d4-a716-446655440000",
      "collected_at": "2026-05-22T10:30:00Z",
      "sync_attempt": 1
    }
  ],
  "device_id": "device-uuid-123",
  "sync_id": "sync-batch-550e8400"
}
```

**Response (200):**

```json
{
  "success": true,
  "message": "Sync completed",
  "data": {
    "summary": {
      "total": 3,
      "synced": 2,
      "skipped": 1,
      "failed": 0
    },
    "results": [
      {
        "offline_sync_id": "550e8400-e29b-41d4-a716-446655440000",
        "status": "synced",
        "server_id": 101,
        "receipt_number": "REC-ABC123-20260522"
      },
      {
        "offline_sync_id": "660e8400-e29b-41d4-a716-446655440001",
        "status": "synced",
        "server_id": 102,
        "receipt_number": "REC-DEF456-20260522"
      },
      {
        "offline_sync_id": "770e8400-e29b-41d4-a716-446655440002",
        "status": "skipped",
        "reason": "duplicate",
        "message": "Collection already synced"
      }
    ]
  }
}
```

### Conflict Resolution

**Scenario 1: Duplicate Detection**
- Same `offline_sync_id` detected
- Response: Skip with `"duplicate"` reason
- Mobile app: Update local record with `synced` status

**Scenario 2: Validation Error**
- Server rejects due to invalid data
- Response: Include `error` in result
- Mobile app: Mark as `conflict`, notify user for manual fix

**Scenario 3: Network Timeout**
- Request doesn't complete
- Mobile app: Increment `sync_attempt`, retry with exponential backoff
- Max retries: 5 with 1, 2, 4, 8, 16 second delays

### Retry Strategy

```dart
Future<void> syncCollections() async {
  List<Collection> pending = await db.getPendingCollections();
  
  for (Collection collection in pending) {
    int retries = 0;
    bool synced = false;
    
    while (retries < 5 && !synced) {
      try {
        var response = await api.syncBulk([collection]);
        
        if (response.success) {
          await db.markSynced(collection.id, response.data.serverId);
          synced = true;
        } else {
          await db.markConflict(collection.id, response.error);
        }
      } catch (e) {
        retries++;
        await Future.delayed(Duration(seconds: pow(2, retries).toInt()));
      }
    }
    
    if (!synced && retries >= 5) {
      await db.markFailed(collection.id);
    }
  }
}
```

---

## Real-Time Features

### Recommended Architecture

For optimal performance in African networks, use **hybrid approach**:

| Feature | Method | Interval | Benefits |
|---------|--------|----------|----------|
| **Notifications** | Polling | 30 sec | Always completes, no overhead |
| **Live Agent Status** | Polling | 10 sec | Shows when agents come online |
| **Collection Feed** | Polling | 10-30 sec | Near real-time, low bandwidth |
| **Agent Location** | Polling | 10-15 sec | Works on 2G networks |

### Why Not WebSockets?

❌ Not ideal for mobile:
- High battery drain (persistent connection)
- Unreliable on unstable networks
- Difficult to implement offline-first
- Requires connection fallback anyway
- Firebase better for push notifications

### Polling Implementation

**Heartbeat (keep agent online):**
```bash
POST /agent/heartbeat every 60 seconds
Response includes: unread_notification_count
```

**Check for new notifications:**
```bash
GET /agent/notifications?unread_only=1 every 30 seconds
If count > 0, fetch full notification list
```

**Admin live tracking:**
```bash
GET /admin/agents/locations every 10-15 seconds
GET /admin/collections/live?since=last_sync_time every 30 seconds
```

### Recommended Infrastructure

**For real-time push notifications** (optional, tier 2):
- Google Cloud Messaging (GCM) for Android
- Apple Push Notification (APN) for iOS
- Firebase Cloud Messaging (FCM) as unified solution

**Backend sends notification:**
```php
// When collection synced, send to admin
$adminDeviceTokens = getAdminDeviceTokens();
foreach ($adminDeviceTokens as $token) {
    sendFirebasePush($token, [
        'title' => 'New Collection',
        'body' => "Agent collected $150 from ABC Store"
    ]);
}
```

---

## Mobile Best Practices

### Token Management

**On App Start:**
```dart
Future<void> initializeApp() {
  String? accessToken = await secureStorage.read(key: 'access_token');
  String? refreshToken = await secureStorage.read(key: 'refresh_token');
  DateTime? tokenExpiry = tokenManager.getExpiry();
  
  if (TokenManager.isExpired(tokenExpiry)) {
    // Try refresh
    await authService.refreshToken();
  } else if (accessToken != null) {
    // Use existing token
    httpClient.setAuthToken(accessToken);
  } else {
    // Navigate to login
    navigateTo(LoginPage);
  }
}
```

**Token Expiry Check:**
```dart
class TokenManager {
  static bool isExpired(DateTime? expiry) {
    if (expiry == null) return true;
    return DateTime.now().isAfter(expiry.subtract(Duration(minutes: 5)));
  }
  
  // Refresh 5 minutes before expiry
  static Duration timeUntilRefresh(DateTime expiry) {
    return expiry.difference(DateTime.now()).inSeconds > 300 
      ? Duration(minutes: 55)  // Refresh after 55 min of 60
      : Duration(minutes: 1);  // Check every minute if close
  }
}
```

### Offline SQLite Database

**Local schema mirrors server entities:**

```sql
CREATE TABLE collections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  business_id INTEGER NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method TEXT,
  gps_lat REAL,
  gps_lng REAL,
  offline_sync_id TEXT UNIQUE,
  collected_at TEXT,
  sync_status TEXT DEFAULT 'pending',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sync_status ON collections(sync_status);
CREATE INDEX idx_collected_at ON collections(collected_at);
```

**Local cache strategy:**

```dart
class CollectionRepository {
  // Always use local DB if data exists
  Future<List<Collection>> getCollections(DateTime date) async {
    List<Collection> local = await db.getCollections(date);
    
    // If online, sync in background
    if (connectivityService.isOnline()) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        syncCollectionsInBackground();
      });
    }
    
    return local;
  }
  
  // Sync only new data since last sync
  Future<void> syncCollectionsInBackground() async {
    DateTime lastSync = await db.getLastSyncTime();
    List<Collection> newCollections = 
      await api.getCollectionsSince(lastSync);
    
    await db.insertCollections(newCollections);
  }
}
```

### GPS Handling

**Permissions & Privacy:**
```dart
final location = Location();

// Request permission explicitly
PermissionStatus status = await location.requestPermission();
if (status == PermissionStatus.denied) {
  // Show explaining dialog
  showDialog(title: 'GPS Required', message: 'GPS is needed to verify your location during collections');
}
```

**Efficient GPS tracking:**
```dart
class LocationService {
  StreamSubscription<LocationData>? _locationSub;
  
  // Only update location when agent is actively collecting
  void startLocationTracking() {
    _locationSub = location.onLocationChanged.listen((LocationData event) {
      updateLocation(event.latitude!, event.longitude!, event.accuracy!);
    });
  }
  
  void stopLocationTracking() {
    _locationSub?.cancel();  // Saves battery
  }
  
  // Log location only when needed
  Future<void> updateLocation(double lat, double lng, double accuracy) async {
    // Don't send to server every second
    Duration timeSince = DateTime.now().difference(_lastLocationUpdate);
    if (timeSince.inSeconds < 10) return;
    
    await api.updateLocation(lat, lng, accuracy);
    _lastLocationUpdate = DateTime.now();
  }
}
```

### Battery Optimization

```dart
class BatteryOptimizer {
  // Reduce polling frequency when battery low
  Duration getHeartbeatInterval() {
    int batteryLevel = await _getBatteryLevel();
    
    if (batteryLevel < 10) return Duration(minutes: 2);
    if (batteryLevel < 25) return Duration(minutes: 1);
    if (batteryLevel < 50) return Duration(seconds: 45);
    return Duration(seconds: 30);
  }
  
  // Dim screen, reduce animations when low battery
  void activateLowBatteryMode() {
    // Disable background sync
    // Disable GPS polling
    // Reduce UI refresh rate
    // Request user to save before app close
  }
}
```

### Error Handling & Recovery

```dart
class APIService {
  Future<T> makeRequest<T>(
    String method,
    String endpoint,
    Future<T> Function() request,
  ) async {
    int retries = 0;
    const maxRetries = 5;
    
    while (retries < maxRetries) {
      try {
        return await request();
      } on TokenExpiredException {
        // Handle token refresh
        await refreshToken();
        retries++;
      } on ConnectivityException {
        // Offline: queue request
        await requestQueue.add(RequestQueueItem(
          method: method,
          endpoint: endpoint,
          timestamp: DateTime.now()
        ));
        throw 'Request queued for sync';
      } on ServerException catch (e) {
        if (e.statusCode == 429) {
          // Rate limited: wait and retry
          await Future.delayed(Duration(seconds: pow(2, retries)));
          retries++;
        } else {
          // Other error: don't retry
          rethrow;
        }
      }
    }
    
    throw 'Max retries exceeded';
  }
}
```

---

## Deployment & Scaling

### Database Optimization

#### Indexing Strategy

```sql
-- Agent endpoints
CREATE INDEX idx_users_email_password ON users(email, password);
CREATE INDEX idx_users_zone_id ON users(zone_id);
CREATE INDEX idx_users_role ON users(role);

-- Collection tracking
CREATE INDEX idx_collections_agent_date 
  ON collections(agent_id, DATE(collected_at));
CREATE INDEX idx_collections_business_id 
  ON collections(business_id);
CREATE INDEX idx_collections_offline_sync_id 
  ON collections(offline_sync_id);

-- Location tracking
CREATE INDEX idx_agent_locations_agent_id 
  ON agent_locations(agent_id);
CREATE INDEX idx_agent_locations_last_seen 
  ON agent_locations(last_seen_at);

-- Performance queries
CREATE INDEX idx_collections_created_at 
  ON collections(created_at);
CREATE INDEX idx_notifications_recipient 
  ON notifications(recipient_id, read_at);
```

#### Query Optimization

```php
// ❌ BAD: N+1 problem
$agents = Agent::all();
foreach ($agents as $agent) {
    $collections = Collection::where('agent_id', $agent->id)->get();  // Query per agent!
}

// ✅ GOOD: Eager loading
$agents = Agent::with('collections')  // One query + one JOIN query
    ->where('is_active', true)
    ->get();
```

### Caching Strategy

```php
// Cache expensive queries
Cache::remember('agent_stats:' . $agentId, 300, function () {
    return Collection::where('agent_id', $agentId)
        ->whereBetween('collected_at', [today(), today()->endOfDay()])
        ->sum('amount');
});

// Clear cache on collection insert
event(new CollectionCreated($collection));
Cache::forget('agent_stats:' . $collection->agent_id);
```

### Scaling Architecture

#### Level 1: Small Deployment (< 100 agents)
```
┌─────────┐
│ HTTPS   │
│ LB      │
└────┬────┘
     │
┌────┴──────┐
│ PHP API   │
│ Server    │
└────┬──────┘
     │
  ┌──┴──────┐
  │ MySQL   │
  │ DB      │
  └─────────┘
```

#### Level 2: Growing Deployment (100 - 1000 agents)
```
┌──────────────┐
│ HTTPS Load   │
│ Balancer     │
└────┬─────────┘
     │
┌────┴────┬─────────┐
│ API 1    │ API 2   │
│ (PHP)    │ (PHP)   │
└────┬────┬────┬────┘
     │    │    │
     ├────┼────┐
     │    │    │
┌────┴────┴────┴────┐
│ Primary MySQL      │
├────────────────────┤
│ Replication to     │
│ Replica DB for    │
│ Reads             │
└────────────────────┘

Redis Cache Layer
├─ Token cache
├─ Session cache
├─ Query result cache
└─ Rate limit counters
```

#### Level 3: Enterprise Deployment (1000+ agents)
```
CDN (Static Assets)
      │
HTTPS LB (Geographic)
      │
┌─────┴─────┬─────┬─────┐
│ API Region 1    │ API Region 2 │
│ ┌────┬────┐     │ ┌────┬────┐     │
│ │ φ  │ φ  │     │ │ φ  │ φ  │     │
│ └────┴────┘     │ └────┴────┘     │
└─────┬──────────┴─────┬──────────┘
      │                │
┌─────┴────┐      ┌────┴─────┐
│ Primary  │◄────►│ Replica  │
│ MySQL    │      │ MySQL    │
└──────────┘      └──────────┘

Distributed Cache
├─ Redis Cluster
├─ Memcached nodes
└─ Session store

Queue System
├─ Notification queues
├─ Report generation
└─ Async processing

Monitoring
├─ ELK stack (logs)
├─ DataDog/New Relic (APM)
├─ Prometheus (metrics)
└─ Alert system
```

### Monitoring & Logging

```php
// Structured logging
Log::info('Collection recorded', [
    'agent_id' => $agent->id,
    'business_id' => $business->id,
    'amount' => $amount,
    'gps_distance' => $distance,
    'request_id' => $requestId,
    'timestamp' => now()->toIso8601String(),
]);

// Performance monitoring
\DB::listen(function ($query) {
    if ($query->time > 1000) {  // > 1 second
        alert('Slow query detected: ' . $query->sql);
    }
});
```

### Backup & Disaster Recovery

```
Daily Full Backup → AWS S3 (encrypted)
Hourly Incremental → Offsite
Transaction Log → Continuous replication
Point-in-time Recovery: 30 days
RTO: 1 hour
RPO: 5 minutes
```

### Recommended Tools

| Category | Tool | Purpose |
|----------|------|---------|
| **Load Balancing** | NGINX/HAProxy | Traffic distribution |
| **Caching** | Redis | Session & query cache |
| **Queue** | Supervisor + Laravel Queue | Async jobs |
| **Logging** | ELK Stack | Centralized logs |
| **Monitoring** | Prometheus + Grafana | Metrics & alerts |
| **APM** | DataDog/New Relic | Performance tracking |
| **CDN** | CloudFlare | Static asset delivery |
| **Backup** | AWS S3 + Glacier | Data protection |

---

## Advanced Endpoint Examples

### Get Collection Receipt

**GET /agent/collections/{id}/receipt**

```json
{
  "success": true,
  "data": {
    "receipt": {
      "id": 101,
      "receipt_number": "REC-ABC123-20260522",
      "qr_code": "data:image/png;base64,...",
      "amount": 150.50,
      "currency": "GHS",
      "business": {
        "name": "ABC Store",
        "address": "123 Main Street",
        "identifier": "BID-001"
      },
      "agent": {
        "name": "John Doe",
        "phone": "+233241234567"
      },
      "payment_method": "cash",
      "collected_at": "2026-05-22T10:30:00Z",
      "verification_url": "https://revenue.municipality.gov.gh/verify/REC-ABC123-20260522"
    }
  }
}
```

### Verify Receipt

**GET /public/receipts/{receipt_number}/verify**

```json
{
  "success": true,
  "data": {
    "valid": true,
    "receipt_number": "REC-ABC123-20260522",
    "business_name": "ABC Store",
    "amount": 150.50,
    "collected_at": "2026-05-22T10:30:00Z",
    "agent_name": "John Doe",
    "status": "confirmed"
  }
}
```

### Revenue Trends

**GET /admin/reports/revenue-trends?period=month**

```json
{
  "success": true,
  "data": {
    "period": "May 2026",
    "total_revenue": 125000.00,
    "average_per_day": 4032.26,
    "daily_breakdown": [
      {
        "date": "2026-05-01",
        "revenue": 3500.00,
        "collections": 28,
        "avg_amount": 125.00
      }
    ],
    "trend": "📈 +15% vs April",
    "forecast_eom": 135000.00
  }
}
```

---

## Support & Maintenance

### API Versioning

Current Version: **2.0** (Production-Ready)

Breaking Changes:
- Will be announced 60 days in advance
- Deprecated endpoints supported for 1 year
- New version path: `/api/v3/`

### Contact & Support

- **Email:** api-support@municipality.gov.gh
- **Slack:** #ratepoint-api-support
- **Status Page:** status.municipality.gov.gh
- **Documentation:** docs.municipality.gov.gh

---

**Last Updated:** May 2026  
**Next Review:** August 2026
