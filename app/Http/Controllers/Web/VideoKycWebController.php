<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VideoKycSession;

class VideoKycWebController extends Controller
{
    public function page(string $session_id)
    {
        $session = VideoKycSession::where('session_id', $session_id)->firstOrFail();

        // ✅ One-time link block: if already completed, show completed page
        if (in_array($session->status, ['uploaded', 'verified', 'rejected'])) {
            return redirect()->route('vkyc.completed', ['session_id' => $session_id]);
        }

        // ✅ Use ONE view name only
        return view('vkyc.page', compact('session')); // your current frontend file
    }

    public function completed(string $session_id)
    {
        $session = VideoKycSession::where('session_id', $session_id)->firstOrFail();

        // ✅ show success page always
        return view('vkyc.completed', compact('session'));
    }
}