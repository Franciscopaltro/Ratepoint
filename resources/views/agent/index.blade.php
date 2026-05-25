<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ratepoint Agent - Revenue Collection</title>
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gov-green: #1B3022;
            --gov-gold: #F4B400;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
            padding-bottom: 80px;
        }
        .header {
            background-color: var(--gov-green);
            color: white;
            padding: 20px;
            border-radius: 0 0 25px 25px;
            margin-bottom: 20px;
        }
        .business-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 10px;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 15px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .nav-item {
            text-align: center;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .nav-item.active {
            color: var(--gov-green);
        }
        .btn-collect {
            background-color: var(--gov-green);
            color: white;
            border-radius: 12px;
            font-weight: 600;
        }
        .sync-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: var(--gov-gold);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            border: none;
            z-index: 999;
        }
        .offline-banner {
            display: none;
            background: #ffc107;
            text-align: center;
            padding: 5px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<div id="offline-status" class="offline-banner">
    <i class="fas fa-wifi-slash me-1"></i> You are currently offline. Transactions will be stored locally.
</div>

<div class="header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Hello, {{ Auth::user()->name }}</h5>
            <small>Zone: {{ Auth::user()->zone->name ?? 'Unassigned' }}</small>
        </div>
        <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=F4B400&color=1B3022" class="rounded-circle" width="45">
    </div>
    <div class="mt-4 p-3 bg-white bg-opacity-10 rounded-3">
        <div class="row text-center">
            <div class="col-6 border-end">
                <div class="small opacity-75">Today's Target</div>
                <div class="h5 mb-0">GH₵ 1,200</div>
            </div>
            <div class="col-6">
                <div class="small opacity-75">Collected</div>
                <div class="h5 mb-0">GH₵ <span id="daily-total">0.00</span></div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="mb-3">
        <input type="text" class="form-control rounded-4 py-2 border-0 shadow-sm" placeholder="🔍 Search businesses...">
    </div>

    <div id="business-list">
        <!-- Business cards will be injected by JS -->
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-success" role="status"></div>
            <p class="mt-2">Loading businesses...</p>
        </div>
    </div>
</div>

<button id="sync-btn" class="sync-btn">
    <i class="fas fa-sync-alt"></i>
    <span id="sync-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none">0</span>
</button>

<div class="bottom-nav">
    <a href="#" class="nav-item active"><i class="fas fa-home d-block h5 mb-1"></i>Home</a>
    <a href="#" class="nav-item"><i class="fas fa-history d-block h5 mb-1"></i>History</a>
    <a href="#" class="nav-item" id="btn-scan"><i class="fas fa-qrcode d-block h5 mb-1"></i>Verify</a>
    <a href="#" class="nav-item"><i class="fas fa-user d-block h5 mb-1"></i>Profile</a>
</div>

<!-- Collection Modal -->
<div class="modal fade" id="collectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="business-name-modal"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-4">Confirm payment details below</p>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Amount to Collect (GH₵)</label>
                    <input type="number" id="collect-amount" class="form-control form-control-lg rounded-3" step="0.01">
                </div>
                <div id="gps-status" class="small text-muted mb-3">
                    <i class="fas fa-location-arrow"></i> Capturing GPS...
                </div>
                <button id="confirm-payment" class="btn btn-collect w-100 py-3 shadow">
                    <i class="fas fa-check-circle me-2"></i> GENERATE RECEIPT
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4 rounded-4 border-0">
            <div class="text-success mb-3"><i class="fas fa-check-circle fa-4x"></i></div>
            <h4 class="fw-bold">Payment Successful</h4>
            <p class="text-muted">Digital receipt generated</p>
            <hr>
            <div id="receipt-qr" class="mb-3 mx-auto" style="width:150px; height:150px; background:#eee"></div>
            <div class="h5 fw-bold mb-1" id="receipt-id">REC-XXXX-2024</div>
            <div class="small text-muted mb-3" id="receipt-time"></div>
            <button class="btn btn-collect w-100 py-2" data-bs-dismiss="modal">DONE</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>

<script>
    // Offline Storage Logic (IndexedDB or LocalStorage)
    let businesses = [];
    let pendingCollections = JSON.parse(localStorage.getItem('pendingCollections')) || [];
    let currentBusiness = null;
    let currentGPS = { lat: 0, lng: 0 };

    // Update sync badge
    function updateSyncBadge() {
        const badge = document.getElementById('sync-count');
        if (pendingCollections.length > 0) {
            badge.style.display = 'block';
            badge.innerText = pendingCollections.length;
        } else {
            badge.style.display = 'none';
        }
    }

    // Check online status
    window.addEventListener('online', () => document.getElementById('offline-status').style.display = 'none');
    window.addEventListener('offline', () => document.getElementById('offline-status').style.display = 'block');

    // Load Businesses (Mocking API for now)
    function loadBusinesses() {
        // In real app: fetch('/api/agent/businesses').then(...)
        businesses = [
            { id: 1, name: "Ama's Provision Shop", owner: "Ama Serwaa", amount: 150.00, status: 'unpaid' },
            { id: 2, name: "Kofi Brothers Garage", owner: "Kofi Mensah", amount: 300.00, status: 'unpaid' },
            { id: 3, name: "City Center Salon", owner: "Mary Appiah", amount: 75.00, status: 'paid' },
        ];
        renderBusinesses();
    }

    function renderBusinesses() {
        const list = document.getElementById('business-list');
        list.innerHTML = businesses.map(b => `
            <div class="card business-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0">${b.name}</h6>
                        <span class="badge status-badge ${b.status == 'paid' ? 'bg-success' : 'bg-warning text-dark'}">
                            ${b.status.toUpperCase()}
                        </span>
                    </div>
                    <div class="small text-muted mb-3">Owner: ${b.owner}</div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="h6 mb-0">GH₵ ${b.amount.toFixed(2)}</div>
                        ${b.status == 'unpaid' ? `<button class="btn btn-sm btn-collect px-3" onclick="openCollectModal(${b.id})">Collect</button>` : '<i class="fas fa-check-circle text-success"></i>'}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function openCollectModal(id) {
        currentBusiness = businesses.find(b => b.id === id);
        document.getElementById('business-name-modal').innerText = currentBusiness.name;
        document.getElementById('collect-amount').value = currentBusiness.amount;
        
        // Capture GPS
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                currentGPS = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                document.getElementById('gps-status').innerHTML = `<i class="fas fa-map-marker-alt text-success"></i> GPS Locked`;
            });
        }
        
        new bootstrap.Modal(document.getElementById('collectModal')).show();
    }

    document.getElementById('confirm-payment').onclick = function() {
        const amount = document.getElementById('collect-amount').value;
        const receiptId = 'REC-' + Math.random().toString(36).substr(2, 9).toUpperCase() + '-' + new Date().getFullYear();
        
        const collection = {
            id: Date.now(),
            business_id: currentBusiness.id,
            amount: amount,
            gps: currentGPS,
            receipt_number: receiptId,
            timestamp: new Date().toISOString()
        };

        // Save locally
        pendingCollections.push(collection);
        localStorage.setItem('pendingCollections', JSON.stringify(pendingCollections));
        updateSyncBadge();

        // Update local business status
        currentBusiness.status = 'paid';
        renderBusinesses();

        // Show Receipt
        bootstrap.Modal.getInstance(document.getElementById('collectModal')).hide();
        document.getElementById('receipt-id').innerText = receiptId;
        document.getElementById('receipt-time').innerText = new Date().toLocaleString();
        
        const qrContainer = document.getElementById('receipt-qr');
        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
            text: receiptId,
            width: 150,
            height: 150
        });

        new bootstrap.Modal(document.getElementById('receiptModal')).show();
    };

    // Sync Logic
    document.getElementById('sync-btn').onclick = function() {
        if (!navigator.onLine) {
            alert("No internet connection. Please try again when online.");
            return;
        }

        if (pendingCollections.length === 0) {
            alert("Everything is up to date!");
            return;
        }

        this.querySelector('i').classList.add('fa-spin');
        
        // In real app: Send to /api/agent/sync-bulk
        setTimeout(() => {
            alert(`Successfully synced ${pendingCollections.length} transactions!`);
            pendingCollections = [];
            localStorage.setItem('pendingCollections', JSON.stringify(pendingCollections));
            updateSyncBadge();
            this.querySelector('i').classList.remove('fa-spin');
        }, 2000);
    };

    // Init
    loadBusinesses();
    updateSyncBadge();

</script>
</body>
</html>
