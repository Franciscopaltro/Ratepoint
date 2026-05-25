# Ratepoint System - Backend Architecture & Implementation Guide

**Version:** 2.0  
**Technology Stack:** PHP 8.1+, Laravel 10+, MySQL 8.0+  
**Deployment Environment:** Linux (Ubuntu 22.04+)

---

## Table of Contents

1. [Database Architecture](#database-architecture)
2. [API Architecture](#api-architecture)
3. [Authentication & Authorization](#authentication--authorization)
4. [Caching Strategy](#caching-strategy)
5. [Queue & Background Jobs](#queue--background-jobs)
6. [Logging & Monitoring](#logging--monitoring)
7. [Deployment Architecture](#deployment-architecture)
8. [Scaling Strategy](#scaling-strategy)
9. [Performance Optimization](#performance-optimization)
10. [Disaster Recovery](#disaster-recovery)

---

## Database Architecture

### Schema Design

#### Users Table

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    
    role ENUM('field_agent', 'supervisor', 'finance_officer', 'super_admin') NOT NULL,
    zone_id BIGINT UNSIGNED,
    
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by BIGINT UNSIGNED,
    verified_at TIMESTAMP NULL,
    
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    last_password_change TIMESTAMP NULL,
    
    mfa_enabled BOOLEAN DEFAULT FALSE,
    mfa_method ENUM('sms', 'email', 'totp') DEFAULT 'sms',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_zone_id (zone_id),
    INDEX idx_role (role),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Personal Access Tokens Table

```sql
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    device_name VARCHAR(255),
    
    name VARCHAR(255),
    token VARCHAR(64) UNIQUE NOT NULL,  -- SHA256 hash
    abilities JSON,
    
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_device_id (device_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Collections Table

```sql
CREATE TABLE collections (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    agent_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    zone_id BIGINT UNSIGNED,
    
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'mobile_money', 'check') DEFAULT 'cash',
    
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    offline_sync_id VARCHAR(36),  -- UUID
    
    gps_lat DECIMAL(10, 8) NOT NULL,
    gps_lng DECIMAL(11, 8) NOT NULL,
    gps_accuracy FLOAT,  -- meters
    gps_verified BOOLEAN DEFAULT TRUE,
    
    collected_at TIMESTAMP NOT NULL,
    synced_at TIMESTAMP NULL,
    
    is_reconciled BOOLEAN DEFAULT FALSE,
    reconciled_at TIMESTAMP NULL,
    reconciliation_id BIGINT UNSIGNED,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_agent_date (agent_id, DATE(collected_at)),
    INDEX idx_business_id (business_id),
    INDEX idx_zone_id (zone_id),
    INDEX idx_collected_at (collected_at),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_offline_sync_id (offline_sync_id),
    INDEX idx_is_reconciled (is_reconciled),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    FOREIGN KEY (business_id) REFERENCES businesses(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (reconciliation_id) REFERENCES reconciliations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Agent Locations Table

```sql
CREATE TABLE agent_locations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    agent_id BIGINT UNSIGNED UNIQUE NOT NULL,
    
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT,  -- meters
    
    battery_level INT,  -- 0-100
    is_online BOOLEAN DEFAULT TRUE,
    
    last_seen_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_agent_id (agent_id),
    INDEX idx_last_seen_at (last_seen_at),
    INDEX idx_is_online (is_online),
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Audit Logs Table

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    user_id BIGINT UNSIGNED,
    action VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50),
    resource_id BIGINT UNSIGNED,
    
    old_values JSON,
    new_values JSON,
    
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Suspicious Activities Table

```sql
CREATE TABLE suspicious_activities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    type ENUM('GPS_MISMATCH', 'IMPOSSIBLE_TRAVEL', 'DUPLICATE_COLLECTION', 
              'FAILED_LOGIN', 'TOKEN_MISUSE', 'DEVICE_CHANGE') NOT NULL,
    
    agent_id BIGINT UNSIGNED,
    collection_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED,
    
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'investigating', 'resolved', 'false_alarm') DEFAULT 'open',
    
    description TEXT,
    metadata JSON,
    
    assigned_to BIGINT UNSIGNED,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    INDEX idx_type (type),
    INDEX idx_agent_id (agent_id),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    FOREIGN KEY (collection_id) REFERENCES collections(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Indexing Strategy

```sql
-- Performance indexes for common queries
CREATE INDEX idx_collections_agent_date 
  ON collections(agent_id, DATE(collected_at), zone_id);

CREATE INDEX idx_collections_business_amount_date
  ON collections(business_id, amount, collected_at);

CREATE INDEX idx_users_zone_role_active
  ON users(zone_id, role, is_active);

CREATE INDEX idx_agent_locations_online_timestamp
  ON agent_locations(is_online, last_seen_at);

-- Full-text search for businesses
CREATE FULLTEXT INDEX idx_businesses_search 
  ON businesses(name, owner_name);

-- Partitioning by date for large collections table
ALTER TABLE collections 
PARTITION BY RANGE (YEAR(collected_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

---

## API Architecture

### Layered Architecture

```
┌─────────────────────────────────────────┐
│         API Routes (routes/api.php)     │
├─────────────────────────────────────────┤
│      Controllers (Http/Controllers/)    │
│  - Request validation                   │
│  - Response formatting                  │
├─────────────────────────────────────────┤
│      Services (Services/)               │
│  - Business logic                       │
│  - Orchestration                        │
├─────────────────────────────────────────┤
│      Repositories (Repositories/)       │
│  - Data abstraction                     │
│  - Query building                       │
├─────────────────────────────────────────┤
│        Models (Models/)                 │
│  - Eloquent models                      │
│  - Database mapping                     │
├─────────────────────────────────────────┤
│        Database                         │
│  - MySQL tables                         │
└─────────────────────────────────────────┘
```

### Example Controller Implementation

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Collection;
use App\Services\CollectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CollectionController extends Controller
{
    private CollectionService $collectionService;
    
    public function __construct(CollectionService $collectionService)
    {
        $this->collectionService = $collectionService;
    }
    
    /**
     * POST /api/v1/agent/collections
     * Record a single collection
     */
    public function store(Request $request): Response
    {
        // Validate input
        $validated = $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'gps_lat' => 'required|numeric|between:-90,90',
            'gps_lng' => 'required|numeric|between:-180,180',
            'collected_at' => 'required|date_format:Y-m-d\TH:i:s\Z',
            'payment_method' => 'nullable|in:cash,mobile_money,check',
            'offline_sync_id' => 'nullable|string|max:36|unique:collections',
        ]);
        
        try {
            // Call service layer
            $collection = $this->collectionService->recordCollection(
                auth()->id(),
                $validated
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Collection recorded successfully',
                'data' => $collection->toApiArray(),
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Collection recording failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Server error',
                'message' => 'Failed to record collection',
            ], 500);
        }
    }
    
    /**
     * GET /api/v1/agent/collections
     * Paginated list of collections
     */
    public function index(Request $request): Response
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 20), 100);
        $date = $request->input('date', now()->format('Y-m-d'));
        
        $collections = Collection::where('agent_id', auth()->id())
            ->whereDate('collected_at', $date)
            ->orderBy('collected_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'success' => true,
            'data' => $collections->getCollection()->map->toApiArray(),
            'pagination' => [
                'current_page' => $collections->currentPage(),
                'per_page' => $collections->perPage(),
                'total' => $collections->total(),
                'last_page' => $collections->lastPage(),
                'from' => $collections->firstItem(),
                'to' => $collections->lastItem(),
                'has_more' => $collections->hasMorePages(),
            ],
        ]);
    }
}
```

### Service Layer Example

```php
<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Business;
use App\Models\SuspiciousActivity;
use App\Repositories\CollectionRepository;
use App\Events\CollectionRecorded;
use Illuminate\Support\Str;

class CollectionService
{
    private CollectionRepository $repository;
    
    public function __construct(CollectionRepository $repository)
    {
        $this->repository = $repository;
    }
    
    /**
     * Record a collection with validation and fraud detection
     */
    public function recordCollection(
        int $agentId,
        array $data
    ): Collection {
        // Get business
        $business = Business::findOrFail($data['business_id']);
        
        // 1. Check geofence (GPS validation)
        $distance = $this->calculateDistance(
            $data['gps_lat'], $data['gps_lng'],
            $business->latitude, $business->longitude
        );
        
        if ($distance > 0.5) {
            SuspiciousActivity::create([
                'type' => 'GPS_MISMATCH',
                'agent_id' => $agentId,
                'severity' => 'medium',
                'description' => "Collected {$distance}km away from business",
            ]);
        }
        
        // 2. Check for duplicate (offline_sync_id)
        if (!empty($data['offline_sync_id'])) {
            if (Collection::where('offline_sync_id', $data['offline_sync_id'])->exists()) {
                throw new \Exception('Collection already synced (duplicate)');
            }
        }
        
        // 3. Create collection
        $collection = new Collection([
            'agent_id' => $agentId,
            'business_id' => $business->id,
            'zone_id' => $business->zone_id,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'gps_lat' => $data['gps_lat'],
            'gps_lng' => $data['gps_lng'],
            'offline_sync_id' => $data['offline_sync_id'],
            'collected_at' => $data['collected_at'],
            'receipt_number' => $this->generateReceiptNumber(),
            'synced_at' => now(),
        ]);
        
        // 4. Save to database
        $collection->save();
        
        // 5. Update business status
        $business->update(['status' => 'paid', 'last_collected_at' => now()]);
        
        // 6. Fire event (triggers notification, logging, etc)
        event(new CollectionRecorded($collection));
        
        // 7. Clear cache
        \Cache::forget("agent_stats:{$agentId}");
        
        return $collection;
    }
    
    /**
     * Bulk sync collections
     */
    public function bulkSync(int $agentId, array $collections): array
    {
        $results = [];
        
        foreach ($collections as $index => $item) {
            try {
                $collection = $this->recordCollection($agentId, $item);
                
                $results[] = [
                    'index' => $index,
                    'status' => 'synced',
                    'server_id' => $collection->id,
                    'receipt_number' => $collection->receipt_number,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'index' => $index,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    private function calculateDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        // Haversine formula
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
               + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos(min(1, max(-1, $dist)));
        return rad2deg($dist) * 60 * 1.1515 * 1.609344;
    }
    
    private function generateReceiptNumber(): string
    {
        return 'REC-' . strtoupper(bin2hex(random_bytes(4))) 
               . '-' . date('Ymd');
    }
}
```

---

## Authentication & Authorization

### JWT Token Implementation

```php
<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $privateKey;
    private string $publicKey;
    private string $algorithm = 'RS256';
    
    public function __construct()
    {
        $this->privateKey = file_get_contents(
            storage_path('keys/jwt-private.pem')
        );
        $this->publicKey = file_get_contents(
            storage_path('keys/jwt-public.pem')
        );
    }
    
    /**
     * Generate access token (2 hours)
     */
    public function generateAccessToken(int $userId, string $deviceId): string
    {
        $payload = [
            'iss' => config('app.url'),
            'aud' => 'ratepoint-mobile',
            'iat' => time(),
            'exp' => time() + (2 * 60 * 60),  // 2 hours
            'sub' => $userId,
            'device_id' => $deviceId,
            'type' => 'access',
        ];
        
        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }
    
    /**
     * Verify and decode token
     */
    public function verifyToken(string $token): object
    {
        try {
            return JWT::decode(
                $token,
                new Key($this->publicKey, $this->algorithm)
            );
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage());
        }
    }
    
    /**
     * Check token expiry
     */
    public function isExpired(string $token): bool
    {
        $payload = $this->verifyToken($token);
        return $payload->exp < time();
    }
}
```

### Permission Gates

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();
        
        // Agent permissions
        Gate::define('collect-revenue', function ($user) {
            return in_array($user->role, ['field_agent', 'supervisor']);
        });
        
        Gate::define('view-own-collections', function ($user) {
            return true;  // All authenticated can view own
        });
        
        Gate::define('update-location', function ($user) {
            return $user->role === 'field_agent';
        });
        
        // Admin permissions
        Gate::define('view-all-agents', function ($user) {
            return in_array($user->role, ['super_admin', 'supervisor']);
        });
        
        Gate::define('send-notifications', function ($user) {
            return in_array($user->role, ['super_admin', 'finance_officer', 'supervisor']);
        });
        
        Gate::define('generate-reports', function ($user) {
            return in_array($user->role, ['super_admin', 'finance_officer']);
        });
        
        Gate::define('manage-users', function ($user) {
            return $user->role === 'super_admin';
        });
    }
}
```

### Middleware for Authentication

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\JwtService;

class AuthenticateWithJwt
{
    private JwtService $jwtService;
    
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }
    
    public function handle(Request $request, Closure $next)
    {
        // Get token from header
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'message' => 'Authorization header required'
            ], 401);
        }
        
        try {
            // Verify token
            $payload = $this->jwtService->verifyToken($token);
            
            // Check expiry
            if ($this->jwtService->isExpired($token)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Token expired',
                ], 401);
            }
            
            // Get user
            $user = \App\Models\User::find($payload->sub);
            if (!$user || !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found or inactive'
                ], 403);
            }
            
            // Attach to request
            \Auth::setUser($user);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 401);
        }
        
        return $next($request);
    }
}
```

---

## Caching Strategy

### Redis Caching Layer

```php
<?php

// Configuration: config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => 'ratepoint:',
    ],
],
```

### Cache Keys Strategy

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    // Agent caches
    public static function agentStatsKey(int $agentId, string $period = 'today'): string
    {
        return "agent_stats:{$agentId}:{$period}";
    }
    
    public static function agentBusinessesKey(int $agentId): string
    {
        return "agent_businesses:{$agentId}";
    }
    
    // Admin caches
    public static function dashboardStatsKey(): string
    {
        return "dashboard_stats:{date('Y-m-d')}";
    }
    
    public static function agentsLiveKey(): string
    {
        return "agents_live";
    }
    
    /**
     * Get user stats with cache
     */
    public static function getAgentStats(int $agentId): array
    {
        return Cache::rememberForever(
            self::agentStatsKey($agentId),
            function () use ($agentId) {
                // Query database
                $today = \App\Models\Collection::where('agent_id', $agentId)
                    ->whereDate('collected_at', today())
                    ->selectRaw('COUNT(*) as count, SUM(amount) as total')
                    ->first();
                
                return [
                    'today_collections' => $today->count ?? 0,
                    'today_amount' => $today->total ?? 0,
                ];
            }
        );
    }
    
    /**
     * Invalidate caches on collection
     */
    public static function invalidateOnCollection(int $agentId): void
    {
        Cache::forget(self::agentStatsKey($agentId, 'today'));
        Cache::forget(self::dashboardStatsKey());
        Cache::forget(self::agentsLiveKey());
    }
}
```

### Cache Tags for Hierarchical Invalidation

```php
<?php

// Invalidate all agent-related caches
Cache::tags(['agent:' . $agentId])->flush();

// Usage
Cache::tags(['agent:5', 'collections'])->remember(
    'agent_collections:5',
    3600,
    fn() => Collection::where('agent_id', 5)->get()
);

// Invalidate when collection created
event(new CollectionCreated($collection));
Cache::tags(['agent:' . $collection->agent_id, 'collections'])->flush();
```

---

## Queue & Background Jobs

### Queued Jobs

```php
<?php

namespace App\Jobs;

use App\Models\Collection;
use App\Events\CollectionSynced;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessCollectionSync implements ShouldQueue
{
    use Queueable;
    
    public int $timeout = 60;
    public int $tries = 3;
    
    public function __construct(
        public Collection $collection
    ) {}
    
    public function handle(): void
    {
        // Process collection
        // - Verify GPS
        // - Check for fraud
        // - Update analytics
        // - Trigger notifications
        
        event(new CollectionSynced($this->collection));
    }
    
    public function failed(\Throwable $exception): void
    {
        \Log::error('Collection sync failed', [
            'collection_id' => $this->collection->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Scheduled Tasks

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Mark agents as offline if no heartbeat in 5 minutes
        $schedule->call(function () {
            AgentLocation::where('is_online', true)
                ->where('last_seen_at', '<', now()->subMinutes(5))
                ->update(['is_online' => false]);
        })->everyMinute();
        
        // Generate daily reconciliation reports
        $schedule->command('reconciliation:generate-daily')
            ->dailyAt('23:59');
        
        // Archive old audit logs
        $schedule->command('audit:archive-old')
            ->weekly();
        
        // Cleanup expired tokens
        $schedule->command('tokens:cleanup-expired')
            ->hourly();
        
        // Generate analytics
        $schedule->command('analytics:generate-daily')
            ->dailyAt('03:00');
    }
}
```

---

## Logging & Monitoring

### Structured Logging

```php
<?php

use Illuminate\Support\Facades\Log;

// Log collection recording
Log::info('Collection recorded', [
    'collection_id' => $collection->id,
    'agent_id' => $collection->agent_id,
    'business_id' => $collection->business_id,
    'amount' => $collection->amount,
    'gps_distance' => $distance,
    'receipt' => $collection->receipt_number,
    'timestamp' => now()->toIso8601String(),
    'user_ip' => request()->ip(),
]);

// Log API requests
Log::channel('api')->info('API Request', [
    'method' => $request->method(),
    'endpoint' => $request->path(),
    'status' => $response->status(),
    'duration_ms' => $duration,
    'user_id' => auth()->id(),
]);

// Log errors
Log::error('Collection sync failed', [
    'collection_id' => $e->collection_id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'user_id' => auth()->id(),
]);
```

### Application Monitoring

```bash
# Monitor server metrics
# CPU, Memory, Disk, Network

# Monitor database
# Query performance, slow queries, connections

# Monitor queue
# Job failures, pending jobs, processing time

# Monitor API
# Response time, error rate, throughput

# Tools: Datadog, New Relic, Prometheus, Grafana
```

### Health Check Endpoint

```php
<?php

Route::get('/health', function () {
    $health = [
        'database' => 'ok',
        'cache' => 'ok',
        'queue' => 'ok',
    ];
    
    // Check database
    try {
        \DB::connection()->getPdo();
    } catch (\Exception $e) {
        $health['database'] = 'error: ' . $e->getMessage();
    }
    
    // Check Redis
    try {
        \Cache::connection('redis')->get('_test_');
    } catch (\Exception $e) {
        $health['cache'] = 'error: ' . $e->getMessage();
    }
    
    return response()->json($health);
});
```

---

## Deployment Architecture

### Docker Containerization

```dockerfile
# Dockerfile

FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip
RUN pecl install redis && docker-php-ext-enable redis

# Copy application
WORKDIR /app
COPY . .

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-dev

# Set permissions
RUN chown -R www-data:www-data /app

EXPOSE 9000
```

### Docker Compose Setup

```yaml
# docker-compose.yml

version: '3.8'

services:
  php:
    build: .
    container_name: ratepoint_php
    restart: unless-stopped
    working_dir: /app
    ports:
      - "9000:9000"
    volumes:
      - ./:/app
    env_file:
      - .env
    depends_on:
      - mysql
      - redis

  nginx:
    image: nginx:latest
    container_name: ratepoint_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/app
      - ./nginx:/etc/nginx/conf.d
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - php

  mysql:
    image: mysql:8.0
    container_name: ratepoint_mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

  redis:
    image: redis:7
    container_name: ratepoint_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

### Deployment Checklist

```bash
# 1. Prepare server
sudo apt update && sudo apt upgrade -y
sudo apt install -y php8.1 php8.1-fpm mysql-server redis-server nginx

# 2. Clone repository
git clone https://github.com/municipality/ratepoint.git
cd ratepoint

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# 5. Migrate database
php artisan migrate --force

# 6. Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data /var/www/ratepoint

# 7. Configure SSL (Let's Encrypt)
sudo certbot certonly --standalone -d revenue.municipality.gov.gh

# 8. Start services
sudo systemctl start php8.1-fpm mysql redis-server nginx

# 9. Run migrations
php artisan migrate

# 10. Seed initial data
php artisan db:seed

# 11. Set up monitoring
# Configure Datadog/New Relic agent
# Configure log shipping (CloudWatch, ELK)
```

---

## Scaling Strategy

### Horizontal Scaling

```
Load Balancer (NGINX)
        │
┌───────┼───────┐
│       │       │
PHP     PHP     PHP
App 1   App 2   App 3
│       │       │
└───────┼───────┘
        │
    DB Cluster
    (Primary + Replicas)
```

### Database Read Replicas

```php
// config/database.php

'mysql' => [
    'write' => [
        'host' => 'primary.db.amazonaws.com',
    ],
    'read' => [
        ['host' => 'replica1.db.amazonaws.com'],
        ['host' => 'replica2.db.amazonaws.com'],
    ],
],
```

### Automated Scaling with Kubernetes

```yaml
# k8s-deployment.yaml

apiVersion: apps/v1
kind: Deployment
metadata:
  name: ratepoint-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: ratepoint-api
  template:
    metadata:
      labels:
        app: ratepoint-api
    spec:
      containers:
      - name: api
        image: ratepoint:latest
        resources:
          requests:
            cpu: "500m"
            memory: "512Mi"
          limits:
            cpu: "1000m"
            memory: "1Gi"
      - name: php-fpm
        image: php:8.1-fpm

---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: ratepoint-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: ratepoint-api
  minReplicas: 3
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

---

## Performance Optimization

### Query Optimization

```php
// Use eager loading to prevent N+1
$agents = Agent::with('collections', 'location')
    ->where('is_active', true)
    ->get();

// Use select to fetch only needed columns
$users = User::select(['id', 'name', 'email'])
    ->where('role', 'field_agent')
    ->get();

// Use chunk for large datasets
Collection::chunk(1000, function ($collections) {
    foreach ($collections as $collection) {
        // Process
    }
});

// Use pluck for single column
$agentIds = User::where('role', 'field_agent')
    ->pluck('id');
```

### API Response Caching

```php
// Cache GET responses
\Cache::remember(
    "collections:{$page}:{$perPage}",
    3600,
    fn() => Collection::paginate($perPage)
);

// Set cache headers
header('Cache-Control: public, max-age=3600');
header('ETag: ' . md5($response));
```

### Database Connection Pooling

```
Database Connections
├─ Max: 100
├─ Min: 10
├─ Timeout: 30 seconds
└─ Idle: 5 seconds
```

---

## Disaster Recovery

### Backup Strategy

```bash
# Daily full backup at 2 AM UTC
0 2 * * * mysqldump -u root -p${DB_PASSWORD} ${DB_NAME} | \
  gzip > /backups/daily/ratepoint_$(date +\%Y\%m\%d).sql.gz

# Upload to S3
0 3 * * * aws s3 cp /backups/daily/ s3://ratepoint-backups/daily/

# Keep 30 days
find /backups/daily -mtime +30 -delete

# Verify restore monthly
0 4 1 * * ./scripts/verify_backup.sh
```

### Recovery Procedure

```bash
# 1. Stop application
systemctl stop php8.1-fpm

# 2. Restore database
gunzip < /backups/daily/ratepoint_20260523.sql.gz | \
  mysql -u root -p${DB_PASSWORD} ${DB_NAME}

# 3. Clear cache
php artisan cache:clear

# 4. Restart
systemctl start php8.1-fpm

# 5. Verify
curl https://revenue.municipality.gov.gh/health
```

---

## Implementation Roadmap

### Phase 1: MVP (Weeks 1-4)
- ✅ Core API endpoints
- ✅ SQLite database schema
- ✅ Basic authentication
- ✅ Collection recording

### Phase 2: Production-Ready (Weeks 5-8)
- ✅ MySQL migration
- ✅ Token refresh mechanism
- ✅ Pagination
- ✅ Advanced validation

### Phase 3: Scaling (Weeks 9-12)
- ✅ Caching layer (Redis)
- ✅ Queue system
- ✅ Load balancing
- ✅ Database replication

### Phase 4: Enterprise (Weeks 13+)
- ✅ Kubernetes deployment
- ✅ Advanced analytics
- ✅ Real-time dashboards
- ✅ ELK logging stack

---

**Document Version:** 2.0  
**Last Updated:** May 2026  
**Next Review:** August 2026
