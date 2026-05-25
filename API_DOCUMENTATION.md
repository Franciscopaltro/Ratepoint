# Ratepoint Mobile API v1 Documentation

**Base URL:** `http://<your-server>/RatepointSystem/api/v1/`

**API Version:** 1.0  
**Last Updated:** May 2026

---

## Table of Contents
1. [Authentication](#authentication)
2. [Base Response Format](#base-response-format)
3. [Public Endpoints](#public-endpoints)
4. [Agent Endpoints](#agent-endpoints)
5. [Admin Endpoints](#admin-endpoints)
6. [Error Handling](#error-handling)
7. [Request Examples](#request-examples)

---

## Authentication

### Authorization Header
All authenticated endpoints require the `Authorization` header with a Bearer token:

```
Authorization: Bearer <your-api-token>
```

### Token Validity
- **Expiration:** 30 days from issue
- **Revocation:** Tokens are deleted upon logout
- **Expired Token Response:** HTTP 401 with message "Token expired"

### Role-Based Access
- **field_agent / agent:** Mobile app access (field collection)
- **super_admin / finance_officer / supervisor:** Admin dashboard access
- **Other roles:** Cannot access mobile API

---

## Base Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* endpoint-specific data */ }
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error type",
  "message": "Detailed error message"
}
```

### HTTP Status Codes
- **200:** Success (GET, PATCH)
- **201:** Created (POST)
- **400:** Bad request
- **401:** Unauthenticated
- **403:** Forbidden / Insufficient permissions
- **404:** Not found
- **422:** Validation error
- **500:** Server error

---

## Public Endpoints

### 1. POST /auth/login
**Authentication:** None  
**Description:** Authenticate user and receive API token

**Request:**
```json
{
  "email": "agent@example.com",
  "password": "password123"
}
```

**Response (201):**
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
      "is_active": true
    },
    "token": "a1b2c3d4e5f6g7h8...",
    "token_type": "Bearer",
    "expires_at": "2026-06-22T10:30:00Z"
  }
}
```

**Errors:**
- 401: Invalid email or password
- 403: Account disabled
- 403: Only field agents can log in via the mobile app

---

## Agent Endpoints
All agent endpoints require authentication: `Authorization: Bearer <token>`

### 2. POST /auth/logout
**Description:** Revoke the current API token

**Request:** Empty body (no JSON required)

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully. Token has been revoked."
}
```

---

### 3. GET /auth/me
**Description:** Get current authenticated user profile

**Request:** No parameters

**Response (200):**
```json
{
  "success": true,
  "data": {
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
    "last_login_at": "2026-05-22T14:30:00Z"
  }
}
```

---

### 4. GET /agent/businesses
**Description:** Get businesses assigned to agent's zone with optional status filter

**Query Parameters:**
- `status` (optional): Filter by status - `paid`, `unpaid`, `pending`

**Request Examples:**
```
GET /agent/businesses
GET /agent/businesses?status=unpaid
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "ABC Store",
      "owner_name": "Mr. Mensah",
      "gps_lat": 5.60370,
      "gps_lng": -0.18700,
      "zone_id": 2,
      "zone_name": "Zone B",
      "structure_type": "Shop",
      "levy_type": "Monthly",
      "fee_amount": 150.00,
      "status": "unpaid"
    },
    {
      "id": 2,
      "name": "XYZ Market",
      "owner_name": "Mrs. Akosua",
      "gps_lat": 5.60500,
      "gps_lng": -0.18900,
      "zone_id": 2,
      "zone_name": "Zone B",
      "structure_type": "Market Stall",
      "levy_type": "Weekly",
      "fee_amount": 50.00,
      "status": "paid"
    }
  ],
  "count": 2
}
```

---

### 5. GET /agent/collections
**Description:** Get agent's collection history

**Query Parameters:**
- `date` (optional): Filter by date (YYYY-MM-DD), defaults to today
- `limit` (optional): Max 200, default 50

**Request Examples:**
```
GET /agent/collections
GET /agent/collections?date=2026-05-22&limit=20
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "business_id": 1,
      "business_name": "ABC Store",
      "owner_name": "Mr. Mensah",
      "amount": 150.00,
      "payment_method": "cash",
      "receipt_number": "REC-A1B2C3D4-20260522",
      "gps_lat": 5.60370,
      "gps_lng": -0.18700,
      "offline_sync_id": "uuid-123-456",
      "collected_at": "2026-05-22T10:30:00Z"
    }
  ],
  "count": 1,
  "date": "2026-05-22"
}
```

---

### 6. POST /agent/collections
**Description:** Record a single revenue collection (online or offline)

**Request Body:**
```json
{
  "business_id": 1,
  "amount": 150.00,
  "payment_method": "cash",
  "gps_lat": 5.60370,
  "gps_lng": -0.18700,
  "collected_at": "2026-05-22T10:30:00Z",
  "offline_sync_id": "unique-local-id-123"
}
```

**Field Details:**
- `business_id` (required): ID of business paying
- `amount` (required): Amount collected
- `payment_method` (optional): `cash`, `mobile_money`, `check` (default: `cash`)
- `gps_lat`, `gps_lng` (required): GPS coordinates where collection occurred
- `collected_at` (required): ISO timestamp of collection
- `offline_sync_id` (optional): Unique ID for offline deduplication

**Response (201):**
```json
{
  "success": true,
  "message": "Collection recorded successfully.",
  "data": {
    "collection_id": 101,
    "receipt_number": "REC-A1B2C3D4-20260522",
    "amount": 150.00,
    "business_name": "ABC Store",
    "geo_flagged": false,
    "collected_at": "2026-05-22T10:30:00Z"
  }
}
```

**Geo-Fencing Validation:**
- Collections within **500 meters** of business GPS: Accepted
- Collections **>500 meters** away: Flagged as suspicious activity (still recorded)
- Response includes `"geo_flagged": true/false`

**Duplicate Detection:**
- If `offline_sync_id` already exists, returns duplicate response without re-inserting

---

### 7. POST /agent/collections/bulk
**Description:** Sync multiple offline collections at once (for offline-first apps)

**Request Body:**
```json
{
  "collections": [
    {
      "business_id": 1,
      "amount": 150.00,
      "payment_method": "cash",
      "gps_lat": 5.60370,
      "gps_lng": -0.18700,
      "offline_sync_id": "offline-uuid-1",
      "collected_at": "2026-05-22T10:30:00Z"
    },
    {
      "business_id": 2,
      "amount": 50.00,
      "payment_method": "cash",
      "gps_lat": 5.60500,
      "gps_lng": -0.18900,
      "offline_sync_id": "offline-uuid-2",
      "collected_at": "2026-05-22T11:00:00Z"
    }
  ]
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Bulk sync complete: 2 synced, 0 skipped, 0 failed.",
  "summary": {
    "total": 2,
    "synced": 2,
    "skipped": 0,
    "failed": 0
  },
  "results": [
    {
      "index": 0,
      "status": "synced",
      "receipt_number": "REC-X1Y2Z3-20260522",
      "collection_id": 101
    },
    {
      "index": 1,
      "status": "synced",
      "receipt_number": "REC-A9B8C7-20260522",
      "collection_id": 102
    }
  ]
}
```

**Result Statuses:**
- `synced`: Successfully recorded
- `skipped`: Already synced (duplicate `offline_sync_id`)
- `failed`: Validation error or server error

---

### 8. POST /agent/location
**Description:** Update agent's live GPS position for admin tracking

**Request Body:**
```json
{
  "latitude": 5.60370,
  "longitude": -0.18700,
  "accuracy": 12.5,
  "battery_level": 78
}
```

**Field Details:**
- `latitude`, `longitude` (required): Current GPS coordinates
- `accuracy` (optional): GPS accuracy in meters
- `battery_level` (optional): Device battery percentage (0-100)

**Response (200):**
```json
{
  "success": true,
  "message": "Location updated."
}
```

---

### 9. POST /agent/heartbeat
**Description:** Lightweight ping to keep agent marked as online (send every 30-60 seconds)

**Request Body (optional):**
```json
{
  "battery_level": 65
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Heartbeat received.",
  "server_time": "2026-05-22T15:30:00Z",
  "unread_notifications": 2
}
```

---

### 10. GET /agent/notifications
**Description:** Fetch notifications for the agent

**Query Parameters:**
- `unread_only` (optional): Set to 1 to show only unread
- `limit` (optional): Max 100, default 20

**Request Examples:**
```
GET /agent/notifications
GET /agent/notifications?unread_only=1&limit=10
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "title": "Daily Target Reminder",
      "message": "Remember to collect from at least 5 businesses today.",
      "type": "info",
      "priority": "normal",
      "sender_name": "Supervisor Admin",
      "data": null,
      "is_read": false,
      "created_at": "2026-05-22T09:00:00Z"
    },
    {
      "id": 4,
      "title": "Urgent: New Business",
      "message": "New business added to your zone. Visit for initial assessment.",
      "type": "alert",
      "priority": "high",
      "sender_name": "Finance Officer",
      "data": { "business_id": 10 },
      "is_read": true,
      "created_at": "2026-05-21T14:30:00Z"
    }
  ],
  "count": 2
}
```

**Notification Types:**
- `info`: General information
- `warning`: Warning message
- `alert`: Urgent alert
- `task`: Task assignment
- `broadcast`: Broadcast message to all agents

**Priority Levels:**
- `low`, `normal`, `high`, `urgent`

---

### 11. PATCH /agent/notifications/read
**Description:** Mark notifications as read

**Request Body (one of):**
```json
{
  "notification_ids": [1, 2, 3, 4]
}
```

Or mark all as read:
```json
{
  "mark_all": true
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Notifications marked as read."
}
```

---

### 12. GET /agent/stats
**Description:** Get agent's performance statistics

**Request:** No parameters

**Response (200):**
```json
{
  "success": true,
  "data": {
    "today": {
      "collections": 8,
      "total": 950.00
    },
    "this_week": {
      "collections": 35,
      "total": 4200.00
    },
    "this_month": {
      "collections": 120,
      "total": 15000.00
    },
    "all_time": {
      "collections": 450,
      "total": 52500.00
    }
  }
}
```

---

## Admin Endpoints
All admin endpoints require authentication with admin role (`super_admin`, `finance_officer`, or `supervisor`)

### 13. GET /admin/agents/live
**Description:** Real-time status of all field agents

**Request:** No parameters

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "phone_number": "+233241234567",
      "zone": {
        "id": 2,
        "name": "Zone B"
      },
      "is_active": true,
      "status": "online",
      "location": {
        "latitude": 5.60370,
        "longitude": -0.18700,
        "accuracy": 12.5,
        "battery_level": 78,
        "last_seen_at": "2026-05-22T15:28:00Z"
      },
      "today_stats": {
        "collections": 8,
        "amount": 950.00
      }
    }
  ],
  "summary": {
    "total": 12,
    "online": 8,
    "offline": 3,
    "never_connected": 1
  },
  "server_time": "2026-05-22T15:30:00Z"
}
```

**Agent Status Values:**
- `online`: Last heartbeat within 5 minutes
- `offline`: Last seen >5 minutes ago
- `never_connected`: No location data yet

---

### 14. GET /admin/agents/locations
**Description:** Lightweight GPS coordinates for all online agents (for map updates)

**Request:** No parameters

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "agent_id": 5,
      "agent_name": "John Doe",
      "latitude": 5.60370,
      "longitude": -0.18700,
      "accuracy": 12.5,
      "battery_level": 78,
      "is_online": true,
      "last_seen_at": "2026-05-22T15:28:00Z"
    }
  ],
  "count": 8,
  "server_time": "2026-05-22T15:30:00Z"
}
```

---

### 15. GET /admin/collections/live
**Description:** Real-time collection feed across all agents

**Query Parameters:**
- `since` (optional): ISO timestamp, returns only newer collections
- `limit` (optional): Max 200, default 50

**Request Examples:**
```
GET /admin/collections/live
GET /admin/collections/live?since=2026-05-22T10:00:00Z&limit=20
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "receipt_number": "REC-A1B2C3D4-20260522",
      "amount": 150.00,
      "payment_method": "cash",
      "business": {
        "id": 1,
        "name": "ABC Store",
        "owner_name": "Mr. Mensah"
      },
      "agent": {
        "id": 5,
        "name": "John Doe",
        "phone_number": "+233241234567"
      },
      "zone_name": "Zone B",
      "gps_lat": 5.60370,
      "gps_lng": -0.18700,
      "collected_at": "2026-05-22T10:30:00Z",
      "synced_at": "2026-05-22T10:35:00Z"
    }
  ],
  "count": 1,
  "server_time": "2026-05-22T15:30:00Z"
}
```

---

### 16. GET /admin/dashboard/stats
**Description:** Comprehensive dashboard statistics

**Request:** No parameters

**Response (200):**
```json
{
  "success": true,
  "data": {
    "revenue": {
      "total": 52500.00,
      "today": 950.00,
      "this_week": 4200.00,
      "this_month": 15000.00
    },
    "agents": {
      "total": 12,
      "active": 11,
      "online": 8
    },
    "collections": {
      "today_count": 35,
      "pending_recon": 5
    },
    "alerts": {
      "open_suspicious": 2
    },
    "top_agents_today": [
      {
        "id": 5,
        "name": "John Doe",
        "collections": 8,
        "amount": 950.00
      }
    ],
    "zone_revenue_today": [
      {
        "zone_id": 2,
        "zone_name": "Zone B",
        "amount": 950.00,
        "collections": 8
      }
    ]
  },
  "server_time": "2026-05-22T15:30:00Z"
}
```

---

### 17. POST /admin/notifications/send
**Description:** Send notification to agent(s)

**Request Body (send to specific agent):**
```json
{
  "recipient_id": 5,
  "title": "Daily Task",
  "message": "Please collect from Zone B today.",
  "type": "task",
  "priority": "high"
}
```

**Request Body (broadcast to all agents):**
```json
{
  "title": "System Maintenance",
  "message": "Server maintenance scheduled for tonight. Sync all data before 8 PM.",
  "type": "broadcast",
  "priority": "urgent"
}
```

**Field Details:**
- `recipient_id` (optional): Specific agent ID. Omit for broadcast
- `title` (required): Notification title
- `message` (required): Notification message
- `type` (required): `info`, `warning`, `alert`, `task`, `broadcast`
- `priority` (required): `low`, `normal`, `high`, `urgent`

**Response (201):**
```json
{
  "success": true,
  "message": "Notification sent to agent."
}
```

Or for broadcast:
```json
{
  "success": true,
  "message": "Notification broadcast to all field agents."
}
```

---

## Error Handling

### Common Error Responses

**401 Unauthenticated:**
```json
{
  "success": false,
  "error": "Unauthenticated",
  "message": "Missing or invalid Authorization header. Use: Bearer <token>",
  "code": 401
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "error": "Forbidden",
  "message": "Admin access required.",
  "code": 403
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "error": "Validation error",
  "message": "Missing required fields: amount, gps_lat",
  "code": 422
}
```

**404 Not Found:**
```json
{
  "success": false,
  "error": "Not found",
  "message": "Endpoint POST /invalid/route does not exist.",
  "code": 404
}
```

---

## Request Examples

### JavaScript (Fetch API)

**Login:**
```javascript
const response = await fetch('http://localhost/RatepointSystem/api/v1/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'agent@example.com',
    password: 'password123'
  })
});
const data = await response.json();
const token = data.data.token;
```

**Record Collection:**
```javascript
const response = await fetch('http://localhost/RatepointSystem/api/v1/agent/collections', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    business_id: 1,
    amount: 150.00,
    payment_method: 'cash',
    gps_lat: 5.60370,
    gps_lng: -0.18700,
    collected_at: new Date().toISOString(),
    offline_sync_id: 'unique-id-' + Date.now()
  })
});
const result = await response.json();
```

**Send Heartbeat:**
```javascript
const response = await fetch('http://localhost/RatepointSystem/api/v1/agent/heartbeat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    battery_level: 75
  })
});
```

### Flutter/Dart Example

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

const String baseUrl = 'http://localhost/RatepointSystem/api/v1';
String? token;

// Login
Future<void> login(String email, String password) async {
  final response = await http.post(
    Uri.parse('$baseUrl/auth/login'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({'email': email, 'password': password}),
  );
  
  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    token = data['data']['token'];
  }
}

// Record Collection
Future<void> recordCollection(
  int businessId,
  double amount,
  double latitude,
  double longitude,
) async {
  final response = await http.post(
    Uri.parse('$baseUrl/agent/collections'),
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    },
    body: jsonEncode({
      'business_id': businessId,
      'amount': amount,
      'payment_method': 'cash',
      'gps_lat': latitude,
      'gps_lng': longitude,
      'collected_at': DateTime.now().toIso8601String(),
      'offline_sync_id': 'uuid-${DateTime.now().millisecondsSinceEpoch}',
    }),
  );
  
  if (response.statusCode == 201) {
    final data = jsonDecode(response.body);
    print('Collection recorded: ${data['data']['receipt_number']}');
  }
}
```

---

## Implementation Notes for Mobile App

### Offline-First Strategy
1. Store collections locally with `offline_sync_id`
2. When connectivity returns, use **POST /agent/collections/bulk** to sync multiple at once
3. Server deduplicates based on `offline_sync_id`

### Heartbeat Interval
- Send heartbeat every **30-60 seconds** during active work
- Include `battery_level` for admin visibility
- Admin dashboard uses 5-minute window for "online" status

### GPS Accuracy
- Request location with best available accuracy
- Include `accuracy` field in location updates
- Collections >500m from business are flagged but still recorded

### Token Management
- Store token securely (keychain/secure storage)
- Tokens expire after 30 days
- Implement token refresh flow or re-login on 401 error
- Delete token locally on logout

### Real-Time Features
- Poll `/agent/heartbeat` for unread notification count
- Fetch `/agent/notifications?unread_only=1` when notified
- Admin can poll `/admin/collections/live?since=<timestamp>` for live feed

---

## Support & Contact
For issues or questions about the API, contact the development team.
