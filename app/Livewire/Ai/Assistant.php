<?php

namespace App\Livewire\Ai;

use App\Models\AiLog;
use App\Services\AiAssistantService;
use Livewire\Component;

class Assistant extends Component
{
    public string $userQuestion = '';
    public bool $isAnalyzing = false;

    protected array $rules = [
        'userQuestion' => 'required|string|min:2|max:500',
    ];

    public function ask(AiAssistantService $aiService): void
    {
        $this->validate();

        $question = trim($this->userQuestion);
        $this->userQuestion = '';
        $this->isAnalyzing = true;

        $storeId = (int) auth()->user()->store_id;
        $userId = (int) auth()->id();

        // Xử lý thông qua AiAssistantService (Gemini API -> OpenAI -> Local Fallback)
        $aiService->ask($question, $storeId, $userId);

        $this->isAnalyzing = false;
    }

    public function selectPrompt(string $prompt): void
    {
        $this->userQuestion = $prompt;
    }

    public function clearHistory(): void
    {
        $storeId = (int) auth()->user()->store_id;
        AiLog::where('store_id', $storeId)->delete();
        session()->flash('success', 'Đã dọn dẹp lịch sử hỏi đáp AI thành công.');
    }

    public function render(AiAssistantService $aiService)
    {
        $storeId = (int) auth()->user()->store_id;

        $logs = AiLog::where('store_id', $storeId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get();

        $totalTokens = AiLog::where('store_id', $storeId)->sum('tokens_used');

        return view('livewire.ai.assistant', [
            'logs' => $logs,
            'totalTokens' => $totalTokens,
            'hasGemini' => ! empty(config('services.gemini.key')),
            'hasOpenAi' => ! empty(config('services.openai.key')),
        ])->layout('layouts.app', ['headerTitle' => 'Trợ lý phân tích']);
    }
}
