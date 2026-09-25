<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
class IssueApiToken extends Command {
    protected $signature='api:issue-token {email}';
    protected $description='Issue a new API bearer token for an active user';
    public function handle(): int {
        $user=User::where('email',$this->argument('email'))->first();
        if(!$user){$this->error('Không tìm thấy người dùng.');return self::FAILURE;}
        $token=Str::random(64);$user->update(['api_token'=>hash('sha256',$token)]);
        $this->warn('Token chỉ hiển thị một lần:');$this->line($token);return self::SUCCESS;
    }
}
