<?php

namespace App\Jobs;

use App\Models\VideoKycSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SendVkycWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function handle(): void
    {
        $session = VideoKycSession::where('session_id', $this->sessionId)->first();
        if (!$session || empty($session->response_url)) return;

        $videoUrl = $session->video_path
            ? Storage::disk('public')->url($session->video_path)
            : null;

        Http::timeout(10)->post($session->response_url, [
            'session_id'      => $session->session_id,
            'purpose'         => $session->purpose,
            'status'          => $session->status,

            'customer_name'   => $session->customer_name,
            'customer_email'  => $session->customer_email,
            'customer_mobile' => $session->customer_mobile,

            'video_url'       => $videoUrl,
            'started_at'      => optional($session->started_at)->toISOString(),
            'ended_at'        => optional($session->ended_at)->toISOString(),
        ]);
    }
}