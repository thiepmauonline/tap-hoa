<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Quản lý Cửa hàng Tạp hóa' }} - {{ auth()->user()->store->name ?? 'SaaS POS' }}</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f8fafc;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: #0f172a;
            color: #94a3b8;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        #sidebar .brand {
            padding: 1.15rem 1.25rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        #sidebar .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.5rem 0 2rem;
        }

        /* Custom slim scrollbar for sidebar nav */
        #sidebar .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }
        #sidebar .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }
        #sidebar .sidebar-nav::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        #sidebar .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }

        #sidebar .nav-link {
            color: #94a3b8;
            padding: 0.65rem 1rem;
            margin: 0.15rem 0.65rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }

        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: #ffffff;
            background: var(--primary-color);
        }

        #sidebar .nav-heading {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 0.9rem 1.25rem 0.35rem;
        }

        /* Main Content Styling */
        #content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-custom {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
        }

        .main-container {
            padding: 1.75rem;
            flex: 1;
        }

        /* Card Custom Styling */
        .card-custom {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Badge Pills */
        .badge-soft-primary { background: #e0e7ff; color: #4338ca; }
        .badge-soft-success { background: #dcfce7; color: #15803d; }
        .badge-soft-warning { background: #fef9c3; color: #a16207; }
        .badge-soft-danger  { background: #fee2e2; color: #b91c1c; }

        /* Utility font sizes */
        .fs-7 { font-size: 0.825rem !important; }
        .fs-8 { font-size: 0.75rem !important; }
    </style>

    @livewireStyles
</head>
<body>

    <!-- Sidebar Bar -->
    <div id="sidebar">
        <div class="brand">
            <i class="bi bi-shop fs-4 text-warning flex-shrink-0"></i>
            <div style="min-width: 0;">
                <div class="text-truncate fw-bold">{{ auth()->user()->store->name ?? 'TapHoa SaaS' }}</div>
                <small class="text-secondary fw-normal d-block text-truncate" style="font-size: 0.75rem;">Phần mềm quản lý bán hàng</small>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-heading">Tổng quan</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i> Bảng điều khiển
            </a>
            @if(auth()->user()->isManager())
            <a href="{{ route('reports.business') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line-fill"></i> Báo cáo kinh doanh
            </a>
            @endif
            <a href="{{ route('pos') }}" class="nav-link bg-success text-white my-2 fw-semibold shadow-sm" target="_blank">
                <i class="bi bi-calculator"></i> Bán hàng (POS)
            </a>

            <div class="nav-heading">Quản lý kho & Nhập bán</div>
            @if(auth()->user()->isManager())
            <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i> Sản phẩm
            </a>
            <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                <i class="bi bi-tags"></i> Danh mục
            </a>
            <a href="{{ route('inventories.index') }}" class="nav-link {{ request()->routeIs('inventories.*') ? 'active' : '' }}">
                <i class="bi bi-stack"></i> Tồn kho & Cảnh báo
            </a>
            <a href="{{ route('purchase-orders.index') }}" class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                <i class="bi bi-cart-check"></i> Nhập hàng kho
            </a>

            <div class="nav-heading">Đối tác & Đơn bán</div>
            @endif
            <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> Hóa đơn đã bán
            </a>
            @if(auth()->user()->isManager())
            <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="bi bi-truck"></i> Nhà cung cấp
            </a>
            @endif
            <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Khách hàng tích điểm
            </a>

            <div class="nav-heading">Trợ lý & Hệ thống</div>
            <a href="{{ route('ai.assistant') }}" class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}">
                <i class="bi bi-robot"></i> Trợ lý phân tích
            </a>

            @if(auth()->user()->isOwner())
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-person-gear"></i> Nhân viên & Phân quyền
            </a>
            @endif
        </div>
    </div>

    <!-- Main Content Right Area -->
    <div id="content">
        <!-- Topbar Navbar -->
        <nav class="navbar navbar-expand navbar-custom sticky-top">
            <div class="container-fluid">
                <span class="navbar-text fw-semibold text-dark fs-5">
                    {{ $headerTitle ?? 'Hệ thống Quản lý Bán lẻ' }}
                </span>

                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <!-- Store Info Badge -->
                    <li class="nav-item">
                        <span class="badge badge-soft-primary px-3 py-2 rounded-pill fs-7">
                            <i class="bi bi-building me-1"></i> {{ auth()->user()->store->name ?? 'Cửa hàng' }}
                        </span>
                    </li>

                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:36px; height:36px;">
                                {{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <div class="d-none d-md-block text-start">
                                <div class="fw-semibold text-dark fs-7 mb-0">{{ auth()->user()->name ?? 'User' }}</div>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    {{ auth()->user()->role == 'owner' ? 'Chủ cửa hàng' : 'Nhân viên' }}
                                </small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Hồ sơ tài khoản</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Body Content -->
        <div class="main-container">
            <!-- Flash Message Alerts -->
            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    @livewireScripts
</body>
</html>
