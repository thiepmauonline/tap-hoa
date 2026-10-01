<div>
    <!-- Tiêu đề & Nút thao tác -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">{{ auth()->user()->store->name }}</h4>
            <div class="text-muted fs-7">Xin chào {{ auth()->user()->name }} • Tổng quan hoạt động kinh doanh</div>
        </div>
        <div>
            <a href="{{ route('pos') }}" class="btn btn-primary fw-semibold px-3 py-2 shadow-sm d-flex align-items-center gap-2" target="_blank">
                <i class="bi bi-calculator"></i>
                <span>Mở quầy bán hàng (POS)</span>
            </a>
        </div>
    </div>

    <!-- 4 Thẻ Chỉ Số KPI -->
    <div class="row g-3 mb-4">
        <!-- Doanh thu hôm nay -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-medium">Doanh thu hôm nay</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($todayRevenue, 0, ',', '.') }} đ</h4>
                    </div>
                    <div class="bg-light text-primary rounded-3 p-2.5 d-flex align-items-center justify-content-center border" style="width:42px; height:42px;">
                        <i class="bi bi-cash-stack fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Đơn bán hôm nay -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-medium">Đơn hàng hôm nay</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ $todayOrdersCount }} đơn</h4>
                    </div>
                    <div class="bg-light text-success rounded-3 p-2.5 d-flex align-items-center justify-content-center border" style="width:42px; height:42px;">
                        <i class="bi bi-bag-check fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tổng sản phẩm -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-medium">Sản phẩm đang bán</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ $totalProductsCount }} mặt hàng</h4>
                    </div>
                    <div class="bg-light text-secondary rounded-3 p-2.5 d-flex align-items-center justify-content-center border" style="width:42px; height:42px;">
                        <i class="bi bi-box-seam fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cảnh báo hết kho -->
        <div class="col-xl-3 col-sm-6">
            <div class="card card-custom p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-7 fw-medium">Sắp hết hàng</span>
                        <h4 class="fw-bold text-danger mt-1 mb-0">{{ $lowStockCount }} sản phẩm</h4>
                    </div>
                    <div class="bg-light text-danger rounded-3 p-2.5 d-flex align-items-center justify-content-center border" style="width:42px; height:42px;">
                        <i class="bi bi-exclamation-triangle fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KHU VỰC BIỂU ĐỒ DOANH THU CHART.JS (7 NGÀY GẦN NHẤT) -->
    <div class="card card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold text-dark mb-1">Doanh thu 7 ngày gần nhất</h6>
                <small class="text-muted">Biểu đồ tổng hợp giá trị các đơn bán hoàn tất</small>
            </div>
            <span class="badge bg-light text-secondary border px-3 py-1.5 fs-7 fw-normal">
                {{ $chartLabels[0] ?? '' }} — {{ end($chartLabels) }}
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
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 px-3">
                    <h6 class="fw-bold text-dark mb-0">Đơn hàng gần đây</h6>
                    <a href="{{ route('orders.index') }}" class="btn btn-sm btn-link text-decoration-none p-0">Xem tất cả <i class="bi bi-arrow-right"></i></a>
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
            <div class="card card-custom h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3 px-3">
                    <h6 class="fw-bold text-dark mb-0">Hàng sắp hết kho</h6>
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Nhập hàng</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($lowStockProducts as $lp)
                            <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2.5">
                                <div>
                                    <div class="fw-semibold text-dark fs-7">{{ $lp->name }}</div>
                                    <small class="text-muted fs-8">{{ $lp->barcode ? 'Mã: ' . $lp->barcode . ' | ' : '' }}{{ $lp->category->name ?? 'Khác' }}</small>
                                </div>
                                <span class="badge bg-light text-danger border border-danger-subtle fs-7">Còn {{ $lp->inventory->quantity ?? 0 }} {{ $lp->unit }}</span>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-3 text-success d-block mb-1"></i>
                                Kho hàng đang ở mức an toàn
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
