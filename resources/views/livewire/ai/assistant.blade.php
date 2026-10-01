<div>
    <div class="row g-3">
        <!-- Khung chat chính (8 cột) -->
        <div class="col-lg-8">
            <div class="card card-custom d-flex flex-column" style="height: 76vh;">
                <!-- Header thanh gọn -->
                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-light text-primary rounded-2 p-1.5 d-flex align-items-center justify-content-center border" style="width:32px; height:32px;">
                            <i class="bi bi-chat-left-text fs-6"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-dark fs-7">Hỏi đáp kinh doanh</div>
                            <small class="text-success fs-8"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Trực tuyến</small>
                        </div>
                    </div>

                    @if($logs->isNotEmpty())
                        <button wire:click="clearHistory" wire:confirm="Bạn có chắc chắn muốn xóa toàn bộ lịch sử hỏi đáp không?" class="btn btn-sm btn-link text-muted p-1 text-decoration-none" title="Xóa lịch sử">
                            <i class="bi bi-trash3 fs-7"></i>
                        </button>
                    @endif
                </div>

                <!-- Lịch sử tin nhắn -->
                <div class="card-body p-3 overflow-auto flex-grow-1" id="chatContainer" style="background-color: #f8fafc;">
                    @forelse($logs as $log)
                        <!-- User message -->
                        <div class="d-flex justify-content-end mb-2.5">
                            <div class="bg-primary text-white px-3 py-2 rounded-3 shadow-none" style="max-width: 80%; font-size: 0.85rem;">
                                <div>{{ $log->question }}</div>
                                <div class="text-white-50 text-end fs-8 mt-1">{{ $log->created_at->format('H:i') }}</div>
                            </div>
                        </div>

                        <!-- AI message -->
                        <div class="d-flex justify-content-start mb-3">
                            <div class="bg-white border text-dark px-3 py-2.5 rounded-3 shadow-none" style="max-width: 88%; font-size: 0.85rem;">
                                <div class="ai-rendered-content" style="line-height: 1.55;">
                                    {!! \Illuminate\Support\Str::markdown($log->answer) !!}
                                </div>
                                <div class="text-muted fs-8 mt-1.5 pt-1 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-secondary">Trợ lý</span>
                                    <span>{{ $log->created_at->format('H:i d/m') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted my-auto py-4">
                            <i class="bi bi-chat-dots fs-3 text-secondary d-block mb-2"></i>
                            <div class="fw-semibold text-dark fs-7">Chưa có cuộc trò chuyện nào</div>
                            <small class="text-muted">Chọn câu hỏi gợi ý ở cột bên phải để tra cứu nhanh số liệu.</small>
                        </div>
                    @endforelse

                    <!-- Loading indicator -->
                    <div wire:loading wire:target="ask" class="w-100 mb-2">
                        <div class="d-flex justify-content-start">
                            <div class="bg-white border px-3 py-2 rounded-3 text-muted d-flex align-items-center gap-2 fs-8">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                <span>Đang tổng hợp dữ liệu...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input bar gọn gàng -->
                <div class="card-footer bg-white border-top p-2.5">
                    <form wire:submit.prevent="ask">
                        <div class="input-group">
                            <input type="text" wire:model="userQuestion" class="form-control fs-7" 
                                   placeholder="Hỏi về bán chạy, tồn kho, doanh thu..."
                                   autocomplete="off" autofocus>
                            <button type="submit" class="btn btn-primary px-3 fs-7" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="ask"><i class="bi bi-send-fill me-1"></i>Gửi</span>
                                <span wire:loading wire:target="ask"><span class="spinner-border spinner-border-sm"></span></span>
                            </button>
                        </div>
                        @error('userQuestion')
                            <div class="text-danger small mt-1 fs-8">{{ $message }}</div>
                        @enderror
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar gợi ý gom thành 1 thẻ duy nhất (4 cột) -->
        <div class="col-lg-4">
            <div class="card card-custom">
                <div class="card-header bg-white border-bottom py-2.5 px-3">
                    <span class="fw-semibold fs-7 text-dark">Gợi ý câu hỏi</span>
                </div>

                <div class="list-group list-group-flush p-2">
                    <button wire:click="selectPrompt('Mặt hàng nào đang bán chạy nhất tháng này?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up text-primary"></i>
                        <span>Mặt hàng bán chạy nhất tháng này?</span>
                    </button>

                    <button wire:click="selectPrompt('Sản phẩm nào tồn kho lâu ngày chưa bán được?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-hourglass text-warning"></i>
                        <span>Sản phẩm tồn kho lâu chưa bán được?</span>
                    </button>

                    <button wire:click="selectPrompt('Có sản phẩm nào sắp hết kho cần nhập thêm không?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        <span>Mặt hàng nào sắp hết cần nhập kho?</span>
                    </button>

                    <button wire:click="selectPrompt('Báo cáo doanh thu và lợi nhuận 30 ngày qua thế nào?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-cash-stack text-success"></i>
                        <span>Báo cáo doanh thu & lợi nhuận 30 ngày</span>
                    </button>

                    <button wire:click="selectPrompt('Gợi ý giải pháp giúp cửa hàng tăng doanh thu?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-lightbulb text-info"></i>
                        <span>Gợi ý giải pháp tăng doanh thu</span>
                    </button>
                </div>

                <div class="card-footer bg-light border-top py-2 px-3 text-muted fs-8 d-flex justify-content-between align-items-center">
                    <span>Đã hỏi: {{ $logs->count() }} câu</span>
                    <span>Số liệu thực tế</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Script cuộn mượt xuống cuối chat -->
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

    <!-- CSS tinh chỉnh nội dung Markdown của AI -->
    <style>
        .ai-rendered-content p { margin-bottom: 0.5rem; }
        .ai-rendered-content p:last-child { margin-bottom: 0; }
        .ai-rendered-content ul, .ai-rendered-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .ai-rendered-content li { margin-bottom: 0.25rem; }
        .ai-rendered-content h1, .ai-rendered-content h2, .ai-rendered-content h3, .ai-rendered-content h4 { 
            font-size: 0.92rem; 
            font-weight: 600; 
            margin-top: 0.6rem; 
            margin-bottom: 0.35rem; 
        }
    </style>
</div>
