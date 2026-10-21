<?php

namespace App\Livewire\Ai;

use App\Models\AiLog;
use App\Services\AiAssistantService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
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
        abort_unless(auth()->user()->isManager(), 403);
        $this->userQuestion = trim($this->userQuestion);
        $this->validate();
        $key = 'ai:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['userQuestion' => 'Quá nhiều câu hỏi. Vui lòng thử lại sau một phút.']);
        }
        RateLimiter::hit($key, 60);

        $question = trim($this->userQuestion);
        $this->isAnalyzing = true;

        $storeId = (int) auth()->user()->store_id;
        $userId = (int) auth()->id();

        // Xử lý thông qua AiAssistantService (Gemini API -> OpenAI -> Local Fallback)
        try {
            $aiService->ask($question, $storeId, $userId);
            $this->userQuestion = '';
        } finally {
            $this->isAnalyzing = false;
        }
    }

    public function selectPrompt(string $prompt): void
    {
        $this->userQuestion = $prompt;
    }

    public function clearHistory(): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $storeId = (int) auth()->user()->store_id;
        AiLog::where('store_id', $storeId)->delete();
        session()->flash('success', 'Đã xóa lịch sử hỏi đáp.');
    }

    public function render(AiAssistantService $aiService)
    {
        abort_unless(auth()->user()->isManager(), 403);
        $storeId = (int) auth()->user()->store_id;

        $logs = AiLog::where('store_id', $storeId)
            ->with('user')
            ->orderByDesc('id')
            ->take(50)
            ->get()->reverse()->values();

        $totalTokens = AiLog::where('store_id', $storeId)->sum('tokens_used');

        return view('livewire.ai.assistant', [
            'logs' => $logs,
            'totalTokens' => $totalTokens,
            'hasGemini' => ! empty(config('services.gemini.key')),
            'hasOpenAi' => ! empty(config('services.openai.key')),
        ])->layout('layouts.app', ['headerTitle' => 'Trợ lý AI']);
    }
}
