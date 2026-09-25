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

        #print-receipt {
            display: none;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 3mm;
            }

            html, body {
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
