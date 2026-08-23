<div>
    <!-- Banner Chào mừng & Cửa hàng -->
    <div class="card card-custom border-0 bg-primary text-white p-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-shop me-2"></i> {{ auth()->user()->store->name }}</h4>
                <p class="mb-0 text-white-50 fs-7">Xin chào <strong>{{ auth()->user()->name }}</strong>, chúc bạn một ngày bán hàng thành công!</p>
            </div>
            <a href="{{ route('pos') }}" class="btn btn-warning btn-lg fw-bold px-4 shadow" target="_blank">
                <i class="bi bi-calculator-fill me-2"></i> BẮT ĐẦU BÁN HÀNG (POS)
            </a>
        </div>
    </div>

    <!-- 4 Thẻ Chỉ Số KPI -->
    <div class="row g-3 mb-4">
        <!-- Doanh thu hôm nay -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-semibold">DOANH THU HÔM NAY</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($todayRevenue, 0, ',', '.') }} đ</h4>
                    </div>
                    <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="bi bi-currency-dollar fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Đơn bán hôm nay -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-semibold">ĐƠN HÀNG HÔM NAY</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ $todayOrdersCount }} đơn</h4>
                    </div>
                    <div class="bg-success text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="bi bi-bag-check-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tổng sản phẩm -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 border-start border-info border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-semibold">SẢN PHẨM TRONG KHO</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ $totalProductsCount }} mã</h4>
                    </div>
                    <div class="bg-info text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="bi bi-box-seam-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cảnh báo hết kho -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 border-start border-danger border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-semibold">CẢNH BÁO SẮP HẾT HÀNG</span>
                        <h4 class="fw-bold text-danger mt-1 mb-0">{{ $lowStockCount }} sản phẩm</h4>
                    </div>
                    <div class="bg-danger text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KHU VỰC BIỂU ĐỒ DOANH THU CHART.JS (7 NGÀY GẦN NHẤT) -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-graph-up-arrow me-2 text-primary"></i> Biểu đồ Tăng trưởng Doanh thu (7 Ngày Gần Nhất)
                </h5>
                <small class="text-muted">Thống kê doanh thu tự động ghi nhận từ các đơn hàng bán ra tại quầy POS</small>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-7 fw-semibold">
                <i class="bi bi-calendar-range me-1"></i> {{ $chartLabels[0] ?? '' }} - {{ end($chartLabels) }}
            </span>
        </div>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Hàng 2: Đơn bán mới nhất & Cảnh báo kho -->
    <div class="row g-4 mb-4">
        <!-- Đơn bán mới nhất -->
        <div class="col-lg-7">
            <div class="card card-custom h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Đơn hàng bán mới nhất</h6>
                    <a href="{{ route('orders.index') }}" class="btn btn-sm btn-link text-decoration-none">Xem tất cả <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Mã đơn</th>
                                    <th>Thời gian</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th class="text-end pe-3">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $ord)
                                    <tr>
                                        <td class="ps-3 fw-semibold text-primary">{{ $ord->order_code }}</td>
                                        <td class="fs-7 text-muted">{{ $ord->order_date->format('H:i d/m/Y') }}</td>
                                        <td>{{ $ord->customer->name ?? 'Khách lẻ' }}</td>
                                        <td class="fw-bold">{{ number_format($ord->total_amount, 0, ',', '.') }} đ</td>
                                        <td class="text-end pe-3">
                                            <span class="badge badge-soft-success">Hoàn tất</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Chưa có đơn hàng nào trong ngày hôm nay</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sản phẩm sắp hết kho cần nhập thêm -->
        <div class="col-lg-5">
            <div class="card card-custom h-100 border-danger border-opacity-25">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-octagon me-2"></i> Cần nhập thêm ngay</h6>
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-plus-circle me-1"></i> Nhập kho</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($lowStockProducts as $lp)
                            <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2.5">
                                <div>
                                    <div class="fw-semibold text-dark fs-7">{{ $lp->name }}</div>
                                    <small class="text-muted fs-8">Mã vạch: {{ $lp->barcode ?? 'N/A' }} | Danh mục: {{ $lp->category->name ?? 'Khác' }}</small>
                                </div>
                                <span class="badge bg-danger fs-7">Tồn: {{ $lp->inventory->quantity ?? 0 }} {{ $lp->unit }}</span>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-3 text-success d-block mb-1"></i>
                                Kho hàng đang ở mức an toàn!
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js v4 CDN Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('revenueChart').getContext('2d');

            // Tạo hiệu ứng Gradient Fill tím Indigo
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(79, 70, 229, 0.35)');
            gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            const chartLabels = @json($chartLabels);
            const chartValues = @json($chartValues);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: chartValues,
                        borderColor: '#4f46e5',
                        borderWidth: 3,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4, // Đường uốn cong mềm mại
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 14, weight: 'bold', family: 'Inter' },
                            bodyFont: { size: 13, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed.y || 0;
                                    return ' Doanh thu: ' + value.toLocaleString('vi-VN') + ' đ';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'Inter', size: 12, weight: '500' },
                                color: '#64748b'
                            }
                        },
                        y: {
                            grid: {
                                color: '#f1f5f9',
                                drawBorder: false
                            },
                            ticks: {
                                font: { family: 'Inter', size: 12 },
                                color: '#64748b',
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return (value / 1000000).toFixed(1) + ' Tr';
                                    } else if (value >= 1000) {
                                        return (value / 1000).toFixed(0) + ' k';
                                    }
                                    return value;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</div>
