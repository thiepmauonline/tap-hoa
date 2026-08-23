<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Màn hình Bán hàng POS - {{ auth()->user()->store->name ?? 'Tạp Hóa' }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            height: 100vh;
            overflow: hidden;
        }

        .pos-header {
            height: 56px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
        }

        .pos-body {
            height: calc(100vh - 56px);
        }

        .pos-left {
            height: 100%;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #cbd5e1;
            background: #ffffff;
        }

        .pos-right {
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }

        .product-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
            background: #ffffff;
        }

        .product-card:hover {
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
    </style>

    @livewireStyles
</head>
<body>

    <!-- POS Top Header -->
    <div class="pos-header">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light btn-sm text-decoration-none">
                <i class="bi bi-arrow-left"></i> Về Quản trị
            </a>
            <span class="fw-bold fs-5 text-warning">
                <i class="bi bi-calculator-fill me-1"></i> BÁN HÀNG TẠI QUẦY (POS)
            </span>
            <span class="badge bg-secondary ms-2">{{ auth()->user()->store->name ?? 'Cửa hàng' }}</span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="text-light fs-7">Thu ngân: <strong>{{ auth()->user()->name }}</strong></span>
            <a href="{{ route('orders.index') }}" target="_blank" class="btn btn-dark btn-sm">
                <i class="bi bi-clock-history me-1"></i> Lịch sử đơn
            </a>
        </div>
    </div>

    <!-- POS Main Container -->
    <div class="container-fluid pos-body p-0">
        {{ $slot }}
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @livewireScripts
</body>
</html>
