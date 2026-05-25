# Ratepoint System - Security Best Practices Guide

**Version:** 2.0  
**Last Updated:** May 2026

---

## Executive Summary

This guide provides comprehensive security recommendations for deploying Ratepoint System in production. Implements enterprise-level security suitable for municipal revenue systems in Africa.

**Key Security Priorities:**
1. Secure authentication & token management
2. Data encryption in transit & at rest
3. Field agent account security
4. Admin account protection
5. Audit logging & forensics
6. Network security
7. Backup & disaster recovery

---

## 1. Authentication & Authorization

### Token-Based Authentication

**Access Token (Short-Lived)**
```
Type: JWT (RS256 with public/private key signing)
Lifespan: 2 hours
Scope: All API requests
Format: Authorization: Bearer {token}
Storage: RAM only (cleared on app close)
```

**Refresh Token (Long-Lived)**
```
Type: Opaque random string (64+ characters)
Lifespan: 30 days
Scope: Get new access token only
Format: POST /auth/refresh body
Storage: Secure device storage (Keychain/EncryptedSharedPreferences)
Security: Hashed with argon2 in database
Device-Bound: Specific to device_id
Rotated: New token issued with each refresh
```

**Device Token (Authentication Chain)**
```
Type: Hardware-bound identifier
Lifespan: 1 year
Purpose: Prevent token transfer between devices
Validation: Reject tokens used on different device IDs
```

### Role-Based Access Control (RBAC)

```php
// Define roles with permissions
$roles = [
    'field_agent' => [
        'collect_revenue',
        'view_assigned_businesses',
        'view_own_collections',
        'update_own_location',
    ],
    'supervisor' => [
        'collect_revenue',
        'view_zone_agents',
        'view_zone_collections',
        'send_notifications',
    ],
    'finance_officer' => [
        'view_all_collections',
        'generate_reports',
        'reconcile_accounts',
        'export_data',
    ],
    'super_admin' => [
        '*',  // All permissions
    ]
];

// Check permissions before action
Gate::define('collect_revenue', function ($user) {
    return $user->role === 'field_agent' || $user->role === 'supervisor';
});

// In controller
$this->authorize('collect_revenue');
```

### Session Management

```php
// 1. Single session per user
$existingSessions = PersonalAccessToken::where('user_id', $user->id)->get();
foreach ($existingSessions as $session) {
    $session->delete();  // Logout old sessions
}

// 2. Session tracking by device
PersonalAccessToken::create([
    'user_id' => $user->id,
    'device_id' => $request->input('device_id'),
    'device_name' => $request->input('device_name'),
    'token' => hash('sha256', $plainToken),
    'expires_at' => now()->addDays(30),
    'last_used_at' => now(),
]);

// 3. Detect suspicious device usage
$sessions = PersonalAccessToken::where('user_id', $user->id)->get();
$uniqueDevices = $sessions->pluck('device_id')->unique();

if ($uniqueDevices->count() > 3) {
    alert_user('Multiple devices detected. If unauthorized, change password.');
}
```

---

## 2. Password Security

### Password Requirements for Agents

```
✅ Minimum 10 characters
✅ Mix of uppercase, lowercase, numbers, symbols
✅ Not reuse last 5 passwords
✅ Expires every 90 days
✅ Account locked after 5 failed attempts (30 min)
✅ Hashed with bcrypt or argon2
```

### Implementation

```php
// Hash on registration
$user->password = Hash::make($password);

// Verify on login
if (!Hash::check($password, $user->password)) {
    $this->recordFailedAttempt($user->email);
    
    if ($this->getFailedAttempts($user->email) >= 5) {
        $this->lockAccount($user->email, 30);  // 30 minutes
    }
}

// Enforce password change every 90 days
if ($user->last_password_change < now()->subDays(90)) {
    Event::dispatch(new PasswordExpired($user));
    // Force password reset before next login
}
```

### Password Reset Security

```php
// Generate secure reset token
$token = hash('sha256', bin2hex(random_bytes(32)));

// Store hashed token
PasswordReset::create([
    'email' => $user->email,
    'token' => hash('sha256', $token),
    'expires_at' => now()->addHours(1),  // 1 hour only
]);

// Send link via email (not in plaintext)
Mail::send('passwords.reset', [
    'reset_url' => 'https://api.example.com/reset/' . $token,
    'expires_at' => now()->addHours(1)->toFormattedDateString(),
]);

// Validate reset
$reset = PasswordReset::where('token', hash('sha256', $token))
    ->where('expires_at', '>', now())
    ->firstOrFail();
```

---

## 3. Data Encryption

### Encryption in Transit (HTTPS/TLS)

```
✅ Mandatory HTTPS only (no HTTP fallback)
✅ TLS 1.2 minimum (TLS 1.3 preferred)
✅ Strong cipher suites (AES-256-GCM)
✅ HSTS headers (strict transport)
✅ Certificate pinning for mobile app (optional but recommended)
```

**NGINX Configuration:**
```nginx
server {
    listen 443 ssl http2;
    
    # TLS Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # HSTS (Strict Transport Security)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Disable HTTP
    if ($scheme != "https") {
        return 301 https://$server_name$request_uri;
    }
    
    # Certificate
    ssl_certificate /etc/letsencrypt/live/revenue.municipality.gov.gh/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/revenue.municipality.gov.gh/privkey.pem;
}
```

### Encryption at Rest

```php
// 1. Database Encryption
// Use AWS RDS encryption or similar
$dbConfig = [
    'encrypted' => true,
    'kms_key_id' => env('DB_ENCRYPTION_KEY'),
];

// 2. File Storage Encryption
Storage::disk('s3')->put('backups/data.sql.gz', 
    $content, 
    ['ServerSideEncryption' => 'AES256']
);

// 3. Sensitive Fields Encryption
// Encrypt phone numbers, GPS coordinates at rest
$user->phone_number = encrypt($phoneNumber);
$user->save();

// Retrieve and decrypt
$plainPhone = decrypt($user->phone_number);
```

### Secure Configuration Storage

```php
// ❌ DON'T put secrets in .env
DB_PASSWORD=admin123

// ✅ DO use secure vaults
AWS Secrets Manager:
secrets-manager get-secret ratepoint-db-password

// PHP usage
$dbPassword = \Aws\SecretsManager\SecretsManagerClient::getSecretValue([
    'SecretId' => 'ratepoint-db-password'
]);
```

---

## 4. Field Agent Account Security

### Account Vetting on Registration

```php
// 1. Verify through admin
User::create([
    'email' => $email,
    'password' => Hash::make($password),
    'is_active' => false,  // Default disabled
    'verified_by_admin' => false,
    'verification_code' => Str::random(6),
]);

// Admin must verify
Admin::sendApprovalRequest($user);

// 2. Require supervisor approval
User::where('id', $user->id)->update([
    'is_active' => true,
    'verified_at' => now(),
    'verified_by' => Auth::id(),
]);
```

### Device Security

```php
// 1. Device fingerprinting
$deviceFingerprint = hash('sha256', 
    $deviceId . $deviceName . $osVersion . $imei
);

// 2. Detect compromised devices
if ($deviceFingerprint !== $stored_fingerprint) {
    // Device may be compromised (rooted/jailbroken)
    alert_admin("Device potentially compromised: $deviceName");
}

// 3. Prevent unauthorized app installation
// Sign APK with production key
// Only accept APK from official Play Store
```

### GPS Spoofing Detection

```php
// 1. Geofencing: Collection >500m from business
$businessLocation = [$business->latitude, $business->longitude];
$collectionLocation = [$collection->gps_lat, $collection->gps_lng];
$distance = haversineDistance($businessLocation, $collectionLocation);

if ($distance > 0.5) {
    SuspiciousActivity::create([
        'type' => 'GPS_MISMATCH',
        'agent_id' => $agent->id,
        'severity' => 'medium',
        'description' => "Collected from $distance km away",
    ]);
}

// 2. Impossible travel speed: Location change in <1 min across >50km
$lastLocation = AgentLocation::where('agent_id', $agent->id)->latest()->first();
$distance = haversineDistance(
    [$lastLocation->latitude, $lastLocation->longitude],
    [$newLocation->latitude, $newLocation->longitude]
);

if ($distance > 50 && $timeDiff < 60) {
    SuspiciousActivity::flag('IMPOSSIBLE_TRAVEL', $agent->id);
}

// 3. Offline collections verification
// GPS accuracy must be <100m (not 2000m+ which indicates spoofing)
if ($accuracy > 100 || $accuracy === null) {
    SuspiciousActivity::flag('POOR_GPS_ACCURACY', $agent->id);
}
```

---

## 5. Admin Account Protection

### Multi-Factor Authentication (MFA)

```php
// 1. Require MFA for all admin users
if ($user->role === 'super_admin' || $user->role === 'finance_officer') {
    $user->update(['mfa_enabled' => true]);
}

// 2. Send MFA code to phone
$mfaCode = random_int(100000, 999999);
SMS::send($user->phone_number, "Your login code: $mfaCode (expires in 5 min)");

// Store with expiry
MFAToken::create([
    'user_id' => $user->id,
    'code' => hash('sha256', $mfaCode),
    'expires_at' => now()->addMinutes(5),
]);

// 3. Verify MFA before granting access
if (!$this->verifyMFA($user, $providedCode)) {
    throw new AuthenticationException('Invalid MFA code');
}
```

### IP Whitelisting for Admin

```php
// 1. Whitelist office IPs
$adminUser->update([
    'allowed_ips' => ['202.168.1.0/24', '209.45.200.5'],
]);

// 2. Check on each admin request
$clientIP = $request->getClientIp();
if (!$this->isIPWhitelisted($adminUser, $clientIP)) {
    Log::alert("Unauthorized IP access attempt: $clientIP for admin {$adminUser->email}");
    throw new AuthenticationException('Access denied from this IP');
}
```

### Admin Activity Logging

```php
// Log all admin actions in separate audit table
AuditLog::create([
    'user_id' => Auth::id(),
    'action' => 'COLLECTION_VERIFIED',
    'target_id' => $collection->id,
    'changes' => json_encode([
        'status' => 'unverified' => 'verified',
        'verified_by' => Auth::id(),
    ]),
    'ip_address' => request()->ip(),
    'user_agent' => request()->header('User-Agent'),
    'timestamp' => now(),
]);
```

---

## 6. Network Security

### API Rate Limiting

```php
// Prevent brute force attacks
Route::post('auth/login', 'AuthController@login')
    ->middleware('throttle:5,15');  // 5 attempts per 15 minutes

// Prevent API abuse
Route::middleware('throttle:100,1')->group(function () {
    Route::get('agent/collections', 'CollectionController@index');
    Route::post('agent/collections', 'CollectionController@store');
});

// Exponential backoff for mobile app
Rate limit increases penalty on repeat violations:
1st failure: 1 sec
2nd failure: 2 sec
3rd failure: 4 sec
4th failure: 8 sec
5th+ failure: 16 sec
```

### DDoS Protection

```
✅ CloudFlare or similar WAF
✅ Rate limiting per IP
✅ Captcha on suspicious traffic
✅ Automatic blocking of bad IPs
✅ Geographic restriction if needed (only serve Ghana + diaspora)
```

### CORS Configuration

```php
// Allow only trusted domains
header('Access-Control-Allow-Origin: https://revenue.municipality.gov.gh');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Reject requests from untrusted origins
if (!in_array($origin, $trustedOrigins)) {
    http_response_code(403);
    exit;
}
```

### SQL Injection Prevention

```php
// ✅ Use prepared statements ALWAYS
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);

// ❌ NEVER concatenate user input
$result = $pdo->query("SELECT * FROM users WHERE email = '$email'");  // VULNERABLE!

// ✅ Use ORM
$user = User::where('email', $email)->where('is_active', true)->first();
```

### XSS Prevention

```php
// Escape output in responses
json_encode($data, JSON_HtmlQuotes | JSON_UnescapedSlashes);

// In JSON responses
header('Content-Type: application/json; charset=utf-8');

// Mobile apps don't render HTML, but be safe anyway
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
```

---

## 7. Audit Logging & Forensics

### Comprehensive Audit Trail

```sql
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50),
    resource_type VARCHAR(50),
    resource_id INT,
    changes JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action)
);
```

**What to Log:**
- ✅ All logins (success & failure)
- ✅ All API requests to sensitive endpoints
- ✅ All data modifications
- ✅ Admin actions
- ✅ Authorization failures
- ✅ Suspicious activities detected
- ✅ Token generation & revocation
- ✅ Password changes
- ✅ Account disable/enable

### Real-Time Alerting

```php
// Alert on suspicious activity
SuspiciousActivity::observe(new SuspiciousActivityObserver);

class SuspiciousActivityObserver {
    public function created(SuspiciousActivity $activity) {
        if ($activity->severity === 'high') {
            // Send alert immediately
            Notification::route('sms', '+233XXXXXXXXXXX')
                ->notify(new SuspiciousActivityAlert($activity));
            
            // Create incident ticket
            IncidentTicket::create([
                'activity_id' => $activity->id,
                'assigned_to' => 'security_team',
                'priority' => 'high',
            ]);
        }
    }
}
```

---

## 8. Backup & Disaster Recovery

### Backup Strategy

```
Daily Full Backup at 2 AM UTC
├─ Location: AWS S3 with cross-region replication
├─ Retention: 30 days online, 1 year in Glacier
├─ Encryption: AES-256
└─ Verification: Restore test on backup

Hourly Incremental Backup
├─ Location: Local NAS (snapshots)
├─ Retention: 7 days
└─ Purpose: Quick recovery from minor issues

Transaction Logs
├─ Continuous replication
├─ Point-in-time recovery
└─ RPO: 5 minutes
```

### Disaster Recovery Plan

```
RTO (Recovery Time Objective): 1 hour
RPO (Recovery Point Objective): 5 minutes

Incident Level 1 (Minor Data Corruption)
├─ Restore from 1-hour-old backup
├─ Time: ~5 minutes

Incident Level 2 (Server Failure)
├─ Failover to replica database
├─ Spin up backup server
├─ Time: ~10 minutes

Incident Level 3 (Complete Data Center Loss)
├─ Restore from S3 cross-region backup
├─ Rebuild infrastructure
├─ Time: ~1 hour
```

### Testing & Validation

```bash
# Monthly backup restoration test
./scripts/restore_from_backup.sh production_backup_2026_05_01

# Verify data integrity
./scripts/validate_backup.sh

# Document restore process
./docs/DISASTER_RECOVERY_RUNBOOK.md
```

---

## 9. Compliance & Regulations

### Data Privacy

```
✅ GDPR-like principles (if handling EU data)
✅ Data minimization: Collect only necessary data
✅ Purpose limitation: Use data only for stated purpose
✅ Data retention: Delete after 7 years
✅ Right to be forgotten: Support account deletion
✅ Privacy Policy: Transparent about data handling
```

### Financial Compliance

```
✅ PCI-DSS if storing payment cards (don't - use mobile money only)
✅ SOX Section 404 controls (if public company)
✅ Financial audit trail: Cannot modify historical records
✅ Segregation of duties: Agent can't approve own collections
✅ Reconciliation: Daily collection vs payment reconciliation
```

### Government Requirements

```
✅ Maintain 7-year audit trail for tax purposes
✅ Encrypted offline data for unstable network areas
✅ Biometric authentication (optional, for high-security zones)
✅ Geolocation logging for field enforcement
✅ Integration with government systems (API available)
```

---

## 10. Production Deployment Checklist

### Pre-Launch Security Review

- [ ] All endpoints use HTTPS only
- [ ] API keys rotated and stored securely
- [ ] Database credentials in secure vault
- [ ] Admin MFA enabled
- [ ] Rate limiting configured
- [ ] Audit logging enabled
- [ ] Backup system tested
- [ ] DDoS protection enabled
- [ ] WAF rules deployed
- [ ] SSL certificate valid
- [ ] HSTS headers configured
- [ ] CORS properly configured
- [ ] All secrets removed from repository
- [ ] Security headers set
- [ ] Logging centralized
- [ ] Monitoring alerts configured
- [ ] Incident response plan documented
- [ ] Disaster recovery tested
- [ ] Security training completed for team
- [ ] Penetration testing completed

### Ongoing Security Maintenance

```
Weekly:
├─ Review security alerts
├─ Check for failed login attempts
└─ Verify backup completion

Monthly:
├─ Review audit logs
├─ Test disaster recovery
├─ Check SSL certificate expiry
└─ Update security patches

Quarterly:
├─ Security code review
├─ Penetration testing
├─ Compliance audit
└─ Update security policy

Annually:
├─ Full security audit
├─ Disaster recovery drill
├─ Team security training
└─ Third-party security assessment
```

---

## Emergency Contacts

**Incident Response:**
- Security Team: security@municipality.gov.gh
- Emergency Hotline: +233XX-XXX-XXXX
- After-Hours: on-call through PagerDuty

**Escalation Path:**
1. Security Team
2. IT Director
3. Chief Information Officer

---

**Document Version:** 2.0  
**Last Updated:** May 2026  
**Next Review:** August 2026
