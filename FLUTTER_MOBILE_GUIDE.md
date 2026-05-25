# Ratepoint System - Flutter Mobile App Developer Guide

**Version:** 2.0  
**Target Platform:** Android 8.0+ / iOS 12.0+  
**Architecture:** Offline-First with Automatic Sync  
**Flutter Version:** 3.0+

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Project Architecture](#project-architecture)
3. [Authentication & Token Management](#authentication--token-management)
4. [Offline-First Strategy](#offline-first-strategy)
5. [GPS & Location Services](#gps--location-services)
6. [Collection Recording](#collection-recording)
7. [Networking & Sync](#networking--sync)
8. [Battery & Performance](#battery--performance)
9. [Error Handling](#error-handling)
10. [Testing](#testing)
11. [Deployment](#deployment)

---

## Getting Started

### Environment Setup

```bash
# Install Flutter 3.0+
flutter --version  # Should be 3.0.0 or higher

# Create new project
flutter create ratepoint_mobile

# Required dependencies
flutter pub add:
  - http
  - dio
  - sqflite
  - path_provider
  - shared_preferences
  - geolocator
  - flutter_secure_storage
  - flutter_local_notifications
  - connectivity_plus
  - uuid
  - intl
  - provider
  - riverpod

flutter pub get
```

### API Base Configuration

```dart
// lib/config/api_config.dart

class ApiConfig {
  static const String baseUrl = 'https://revenue.municipality.gov.gh/api/v1';
  static const Duration timeout = Duration(seconds: 30);
  static const Duration retryDelay = Duration(seconds: 2);
  static const int maxRetries = 3;
  
  // OAuth
  static const Duration accessTokenExpiry = Duration(hours: 2);
  static const Duration refreshTokenExpiry = Duration(days: 30);
}
```

### Project Structure

```
lib/
├── main.dart
├── config/
│   ├── api_config.dart
│   └── app_constants.dart
├── models/
│   ├── user.dart
│   ├── business.dart
│   ├── collection.dart
│   └── notification.dart
├── services/
│   ├── api_service.dart
│   ├── auth_service.dart
│   ├── location_service.dart
│   ├── database_service.dart
│   ├── sync_service.dart
│   └── notification_service.dart
├── repositories/
│   ├── auth_repository.dart
│   ├── collection_repository.dart
│   └── business_repository.dart
├── providers/
│   ├── auth_provider.dart
│   ├── collection_provider.dart
│   └── ui_provider.dart
├── screens/
│   ├── login_screen.dart
│   ├── home_screen.dart
│   ├── business_list_screen.dart
│   ├── collection_form_screen.dart
│   └── settings_screen.dart
└── utils/
    ├── validators.dart
    ├── formatters.dart
    └── error_handler.dart
```

---

## Project Architecture

### Clean Architecture with Provider State Management

```
Data Layer
├── API Service (HTTP calls)
├── Local Database (SQLite)
└── Secure Storage (Tokens)
    ↓
Repository Layer (Data abstraction)
    ↓
Provider/State Management
    ↓
UI Layer (Screens, Widgets)
```

### Service Architecture

```dart
// lib/services/api_service.dart

class ApiService {
  final Dio _dio;
  final SecureStorage _secureStorage;
  
  ApiService({
    required SecureStorage secureStorage,
  }) : _secureStorage = secureStorage {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
        connectTimeout: ApiConfig.timeout,
        receiveTimeout: ApiConfig.timeout,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );
    
    // Add interceptors
    _dio.interceptors.add(_AuthInterceptor(_secureStorage));
    _dio.interceptors.add(_LoggingInterceptor());
    _dio.interceptors.add(_RetryInterceptor());
  }
  
  Future<T> get<T>(
    String endpoint, {
    Map<String, dynamic>? queryParameters,
    required T Function(Map<String, dynamic>) fromJson,
  }) async {
    try {
      Response response = await _dio.get(
        endpoint,
        queryParameters: queryParameters,
      );
      return fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }
  
  Future<T> post<T>(
    String endpoint, {
    required Map<String, dynamic> data,
    required T Function(Map<String, dynamic>) fromJson,
  }) async {
    try {
      Response response = await _dio.post(endpoint, data: data);
      return fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }
}
```

### Auth Interceptor (Automatic Token Refresh)

```dart
class _AuthInterceptor extends Interceptor {
  final SecureStorage _secureStorage;
  
  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // Add access token to every request
    String? accessToken = await _secureStorage.read(key: 'access_token');
    
    if (accessToken != null) {
      options.headers['Authorization'] = 'Bearer $accessToken';
    }
    
    handler.next(options);
  }
  
  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (err.response?.statusCode == 401) {
      // Token expired: try refresh
      try {
        bool refreshed = await _refreshToken();
        if (refreshed) {
          // Retry original request
          handler.resolve(await _retry(err.requestOptions));
        } else {
          // Refresh failed: force logout
          await _secureStorage.delete(key: 'access_token');
          await _secureStorage.delete(key: 'refresh_token');
          // Navigate to login
          handler.next(err);
        }
      } catch (e) {
        handler.next(err);
      }
    } else {
      handler.next(err);
    }
  }
  
  Future<bool> _refreshToken() async {
    try {
      String? refreshToken = await _secureStorage.read(key: 'refresh_token');
      if (refreshToken == null) return false;
      
      Response response = await Dio().post(
        '${ApiConfig.baseUrl}/auth/refresh',
        data: {'refresh_token': refreshToken},
      );
      
      String newAccessToken = response.data['data']['access_token'];
      String newRefreshToken = response.data['data']['refresh_token'];
      
      await _secureStorage.write(key: 'access_token', value: newAccessToken);
      await _secureStorage.write(key: 'refresh_token', value: newRefreshToken);
      
      return true;
    } catch (e) {
      return false;
    }
  }
}
```

---

## Authentication & Token Management

### Secure Token Storage

```dart
// lib/services/secure_storage.dart

class SecureStorage {
  final FlutterSecureStorage _storage;
  
  SecureStorage({required FlutterSecureStorage storage}) 
    : _storage = storage;
  
  // Read token from secure storage
  Future<String?> getAccessToken() {
    return _storage.read(key: 'access_token');
  }
  
  Future<String?> getRefreshToken() {
    return _storage.read(key: 'refresh_token');
  }
  
  // Save tokens
  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
    required DateTime expiresAt,
  }) async {
    await Future.wait([
      _storage.write(key: 'access_token', value: accessToken),
      _storage.write(key: 'refresh_token', value: refreshToken),
      _storage.write(key: 'token_expires_at', value: expiresAt.toIso8601String()),
    ]);
  }
  
  // Clear tokens on logout
  Future<void> clearTokens() {
    return Future.wait([
      _storage.delete(key: 'access_token'),
      _storage.delete(key: 'refresh_token'),
      _storage.delete(key: 'token_expires_at'),
    ]);
  }
  
  // Check if token is expired
  Future<bool> isTokenExpired() async {
    String? expiryStr = await _storage.read(key: 'token_expires_at');
    if (expiryStr == null) return true;
    
    DateTime expiry = DateTime.parse(expiryStr);
    // Refresh 5 minutes before actual expiry
    return DateTime.now().isAfter(expiry.subtract(Duration(minutes: 5)));
  }
}
```

### Authentication Service

```dart
// lib/services/auth_service.dart

class AuthService {
  final ApiService _apiService;
  final SecureStorage _secureStorage;
  final DatabaseService _database;
  
  // Stream controller for auth state
  final _authStateController = StreamController<AuthState>.broadcast();
  Stream<AuthState> get authStateStream => _authStateController.stream;
  
  AuthService({
    required ApiService apiService,
    required SecureStorage secureStorage,
    required DatabaseService database,
  })  : _apiService = apiService,
        _secureStorage = secureStorage,
        _database = database;
  
  // Login endpoint
  Future<User> login({
    required String email,
    required String password,
  }) async {
    try {
      Map<String, dynamic> response = await _apiService.post(
        '/auth/login',
        data: {
          'email': email,
          'password': password,
          'device_id': await getDeviceId(),
          'device_name': await getDeviceName(),
        },
        fromJson: (json) => json,
      );
      
      User user = User.fromJson(response['user']);
      
      // Save tokens securely
      await _secureStorage.saveTokens(
        accessToken: response['tokens']['access_token'],
        refreshToken: response['tokens']['refresh_token'],
        expiresAt: DateTime.now().add(
          Duration(seconds: response['tokens']['expires_in'])
        ),
      );
      
      // Save user locally
      await _database.insertUser(user);
      
      // Start sync service
      await _startBackgroundSync();
      
      _authStateController.add(AuthState.authenticated(user));
      return user;
      
    } catch (e) {
      _authStateController.add(AuthState.error(e.toString()));
      rethrow;
    }
  }
  
  // Logout
  Future<void> logout() async {
    try {
      // Notify server
      await _apiService.post(
        '/auth/logout',
        data: {},
        fromJson: (json) => json,
      );
    } catch (e) {
      // Logout locally even if server fails
    } finally {
      // Clear local data
      await _secureStorage.clearTokens();
      await _database.clearAll();
      _authStateController.add(AuthState.unauthenticated());
    }
  }
  
  // Check authentication on app start
  Future<AuthState> checkAuthStatus() async {
    try {
      String? accessToken = await _secureStorage.getAccessToken();
      
      if (accessToken == null) {
        return AuthState.unauthenticated();
      }
      
      if (await _secureStorage.isTokenExpired()) {
        await _refreshToken();
      }
      
      User? user = await _database.getCurrentUser();
      if (user == null) {
        // Token exists but user not in local DB - get from server
        Map<String, dynamic> response = await _apiService.get(
          '/auth/me',
          fromJson: (json) => json,
        );
        user = User.fromJson(response);
        await _database.insertUser(user);
      }
      
      await _startBackgroundSync();
      return AuthState.authenticated(user);
      
    } catch (e) {
      return AuthState.error(e.toString());
    }
  }
  
  Future<void> _refreshToken() async {
    String? refreshToken = await _secureStorage.getRefreshToken();
    if (refreshToken == null) throw TokenException('No refresh token');
    
    Map<String, dynamic> response = await _apiService.post(
      '/auth/refresh',
      data: {'refresh_token': refreshToken},
      fromJson: (json) => json,
    );
    
    await _secureStorage.saveTokens(
      accessToken: response['access_token'],
      refreshToken: response['refresh_token'],
      expiresAt: DateTime.now().add(
        Duration(seconds: response['expires_in'])
      ),
    );
  }
}
```

---

## Offline-First Strategy

### Local SQLite Database

```dart
// lib/services/database_service.dart

class DatabaseService {
  late Database _db;
  
  Future<void> initialize() async {
    String path = join(await getDatabasesPath(), 'ratepoint.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: _onCreate,
    );
  }
  
  Future<void> _onCreate(Database db, int version) async {
    // Collections table
    await db.execute('''
      CREATE TABLE collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        business_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT,
        gps_lat REAL NOT NULL,
        gps_lng REAL NOT NULL,
        offline_sync_id TEXT UNIQUE,
        collected_at TEXT,
        sync_status TEXT DEFAULT 'pending',
        server_collection_id INTEGER,
        receipt_number TEXT,
        error_message TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
      )
    ''');
    
    // Sync attempts tracking
    await db.execute('''
      CREATE TABLE sync_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_id INTEGER NOT NULL,
        attempt_number INTEGER,
        error TEXT,
        attempted_at TEXT,
        FOREIGN KEY(collection_id) REFERENCES collections(id)
      )
    ''');
    
    // Indexes
    await db.execute(
      'CREATE INDEX idx_sync_status ON collections(sync_status)'
    );
    await db.execute(
      'CREATE INDEX idx_collected_at ON collections(collected_at)'
    );
  }
  
  // Save collection
  Future<int> insertCollection(Collection collection) async {
    return await _db.insert(
      'collections',
      collection.toMap(),
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }
  
  // Get pending collections
  Future<List<Collection>> getPendingCollections() async {
    List<Map> maps = await _db.query(
      'collections',
      where: "sync_status = ?",
      whereArgs: ['pending'],
      orderBy: 'collected_at DESC',
    );
    return maps.map((m) => Collection.fromMap(m)).toList();
  }
  
  // Mark as synced
  Future<void> markSynced(
    int localId,
    int serverId,
    String receiptNumber,
  ) async {
    await _db.update(
      'collections',
      {
        'sync_status': 'synced',
        'server_collection_id': serverId,
        'receipt_number': receiptNumber,
        'updated_at': DateTime.now().toIso8601String(),
      },
      where: 'id = ?',
      whereArgs: [localId],
    );
  }
  
  // Mark as conflict
  Future<void> markConflict(int localId, String error) async {
    await _db.update(
      'collections',
      {
        'sync_status': 'conflict',
        'error_message': error,
        'updated_at': DateTime.now().toIso8601String(),
      },
      where: 'id = ?',
      whereArgs: [localId],
    );
  }
}
```

### Sync Service

```dart
// lib/services/sync_service.dart

class SyncService {
  final ApiService _apiService;
  final DatabaseService _database;
  final ConnectivityService _connectivity;
  
  Timer? _syncTimer;
  
  SyncService({
    required ApiService apiService,
    required DatabaseService database,
    required ConnectivityService connectivity,
  })  : _apiService = apiService,
        _database = database,
        _connectivity = connectivity;
  
  // Start background sync
  void startBackgroundSync() {
    // Check connectivity
    _connectivity.connectivityStream.listen((isOnline) {
      if (isOnline) {
        _syncCollections();
      }
    });
    
    // Periodic sync every 5 minutes
    _syncTimer = Timer.periodic(Duration(minutes: 5), (_) {
      _syncCollections();
    });
  }
  
  // Sync all pending collections
  Future<void> _syncCollections() async {
    if (!_connectivity.isOnline) return;
    
    List<Collection> pending = await _database.getPendingCollections();
    if (pending.isEmpty) return;
    
    // Batch sync
    List<Map<String, dynamic>> collectionsData = 
      pending.map((c) => c.toMapForSync()).toList();
    
    try {
      Map<String, dynamic> response = await _apiService.post(
        '/agent/collections/bulk',
        data: {'collections': collectionsData},
        fromJson: (json) => json,
      );
      
      // Process results
      for (var result in response['results']) {
        String offlineSyncId = result['offline_sync_id'];
        Collection? collection = pending.firstWhereOrNull(
          (c) => c.offlineSyncId == offlineSyncId
        );
        
        if (collection == null) continue;
        
        if (result['status'] == 'synced') {
          await _database.markSynced(
            collection.id!,
            result['server_id'],
            result['receipt_number'],
          );
          
          // Show notification
          _showSyncSuccess(collection);
          
        } else if (result['status'] == 'skipped') {
          // Duplicate detected
          await _database.markSynced(
            collection.id!,
            0,  // No server ID for duplicate
            '',
          );
          
        } else if (result['status'] == 'failed') {
          // Record failed attempt
          await _recordSyncAttempt(collection.id!, result['error']);
          
          // Retry later
          await Future.delayed(Duration(minutes: 5));
          _syncCollections();
        }
      }
      
    } catch (e) {
      // Network error - will retry on next connectivity check
      print('Sync error: $e');
    }
  }
  
  Future<void> _recordSyncAttempt(
    int collectionId,
    String error,
  ) async {
    // Store attempt for debugging
    await _database.recordSyncAttempt(collectionId, error);
  }
  
  void _showSyncSuccess(Collection collection) {
    // Show local notification
    FlutterLocalNotifications.show(
      title: 'Sync Successful',
      body: 'Collection synced: ${collection.receiptNumber}',
    );
  }
  
  void stopBackgroundSync() {
    _syncTimer?.cancel();
  }
}
```

---

## GPS & Location Services

### Location Service

```dart
// lib/services/location_service.dart

class LocationService {
  final Geolocator _geolocator = Geolocator();
  LocationSettings? _locationSettings;
  StreamSubscription<Position>? _positionStream;
  
  Future<void> initialize() async {
    // Request permissions
    LocationPermission permission = 
      await Geolocator.checkPermission();
    
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw LocationPermissionException(
          'Location permission is required'
        );
      }
    }
    
    if (permission == LocationPermission.deniedForever) {
      throw LocationPermissionException(
        'Location permission is permanently denied'
      );
    }
    
    _locationSettings = LocationSettings(
      accuracy: LocationAccuracy.bestForNavigation,
      distanceFilter: 10,  // Update when moved 10m
    );
  }
  
  // Get current position
  Future<Position> getCurrentPosition() async {
    return await Geolocator.getCurrentPosition();
  }
  
  // Stream current position (for live tracking)
  Stream<Position> getPositionStream() {
    return Geolocator.getPositionStream(
      locationSettings: _locationSettings,
    );
  }
  
  // Calculate distance between two points
  static double calculateDistance(
    double lat1,
    double lon1,
    double lat2,
    double lon2,
  ) {
    return Geolocator.distanceBetween(lat1, lon1, lat2, lon2) / 1000;
  }
  
  // Stop tracking
  void stopTracking() {
    _positionStream?.cancel();
  }
}
```

### GPS Accuracy Handler

```dart
// lib/utils/location_validator.dart

class LocationValidator {
  // Check if location is acceptable accuracy
  static bool isAcceptable(
    Position position, {
    double maxAccuracy = 100,  // meters
  }) {
    return position.accuracy <= maxAccuracy;
  }
  
  // Check if within geofence
  static bool isWithinGeofence(
    Position position,
    GeoPoint business,
    double radiusKm = 0.5,
  ) {
    double distance = LocationService.calculateDistance(
      position.latitude,
      position.longitude,
      business.latitude,
      business.longitude,
    );
    
    return distance <= radiusKm;
  }
  
  // Detect impossible travel
  static bool isImpossibleTravel(
    Position previous,
    Position current,
    Duration timeDiff,
  ) {
    // > 50 km in < 60 seconds is impossible
    double distance = LocationService.calculateDistance(
      previous.latitude,
      previous.longitude,
      current.latitude,
      current.longitude,
    );
    
    return distance > 50 && timeDiff.inSeconds < 60;
  }
}
```

---

## Collection Recording

### Collection Model

```dart
// lib/models/collection.dart

class Collection {
  final int? id;
  final int businessId;
  final double amount;
  final String paymentMethod;
  final double gpsLat;
  final double gpsLng;
  final String? offlineSyncId;
  final DateTime collectedAt;
  final String? syncStatus;
  final String? receiptNumber;
  final DateTime createdAt;
  
  Collection({
    this.id,
    required this.businessId,
    required this.amount,
    required this.paymentMethod,
    required this.gpsLat,
    required this.gpsLng,
    this.offlineSyncId,
    required this.collectedAt,
    this.syncStatus = 'pending',
    this.receiptNumber,
    required this.createdAt,
  });
  
  // Generate unique offline sync ID
  static String generateOfflineSyncId() {
    return Uuid().v4();
  }
  
  Map<String, dynamic> toMap() => {
    'id': id,
    'business_id': businessId,
    'amount': amount,
    'payment_method': paymentMethod,
    'gps_lat': gpsLat,
    'gps_lng': gpsLng,
    'offline_sync_id': offlineSyncId,
    'collected_at': collectedAt.toIso8601String(),
    'sync_status': syncStatus,
    'receipt_number': receiptNumber,
    'created_at': createdAt.toIso8601String(),
  };
  
  Map<String, dynamic> toMapForSync() => {
    'business_id': businessId,
    'amount': amount,
    'payment_method': paymentMethod,
    'gps_lat': gpsLat,
    'gps_lng': gpsLng,
    'offline_sync_id': offlineSyncId,
    'collected_at': collectedAt.toIso8601String(),
  };
  
  static Collection fromMap(Map<String, dynamic> map) {
    return Collection(
      id: map['id'],
      businessId: map['business_id'],
      amount: map['amount'],
      paymentMethod: map['payment_method'] ?? 'cash',
      gpsLat: map['gps_lat'],
      gpsLng: map['gps_lng'],
      offlineSyncId: map['offline_sync_id'],
      collectedAt: DateTime.parse(map['collected_at']),
      syncStatus: map['sync_status'],
      receiptNumber: map['receipt_number'],
      createdAt: DateTime.parse(map['created_at']),
    );
  }
}
```

### Collection Form Screen

```dart
// lib/screens/collection_form_screen.dart

class CollectionFormScreen extends StatefulWidget {
  final Business business;
  
  const CollectionFormScreen({required this.business});
  
  @override
  State<CollectionFormScreen> createState() => _CollectionFormState();
}

class _CollectionFormState extends State<CollectionFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _locationService = locator<LocationService>();
  final _databaseService = locator<DatabaseService>();
  
  Position? _currentPosition;
  bool _isSubmitting = false;
  
  @override
  void initState() {
    super.initState();
    _getLocation();
  }
  
  Future<void> _getLocation() async {
    try {
      Position position = await _locationService.getCurrentPosition();
      
      // Validate accuracy
      if (!LocationValidator.isAcceptable(position)) {
        _showDialog(
          'Poor GPS Signal',
          'GPS accuracy is ${position.accuracy.toStringAsFixed(1)}m. '
          'Please try again in an open area.',
        );
        return;
      }
      
      // Check geofence
      if (!LocationValidator.isWithinGeofence(position, widget.business)) {
        _showDialog(
          'Location Mismatch',
          'You are not at the business location. '
          'Please move closer and try again.',
        );
        return;
      }
      
      setState(() => _currentPosition = position);
      
    } catch (e) {
      _showError('Location Error', e.toString());
    }
  }
  
  Future<void> _submitCollection() async {
    if (!_formKey.currentState!.validate()) return;
    if (_currentPosition == null) {
      _showError('Error', 'Location not available');
      return;
    }
    
    setState(() => _isSubmitting = true);
    
    try {
      // Create collection
      Collection collection = Collection(
        businessId: widget.business.id,
        amount: double.parse(_amountController.text),
        paymentMethod: 'cash',
        gpsLat: _currentPosition!.latitude,
        gpsLng: _currentPosition!.longitude,
        offlineSyncId: Collection.generateOfflineSyncId(),
        collectedAt: DateTime.now(),
        createdAt: DateTime.now(),
      );
      
      // Save locally
      int collectionId = await _databaseService.insertCollection(collection);
      
      // Try to sync immediately if online
      if (await _isOnline()) {
        await _syncSingleCollection(collection);
      }
      
      // Show success
      _showDialog(
        'Success',
        'Collection recorded successfully.\nReceipt: ${collection.receiptNumber}',
      ).then((_) => Navigator.pop(context, collection));
      
    } catch (e) {
      _showError('Error', e.toString());
    } finally {
      setState(() => _isSubmitting = false);
    }
  }
  
  Future<bool> _isOnline() async {
    ConnectivityResult result = 
      await Connectivity().checkConnectivity();
    return result != ConnectivityResult.none;
  }
  
  Future<void> _syncSingleCollection(Collection collection) async {
    // Send to server
    Map<String, dynamic> response = 
      await locator<ApiService>().post(
        '/agent/collections',
        data: collection.toMapForSync(),
        fromJson: (json) => json,
      );
    
    // Update local record
    await _databaseService.markSynced(
      collection.id!,
      response['data']['collection_id'],
      response['data']['receipt_number'],
    );
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Record Collection')),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              // Business info
              Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Business', style: TextStyle(fontWeight: FontWeight.bold)),
                      Text(widget.business.name),
                      SizedBox(height: 8),
                      Text('Owner: ${widget.business.ownerName}'),
                      SizedBox(height: 8),
                      Text('Fee: GHS ${widget.business.feeAmount.toStringAsFixed(2)}'),
                    ],
                  ),
                ),
              ),
              SizedBox(height: 16),
              
              // Amount field
              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'Amount (GHS)',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Amount is required';
                  }
                  if (double.tryParse(value) == null) {
                    return 'Enter a valid number';
                  }
                  return null;
                },
              ),
              SizedBox(height: 16),
              
              // GPS location
              if (_currentPosition != null)
                Text(
                  'Location: ${_currentPosition!.latitude.toStringAsFixed(4)}, '
                  '${_currentPosition!.longitude.toStringAsFixed(4)}\n'
                  'Accuracy: ${_currentPosition!.accuracy.toStringAsFixed(1)}m',
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                ),
              
              // Submit button
              SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submitCollection,
                  child: _isSubmitting
                    ? CircularProgressIndicator()
                    : Text('Record Collection'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

---

## Networking & Sync

### Retry Strategy with Exponential Backoff

```dart
// lib/utils/retry_helper.dart

class RetryHelper {
  static Future<T> retryWithExponentialBackoff<T>(
    Future<T> Function() operation, {
    int maxRetries = 5,
  }) async {
    int attempt = 0;
    
    while (attempt < maxRetries) {
      try {
        return await operation();
      } catch (e) {
        attempt++;
        
        if (attempt >= maxRetries) {
          rethrow;
        }
        
        // Wait before retry
        int delaySeconds = pow(2, attempt - 1).toInt();
        await Future.delayed(Duration(seconds: delaySeconds));
      }
    }
  }
}

// Usage
try {
  Collection collection = await RetryHelper.retryWithExponentialBackoff(
    () => _syncSingleCollection(collection),
  );
} catch (e) {
  print('Sync failed after retries: $e');
}
```

### Connectivity Monitoring

```dart
// lib/services/connectivity_service.dart

class ConnectivityService {
  final Connectivity _connectivity = Connectivity();
  final _connectivityController = 
    StreamController<bool>.broadcast();
  
  Stream<bool> get connectivityStream => 
    _connectivityController.stream;
  
  bool _isOnline = true;
  bool get isOnline => _isOnline;
  
  void initialize() {
    _connectivity.onConnectivityChanged
      .listen((result) {
        _isOnline = result != ConnectivityResult.none;
        _connectivityController.add(_isOnline);
      });
  }
}
```

---

## Battery & Performance

### Battery Optimization

```dart
// lib/services/battery_service.dart

class BatteryService {
  final Battery _battery = Battery();
  
  Future<Duration> getHeartbeatInterval() async {
    BatteryLevel batteryLevel = await _battery.batteryLevel ?? 100;
    
    if (batteryLevel < 10) {
      return Duration(minutes: 5);  // Slow down sync
    } else if (batteryLevel < 25) {
      return Duration(minutes: 2);
    } else if (batteryLevel < 50) {
      return Duration(minutes: 1);
    }
    
    return Duration(seconds: 30);  // Normal
  }
  
  Future<void> activateLowBatteryMode() async {
    // Disable expensive operations
    // - Stop GPS polling
    // - Reduce UI refresh
    // - Disable push notifications sound
  }
}
```

### Performance Monitoring

```dart
// lib/utils/performance_monitor.dart

class PerformanceMonitor {
  static Future<T> measureAsync<T>(
    String label,
    Future<T> Function() operation,
  ) async {
    Stopwatch stopwatch = Stopwatch()..start();
    
    try {
      return await operation();
    } finally {
      stopwatch.stop();
      print('$label took ${stopwatch.elapsedMilliseconds}ms');
      
      if (stopwatch.elapsedMilliseconds > 3000) {
        // Log slow operation
        _logSlowOperation(label, stopwatch.elapsedMilliseconds);
      }
    }
  }
  
  static void _logSlowOperation(String label, int duration) {
    // Send to analytics/crash reporting
  }
}
```

---

## Error Handling

### Custom Exceptions

```dart
// lib/exceptions/exceptions.dart

class AppException implements Exception {
  final String message;
  final String? code;
  
  AppException({required this.message, this.code});
  
  @override
  String toString() => message;
}

class TokenException extends AppException {
  TokenException(String message) : super(message: message);
}

class LocationException extends AppException {
  LocationException(String message) : super(message: message);
}

class LocationPermissionException extends LocationException {
  LocationPermissionException(String message) : super(message);
}

class SyncException extends AppException {
  SyncException(String message) : super(message: message);
}

class NetworkException extends AppException {
  NetworkException(String message) 
    : super(message: message, code: 'NETWORK_ERROR');
}
```

### Error Handler Utility

```dart
// lib/utils/error_handler.dart

class ErrorHandler {
  static String getErrorMessage(dynamic error) {
    if (error is TokenException) {
      return 'Session expired. Please login again.';
    } else if (error is LocationPermissionException) {
      return 'Location permission is required to record collections.';
    } else if (error is NetworkException) {
      return 'Network connection error. Check your internet.';
    } else if (error is DioException) {
      if (error.response?.statusCode == 422) {
        return 'Validation error: Check your input.';
      } else if (error.response?.statusCode == 429) {
        return 'Too many requests. Please try again later.';
      }
      return 'Server error. Please try again.';
    }
    return 'An unexpected error occurred.';
  }
  
  static void showErrorDialog(
    BuildContext context,
    dynamic error,
  ) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('Error'),
        content: Text(getErrorMessage(error)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('OK'),
          ),
        ],
      ),
    );
  }
}
```

---

## Testing

### Unit Tests

```dart
// test/services/auth_service_test.dart

void main() {
  group('AuthService', () {
    late MockApiService mockApiService;
    late MockSecureStorage mockSecureStorage;
    late AuthService authService;
    
    setUp(() {
      mockApiService = MockApiService();
      mockSecureStorage = MockSecureStorage();
      authService = AuthService(
        apiService: mockApiService,
        secureStorage: mockSecureStorage,
      );
    });
    
    test('login saves tokens securely', () async {
      when(mockApiService.post(...)).thenAnswer(
        (_) async => {'tokens': {...}},
      );
      
      User user = await authService.login(
        email: 'test@example.com',
        password: 'password123',
      );
      
      verify(mockSecureStorage.saveTokens(...)).called(1);
      expect(user.email, 'test@example.com');
    });
    
    test('logout clears tokens', () async {
      when(mockApiService.post(...)).thenAnswer((_) async => {});
      
      await authService.logout();
      
      verify(mockSecureStorage.clearTokens()).called(1);
    });
  });
}
```

### Widget Tests

```dart
// test/screens/collection_form_test.dart

void main() {
  testWidgets('Collection form validates amount', (tester) async {
    await tester.pumpWidget(MyApp());
    
    await tester.tap(find.byType(ElevatedButton));
    await tester.pumpWidget(CollectionFormScreen(...));
    
    // Leave amount empty
    await tester.tap(find.byType(ElevatedButton));
    await tester.pumpAndSettle();
    
    expect(find.text('Amount is required'), findsOneWidget);
  });
}
```

---

## Deployment

### Android Deployment

```gradle
// android/app/build.gradle

android {
    compileSdkVersion 33
    
    defaultConfig {
        applicationId "com.municipality.ratepoint"
        minSdkVersion 21
        targetSdkVersion 33
    }
    
    signingConfigs {
        release {
            keyAlias System.getenv("KEY_ALIAS")
            keyPassword System.getenv("KEY_PASSWORD")
            storeFile file(System.getenv("KEYSTORE_PATH"))
            storePassword System.getenv("KEYSTORE_PASSWORD")
        }
    }
    
    buildTypes {
        release {
            signingConfig signingConfigs.release
        }
    }
}
```

### iOS Deployment

```swift
// ios/Runner/Info.plist

<key>NSLocationWhenInUseUsageDescription</key>
<string>We need your location to verify collection sites</string>

<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>We need your location for background tracking</string>
```

### Build & Release

```bash
# Build release APK
flutter build apk --release

# Build release iOS
flutter build ios --release

# Create bundle for Play Store
flutter build appbundle

# Sign and submit
bundletool build-apks --bundle=app-release.aab

# Upload to Play Store via console
```

---

## Best Practices Summary

✅ Always use HTTPS  
✅ Store tokens securely  
✅ Implement automatic token refresh  
✅ Provide offline-first experience  
✅ Handle network failures gracefully  
✅ Validate GPS accuracy before accepting  
✅ Implement retry logic  
✅ Monitor battery usage  
✅ Log important events for debugging  
✅ Test thoroughly before release  

---

**Document Version:** 2.0  
**Last Updated:** May 2026
