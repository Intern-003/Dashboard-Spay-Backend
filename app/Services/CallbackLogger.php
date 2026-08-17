<?php

namespace App\Services;

class CallbackLogger
{
    public static function log(string $type, string $message, array $data = [])
    {
        dump($type);
        dd("callback log");
        // type = payin | payout
        $logPath = storage_path("callback/{$type}");

        if (!file_exists($logPath)) {
            mkdir($logPath, 0777, true);
        }

        $file = $logPath . '/' . date('Y-m-d') . '.log';

        $text = "[" . date('Y-m-d H:i:s') . "] " . strtoupper($type) . " : " . $message;

        if (!empty($data)) {
            $text .= " " . json_encode($data);
        }

        $text .= "\n\n";

        file_put_contents($file, $text, FILE_APPEND);
    }
}