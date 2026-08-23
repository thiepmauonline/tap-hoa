<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Đăng nhập' }} - Hệ thống SaaS Tạp hóa Tích hợp AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }

        .auth-header {
            background: #4f46e5;
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
        }
    </style>

    @livewireStyles
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <i class="bi bi-shop fs-1 text-warning"></i>
            <h4 class="fw-bold mt-2 mb-1">TẠP HÓA SAAS AI</h4>
            <p class="mb-0 text-white-50 fs-7">Nền tảng Quản lý Cửa hàng & Trợ lý AI Phân tích</p>
        </div>

        <div class="p-4">
            {{ $slot }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @livewireScripts
</body>
</html>
