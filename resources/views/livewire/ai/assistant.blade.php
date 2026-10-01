<div>
    <div class="row g-4">
        <!-- Khung Chat AI (Bên trái 8 cột) -->
        <div class="col-lg-8">
            <div class="card card-custom d-flex flex-column shadow-sm" style="height: 78vh;">
                <!-- Header Chat -->
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width:42px; height:42px;">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Trợ Lý AI Phân Tích Kinh Doanh</h6>
                            <div class="d-flex align-items-center gap-2 mt-0.5">
                                <small class="text-success fw-medium"><i class="bi bi-circle-fill" style="font-size: 0.55rem;"></i> Trực tuyến</small>
                                <span class="text-muted" style="font-size: 0.75rem;">•</span>
                                <small class="text-muted" style="font-size: 0.75rem;">Cửa hàng: <strong>{{ auth()->user()->store->name ?? 'TapHoa' }}</strong></small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        @if($hasGemini)
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill fs-8">
                                <i class="bi bi-stars me-1 text-warning"></i> Google Gemini 1.5 Flash
                            </span>
                        @elseif($hasOpenAi)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill fs-8">
                                <i class="bi bi-cpu-fill me-1"></i> OpenAI GPT API
                            </span>
                        @else
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 rounded-pill fs-8" title="Chế độ phân tích dữ liệu chuẩn xác nội bộ">
                                <i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Local Intelligence Engine
                            </span>
                        @endif

                        @if($logs->isNotEmpty())
                            <button wire:click="clearHistory" wire:confirm="Bạn có chắc chắn muốn xóa toàn bộ lịch sử hỏi đáp AI của cửa hàng không?" class="btn btn-sm btn-outline-secondary" title="Dọn dẹp lịch sử">
                                <i class="bi bi-trash3"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Lịch sử nhắn tin -->
                <div class="card-body p-3 overflow-auto flex-grow-1 bg-light" id="chatContainer">
                    @forelse($logs as $log)
                        <!-- User Question -->
                        <div class="d-flex justify-content-end mb-3">
                            <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 82%; border-bottom-right-radius: 4px !important;">
                                <div class="fw-semibold mb-1 fs-7 d-flex align-items-center justify-content-between gap-3 text-white-50">
                                    <span><i class="bi bi-person-fill me-1"></i> {{ $log->user->name ?? 'Chủ cửa hàng' }}</span>
                                    <span style="font-size: 0.7rem;">{{ $log->created_at->format('H:i d/m') }}</span>
                                </div>
                                <div class="fs-6">{{ $log->question }}</div>
                            </div>
                        </div>

                        <!-- AI Answer -->
                        <div class="d-flex justify-content-start mb-4">
                            <div class="bg-white border p-3 rounded-4 shadow-sm text-dark" style="max-width: 88%; border-bottom-left-radius: 4px !important;">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                    <div class="fw-bold text-primary fs-7 d-flex align-items-center gap-1.5">
                                        <i class="bi bi-stars text-warning fs-6"></i> Trợ Lý AI
                                    </div>
                                    <div>
                                        @if($log->type === 'slow_stock')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">
                                                <i class="bi bi-hourglass-split me-1"></i> Hàng tồn lâu
                                            </span>
                                        @elseif($log->type === 'restock')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-8">
                                                <i class="bi bi-cart-plus me-1"></i> Gợi ý nhập hàng
                                            </span>
                                        @elseif($log->type === 'analysis')
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8">
                                                <i class="bi bi-bar-chart-line me-1"></i> Phân tích doanh số
                                            </span>
                                        @elseif($log->type === 'advice')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-8">
                                                <i class="bi bi-lightbulb me-1"></i> Đề xuất kinh doanh
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-8">
                                                <i class="bi bi-chat-dots me-1"></i> Tư vấn chung
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Nội dung trả lời của AI -->
                                <div class="ai-content fs-6 text-dark" style="line-height: 1.65; white-space: pre-line;">
                                    {!! preg_replace('/(\*\*.*?\*\*)/', '<strong class="text-dark">$1</strong>', e($log->answer)) !!}
                                </div>

                                <div class="text-muted fs-8 mt-2.5 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-database-check me-1 text-success"></i> Dữ liệu cách ly theo Store ID</span>
                                    <span>
                                        <i class="bi bi-lightning me-1 text-warning"></i> {{ $log->tokens_used ?? 0 }} tokens • {{ $log->created_at->format('H:i d/m/Y') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted my-auto py-5">
                            <div class="bg-white rounded-circle p-3 d-inline-flex mb-3 shadow-sm border">
                                <i class="bi bi-robot fs-1 text-primary"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Xin chào! Tôi có thể giúp gì cho bạn?</h5>
                            <p class="text-muted fs-7 mb-3" style="max-width: 480px; margin: 0 auto;">
                                Tôi được kết nối trực tiếp với cơ sở dữ liệu của <strong>{{ auth()->user()->store->name ?? 'cửa hàng' }}</strong> để phân tích bán chạy, cảnh báo kho, nhận diện hàng tồn lâu và tư vấn chiến lược kinh doanh.
                            </p>
                            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">
                                Chọn một câu hỏi gợi ý ở cột bên phải để bắt đầu ngay 👇
                            </span>
                        </div>
                    @endforelse

                    <!-- Spinner đang phân tích -->
                    <div wire:loading wire:target="ask" class="w-100 mb-3">
                        <div class="d-flex justify-content-start">
                            <div class="bg-white border p-3 rounded-4 shadow-sm text-muted d-flex align-items-center gap-2">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="fs-7 fw-medium">AI đang truy vấn dữ liệu & suy luận câu trả lời...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Chat Bar -->
                <div class="card-footer bg-white border-top p-3">
                    <form wire:submit.prevent="ask">
                        <div class="input-group">
                            <input type="text" wire:model="userQuestion" class="form-control form-control-lg fs-6" 
                                   placeholder="Hỏi AI về bán chạy, hàng tồn lâu, gợi ý nhập hàng, doanh thu... (Nhấn Enter để gửi)"
                                   autocomplete="off" autofocus>
                            <button type="submit" class="btn btn-primary px-4 fw-bold d-flex align-items-center gap-2" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="ask"><i class="bi bi-send-fill"></i> Gửi AI</span>
                                <span wire:loading wire:target="ask"><span class="spinner-border spinner-border-sm"></span> Đang xử lý...</span>
                            </button>
                        </div>
                        @error('userQuestion')
                            <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                        @enderror
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar gợi ý câu hỏi & Thông số (Bên phải 4 cột) -->
        <div class="col-lg-4">
            <!-- Gợi ý câu hỏi chuẩn đồ án -->
            <div class="card card-custom p-3 mb-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-lightbulb-fill text-warning me-2"></i> Gợi ý câu hỏi phân tích</h6>
                    <small class="text-muted fs-8">Bấm để chọn</small>
                </div>
                
                <div class="d-grid gap-2">
                    <button wire:click="selectPrompt('Mặt hàng nào đang bán chạy nhất tháng này?')" class="btn btn-outline-secondary text-start fs-7 py-2.5 d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up-arrow text-primary fs-6"></i>
                        <span class="text-truncate">Mặt hàng nào bán chạy nhất tháng này?</span>
                    </button>

                    <button wire:click="selectPrompt('Sản phẩm nào tồn kho lâu ngày cần xả hàng?')" class="btn btn-outline-secondary text-start fs-7 py-2.5 d-flex align-items-center gap-2 border-warning-subtle">
                        <i class="bi bi-hourglass-split text-warning fs-6"></i>
                        <span class="text-truncate">Sản phẩm nào tồn kho lâu cần xả hàng?</span>
                    </button>

                    <button wire:click="selectPrompt('Có sản phẩm nào sắp hết kho cần nhập thêm không?')" class="btn btn-outline-secondary text-start fs-7 py-2.5 d-flex align-items-center gap-2 border-danger-subtle">
                        <i class="bi bi-exclamation-octagon text-danger fs-6"></i>
                        <span class="text-truncate">Sản phẩm nào sắp hết cần nhập gấp?</span>
                    </button>

                    <button wire:click="selectPrompt('Báo cáo doanh thu và lợi nhuận 30 ngày qua thế nào?')" class="btn btn-outline-secondary text-start fs-7 py-2.5 d-flex align-items-center gap-2">
                        <i class="bi bi-cash-coin text-success fs-6"></i>
                        <span class="text-truncate">Báo cáo doanh thu & lợi nhuận 30 ngày qua?</span>
                    </button>

                    <button wire:click="selectPrompt('Đề xuất chiến lược giúp cửa hàng tăng doanh số?')" class="btn btn-outline-secondary text-start fs-7 py-2.5 d-flex align-items-center gap-2 border-primary-subtle">
                        <i class="bi bi-stars text-info fs-6"></i>
                        <span class="text-truncate">Đề xuất giải pháp tăng doanh số bán lẻ?</span>
                    </button>
                </div>
            </div>

            <!-- Card Kiến trúc SaaS Multi-tenant -->
            <div class="card card-custom p-3 mb-3 bg-primary bg-opacity-10 border-primary border-opacity-25 shadow-sm">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-shield-check text-primary fs-5"></i>
                    <h6 class="fw-bold text-primary mb-0">Cách ly Dữ liệu Multi-Tenant</h6>
                </div>
                <p class="fs-7 text-muted mb-2">
                    Mô hình AI chỉ tiếp cận dữ liệu thuộc phạm vi cửa hàng <strong>{{ auth()->user()->store->name ?? '' }}</strong> (Store ID: #{{ auth()->user()->store_id }}).
                </p>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top border-primary border-opacity-25 fs-8 text-secondary">
                    <span>Mô hình SaaS:</span>
                    <strong class="text-dark">Shared DB, Tenant Scoped</strong>
                </div>
            </div>

            <!-- Card Thống kê tiêu thụ AI -->
            <div class="card card-custom p-3 shadow-sm">
                <h6 class="fw-bold text-dark mb-2.5"><i class="bi bi-activity text-success me-2"></i> Thống kê sử dụng AI</h6>
                <div class="d-flex justify-content-between py-1.5 border-bottom fs-7">
                    <span class="text-muted">Tổng số câu hỏi:</span>
                    <strong class="text-dark">{{ $logs->count() }} lượt</strong>
                </div>
                <div class="d-flex justify-content-between py-1.5 border-bottom fs-7">
                    <span class="text-muted">Tổng token đã dùng:</span>
                    <strong class="text-primary">{{ number_format($totalTokens) }} tokens</strong>
                </div>
                <div class="d-flex justify-content-between pt-1.5 fs-7">
                    <span class="text-muted">Cơ chế an toàn:</span>
                    <span class="badge bg-success-subtle text-success">Active Fallback</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Script tự động cuộn xuống tin nhắn mới nhất -->
    <script>
        function scrollToBottom() {
            const container = document.getElementById('chatContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        document.addEventListener('DOMContentLoaded', scrollToBottom);
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => {
                scrollToBottom();
            });
        });
    </script>
</div>
