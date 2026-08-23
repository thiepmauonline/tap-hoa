<div>
    <div class="row g-4">
        <!-- Khung Chat AI (Bên trái 8 cột) -->
        <div class="col-lg-8">
            <div class="card card-custom d-flex flex-column" style="height: 75vh;">
                <!-- Header Chat -->
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Trợ Lý AI Phân Tích Kinh Doanh</h6>
                            <small class="text-success"><i class="bi bi-circle-fill fs-8"></i> Sẵn sàng hỗ trợ cửa hàng</small>
                        </div>
                    </div>
                    <span class="badge bg-light text-dark border">Model: Multi-Tenant AI Agent</span>
                </div>

                <!-- Lịch sử nhắn tin -->
                <div class="card-body p-3 overflow-auto flex-grow-1 bg-light">
                    @forelse($logs as $log)
                        <!-- User Question -->
                        <div class="d-flex justify-content-end mb-3">
                            <div class="bg-primary text-white p-3 rounded-3 shadow-sm" style="max-width: 80%;">
                                <div class="fw-semibold mb-1 fs-7"><i class="bi bi-person me-1"></i> Bạn ({{ $log->user->name ?? 'User' }})</div>
                                <div>{{ $log->question }}</div>
                                <div class="text-white-50 text-end fs-8 mt-1">{{ $log->created_at->format('H:i d/m') }}</div>
                            </div>
                        </div>

                        <!-- AI Answer -->
                        <div class="d-flex justify-content-start mb-4">
                            <div class="bg-white border p-3 rounded-3 shadow-sm text-dark" style="max-width: 85%;">
                                <div class="fw-bold text-primary mb-1 fs-7"><i class="bi bi-stars me-1 text-warning"></i> AI Trợ Lý</div>
                                <div style="white-space: pre-line;">{!! nl2br(e($log->answer)) !!}</div>
                                <div class="text-muted fs-8 mt-2 text-end border-top pt-1">
                                    Token tiêu tốn: {{ $log->tokens_used ?? 0 }} | {{ $log->created_at->format('H:i d/m/Y') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted my-auto py-5">
                            <i class="bi bi-chat-dots fs-1 text-secondary d-block mb-2"></i>
                            Hãy bắt đầu đặt câu hỏi cho AI để phân tích dữ liệu kinh doanh của cửa hàng bạn!
                        </div>
                    @endforelse
                </div>

                <!-- Input Chat Bar -->
                <div class="card-footer bg-white border-top p-3">
                    <form wire:submit.prevent="ask">
                        <div class="input-group">
                            <input type="text" wire:model="userQuestion" class="form-control form-control-lg" placeholder="Nhập câu hỏi... (Vd: Sản phẩm nào bán chạy nhất?, Doanh thu hôm nay thế nào?)">
                            <button type="submit" class="btn btn-primary px-4 fw-bold" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="ask"><i class="bi bi-send-fill me-1"></i> Gửi AI</span>
                                <span wire:loading wire:target="ask"><span class="spinner-border spinner-border-sm me-1"></i> Phân tích...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Các câu hỏi gợi ý nhanh (Bên phải 4 cột) -->
        <div class="col-lg-4">
            <div class="card card-custom p-3 mb-3">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-lightbulb text-warning me-2"></i> Gợi ý câu hỏi nhanh</h6>
                <div class="d-grid gap-2">
                    <button wire:click="$set('userQuestion', 'Sản phẩm nào bán chạy nhất?')" class="btn btn-outline-secondary text-start fs-7 py-2">
                        <i class="bi bi-graph-up-arrow text-success me-2"></i> Sản phẩm nào bán chạy nhất?
                    </button>
                    <button wire:click="$set('userQuestion', 'Có sản phẩm nào sắp hết kho cần nhập thêm không?')" class="btn btn-outline-secondary text-start fs-7 py-2">
                        <i class="bi bi-exclamation-triangle text-danger me-2"></i> Có sản phẩm nào sắp hết kho?
                    </button>
                    <button wire:click="$set('userQuestion', 'Báo cáo doanh thu hôm nay thế nào?')" class="btn btn-outline-secondary text-start fs-7 py-2">
                        <i class="bi bi-currency-dollar text-primary me-2"></i> Báo cáo doanh thu hôm nay?
                    </button>
                </div>
            </div>

            <div class="card card-custom p-3 bg-primary bg-opacity-10 border-primary border-opacity-25">
                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-info-circle me-1"></i> AI Cách ly Dữ liệu Multi-Tenant</h6>
                <p class="fs-7 text-muted mb-0">
                    Mô hình AI chỉ phân tích và truy vấn dữ liệu thuộc cửa hàng <strong>{{ auth()->user()->store->name }}</strong> của bạn. Dữ liệu các cửa hàng khác hoàn toàn được bảo mật độc lập.
                </p>
            </div>
        </div>
    </div>
</div>
