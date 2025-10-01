<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig; // <-- [PERBAIKAN] Import AndroidConfig

class SendWelcomeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fcmToken;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fcmToken)
    {
        $this->fcmToken = $fcmToken;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->fcmToken)) {
            Log::warning('SendWelcomeNotification Job: FCM token tidak ada, job dibatalkan.');
            return;
        }

        try {
            // [PERBAIKAN] Buat konfigurasi spesifik untuk Android
            $config = AndroidConfig::fromArray([
                'priority' => 'high', // Set prioritas menjadi 'high'
                'notification' => [
                    // Menentukan channel ID secara eksplisit agar sesuai dengan di Flutter
                    'channel_id' => 'high_importance_channel',
                ],
            ]);

            $messaging = app('firebase.messaging');
            $notification = Notification::create('Selamat Datang!', 'Registrasi Anda di aplikasi Rencanapa berhasil.');

            $message = CloudMessage::withTarget('token', $this->fcmToken)
                ->withNotification($notification)
                ->withAndroidConfig($config); // <-- [PERBAIKAN] Terapkan konfigurasi Android

            Log::info('QUEUE: Mencoba mengirim notifikasi (PRIORITAS TINGGI) ke token: ' . $this->fcmToken);
            $messaging->send($message);
            Log::info('QUEUE: Notifikasi (PRIORITAS TINGGI) berhasil dikirim ke token: ' . $this->fcmToken);
        } catch (\Exception $e) {
            Log::error('QUEUE: Gagal mengirim notifikasi FCM: ' . $e->getMessage());
        }
    }
}

