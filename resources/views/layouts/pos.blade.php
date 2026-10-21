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
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .pos-header {
            min-height: 56px;
            flex-shrink: 0;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1.25rem;
            gap: 0.75rem;
        }

        .pos-body {
            flex: 1;
            min-height: 0;
        }

        .pos-workspace {
            display: grid;
            grid-template-columns: minmax(0, 7fr) minmax(420px, 5fr);
        }

        .pos-workspace > .pos-left, .pos-workspace > .pos-right {
            width: auto;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
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

        .pos-search, .pos-cart-heading { flex-shrink: 0; }
        .pos-products { min-height: 0; }
        .pos-cart { flex: 1 1 0; min-height: 140px; overscroll-behavior: contain; }
        .pos-payment {
            flex: 0 1 auto;
            min-height: 0;
            max-height: 60%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pos-payment-details {
            min-height: 0;
            padding: 0 0.25rem;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .pos-checkout { flex-shrink: 0; margin-top: 0.75rem; }

        .pos-cart table { min-width: 360px; }
        .pos-cart th, .pos-cart td { white-space: nowrap; }
        .pos-cart .pos-quantity { width: 76px; min-width: 64px; }
        .pos-item-name { max-width: 140px; }
        .pos-header > div { min-width: 0; }
        .pos-store-name { max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
        .fs-7 { font-size: 0.825rem !important; }
        .fs-8 { font-size: 0.75rem !important; }

        @media screen and (max-width: 1399.98px), screen and (max-height: 800px) {
            html { font-size: 14px; }
            .pos-header { padding: 0.5rem 1rem; }
            .pos-header > div { gap: 0.75rem !important; }
            .pos-left, .pos-right { padding: 0.75rem !important; }
            .pos-payment { padding: 0.75rem !important; }
            .pos-left .form-control-lg, .pos-payment .form-control-lg { font-size: 1rem; padding: 0.4rem 0.6rem; }
            .product-card { padding: 0.6rem; }
        }

        @media screen and (max-width: 1199.98px) {
            .pos-workspace { grid-template-columns: minmax(0, 1fr) minmax(390px, 1fr); }
            .pos-store-name, .pos-unit-price { display: none; }
            .pos-header .pos-title { font-size: 1rem !important; }
        }

        @media screen and (max-width: 767.98px) {
            body { height: auto; min-height: 100dvh; overflow: auto; }
            .pos-header { flex-wrap: wrap; }
            .pos-workspace { grid-template-columns: minmax(0, 1fr); height: auto !important; }
            .pos-left { height: 45dvh; min-height: 260px !important; }
            .pos-right { height: auto; overflow: visible !important; }
            .pos-cart { flex: none; height: 240px; }
            .pos-payment { max-height: none; overflow: visible; }
            .pos-payment-details { overflow: visible; }
        }

        .product-card:hover {
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        #print-receipt {
            display: none;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 3mm;
            }

            html, body {
                display: block;
                width: 80mm;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
                color: #000 !important;
                font-family: Arial, sans-serif !important;
            }

            body * {
                visibility: hidden !important;
            }

            #print-receipt,
            #print-receipt * {
                visibility: visible !important;
            }

            #print-receipt {
                display: block !important;
                position: fixed;
                inset: 0 auto auto 0;
                width: 74mm;
                padding: 0;
                margin: 0;
                background: #fff;
                font-size: 11px;
                line-height: 1.35;
            }

            #print-receipt .receipt-title { font-size: 16px; font-weight: 700; text-align: center; }
            #print-receipt .receipt-center { text-align: center; }
            #print-receipt .receipt-line { border-top: 1px dashed #000; margin: 7px 0; }
            #print-receipt .receipt-row { display: flex; justify-content: space-between; gap: 8px; }
            #print-receipt .receipt-row > :last-child { text-align: right; white-space: nowrap; }
            #print-receipt .receipt-item { margin-bottom: 5px; }
            #print-receipt .receipt-total { font-size: 14px; font-weight: 700; }
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
            <span class="pos-title fw-bold fs-5 text-warning">
                <i class="bi bi-calculator-fill me-1"></i> BÁN HÀNG TẠI QUẦY (POS)
            </span>
            <span class="pos-store-name badge bg-secondary ms-2">{{ auth()->user()->store->name ?? 'Cửa hàng' }}</span>
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
    <script>
        function printPosReceipt() {
            const receipt = document.getElementById('print-receipt');
            if (!receipt) return;

            const measuringCopy = receipt.cloneNode(true);
            Object.assign(measuringCopy.style, {
                display: 'block', visibility: 'hidden', position: 'fixed',
                left: '-10000px', top: '0', width: '280px', height: 'auto',
                fontFamily: 'Arial, sans-serif', fontSize: '11px', lineHeight: '1.35'
            });
            document.body.appendChild(measuringCopy);
            const contentHeightMm = Math.ceil(measuringCopy.scrollHeight * 25.4 / 96) + 8;
            measuringCopy.remove();

            const pageHeightMm = Math.max(60, contentHeightMm);
            const printWindow = window.open('', '_blank', 'width=420,height=700');
            if (!printWindow) {
                alert('Trình duyệt đang chặn cửa sổ in. Vui lòng cho phép popup cho trang này.');
                return;
            }

            printWindow.document.open();
            printWindow.document.write(`<!doctype html>
                <html lang="vi"><head><meta charset="utf-8"><title>In hóa đơn</title>
                <style>
                    @page { size: 80mm ${pageHeightMm}mm; margin: 3mm; }
                    * { box-sizing: border-box; }
                    html, body { width: 74mm; margin: 0; padding: 0; color: #000; background: #fff; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.35; }
                    #print-receipt { display: block !important; width: 74mm; margin: 0; padding: 0; }
                    .receipt-title { font-size: 16px; font-weight: 700; text-align: center; }
                    .receipt-center { text-align: center; }
                    .receipt-line { border-top: 1px dashed #000; margin: 7px 0; }
                    .receipt-row { display: flex; justify-content: space-between; gap: 8px; }
                    .receipt-row > :last-child { text-align: right; white-space: nowrap; }
                    .receipt-item { margin-bottom: 5px; break-inside: avoid; }
                    .receipt-total { font-size: 14px; font-weight: 700; }
                </style></head><body>${receipt.outerHTML}</body></html>`);
            printWindow.document.close();
            const actualReceipt = printWindow.document.getElementById('print-receipt');
            const actualHeightMm = Math.max(60, Math.ceil(actualReceipt.scrollHeight * 25.4 / 96) + 8);
            const exactPageStyle = printWindow.document.createElement('style');
            exactPageStyle.textContent = `@page { size: 80mm ${actualHeightMm}mm; margin: 3mm; }`;
            printWindow.document.head.appendChild(exactPageStyle);
            printWindow.focus();
            printWindow.addEventListener('afterprint', () => printWindow.close());
            setTimeout(() => printWindow.print(), 250);
        }
    </script>
    @livewireScripts
</body>
</html>
