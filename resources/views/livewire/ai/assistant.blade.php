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
                            @if($aiMode)
                                <small class="text-success fs-8"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>{{ $aiMode }} · sẵn sàng</small>
                            @else
                                <small class="text-warning fs-8"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Chế độ ngoại tuyến (chưa cấu hình API AI)</small>
                            @endif
                        </div>
                    </div>

                    @if($logs->isNotEmpty())
                        <button wire:click="clearHistory" wire:confirm="Xóa lịch sử hỏi đáp của cửa hàng? Không thể hoàn tác." class="btn btn-sm btn-link text-muted p-1 text-decoration-none" title="Xóa lịch sử">
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
                                    {!! \Illuminate\Support\Str::markdown($log->answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>
                                <div class="text-muted fs-8 mt-1.5 pt-1 border-top d-flex justify-content-between align-items-center">
                                    <span class="text-secondary">
                                        Trợ lý
                                        @if($log->provider === 'local')
                                            · <span class="badge badge-soft-warning fw-normal" title="Không kết nối được dịch vụ AI, câu trả lời theo mẫu có sẵn">Ngoại tuyến</span>
                                        @elseif($log->provider)
                                            · <span title="{{ $log->model }}">{{ \App\Services\AiAssistantService::providerLabel($log->provider) }}</span>
                                        @endif
                                        @if(! empty($log->tools_used))
                                            <span class="ms-1" title="Dữ liệu đã tra cứu"><i class="bi bi-database"></i> {{ collect($log->tools_used)->map(fn ($tool) => \App\Services\Ai\StoreDataTools::LABELS[$tool] ?? $tool)->implode(', ') }}</span>
                                        @endif
                                    </span>
                                    <span>{{ $log->created_at->format('H:i d/m') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted my-auto py-4">
                            <i class="bi bi-chat-dots fs-3 text-secondary d-block mb-2"></i>
                            <div class="fw-semibold text-dark fs-7">Chưa có cuộc trò chuyện</div>
                            <small class="text-muted">Nhập câu hỏi hoặc chọn gợi ý để bắt đầu.</small>
                        </div>
                    @endforelse

                    <!-- Loading indicator -->
                    <div wire:loading wire:target="ask" class="w-100 mb-2">
                        <div class="d-flex justify-content-start">
                            <div class="bg-white border px-3 py-2 rounded-3 text-muted d-flex align-items-center gap-2 fs-8">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                                <span>Đang phân tích...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input bar gọn gàng -->
                <div class="card-footer bg-white border-top p-2.5">
                    <form wire:submit.prevent="ask">
                        <div class="input-group">
                            <input type="text" wire:model="userQuestion" class="form-control fs-7" 
                                   placeholder="Hỏi về doanh thu, sản phẩm, tồn kho"
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
                    <button wire:click="selectPrompt('Doanh thu hôm qua bao nhiêu?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-cash-stack text-success"></i>
                        <span>Doanh thu hôm qua</span>
                    </button>

                    <button wire:click="selectPrompt('So sánh doanh thu tuần này với tuần trước')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-bar-chart-line text-primary"></i>
                        <span>So sánh tuần này và tuần trước</span>
                    </button>

                    <button wire:click="selectPrompt('Sản phẩm bán chạy trong 30 ngày qua?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up text-primary"></i>
                        <span>Bán chạy trong 30 ngày</span>
                    </button>

                    <button wire:click="selectPrompt('Nên nhập thêm hàng gì, bao nhiêu?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam text-danger"></i>
                        <span>Gợi ý nhập hàng</span>
                    </button>

                    <button wire:click="selectPrompt('Sản phẩm nào tồn kho lâu?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-hourglass text-warning"></i>
                        <span>Hàng tồn lâu</span>
                    </button>

                    <button wire:click="selectPrompt('Khách hàng nào mua nhiều nhất?')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-people text-info"></i>
                        <span>Khách mua nhiều nhất</span>
                    </button>

                    <button wire:click="selectPrompt('Gợi ý tăng doanh thu cho cửa hàng')" class="list-group-item list-group-item-action border-0 px-2 py-2 rounded-2 fs-8 text-secondary d-flex align-items-center gap-2">
                        <i class="bi bi-lightbulb text-info"></i>
                        <span>Gợi ý tăng doanh thu</span>
                    </button>
                    <div class="px-2 pt-2 text-muted fs-8">Có thể hỏi tự do, ví dụ: "Pepsi còn bao nhiêu lon?", "Doanh thu tháng 9?"</div>
                </div>

                <div class="card-footer bg-light border-top py-2 px-3 text-muted fs-8 d-flex justify-content-between align-items-center">
                    <span>Đã hỏi: {{ $logs->count() }} câu</span>
                    <span>Số liệu lấy trực tiếp từ hệ thống</span>
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
