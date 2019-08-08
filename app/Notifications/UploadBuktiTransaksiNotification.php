<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Mail\UploadBuktiTransaksiMail;

class UploadBuktiTransaksiNotification extends Notification
{
    use Queueable;

    public $pengajuan,$user;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($pengajuan, $user)
    {
        $this->pengajuan = $pengajuan;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail','database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new UploadBuktiTransaksiMail($this->pengajuan,$this->user));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'type' => 'notification',
            'title' => 'Upload bukti transaksi',
            'body' => 'Pemohon dengan nomor registrasi '.$this->pengajuan->regId.' telah mengupload bukti transaksi pembayaran'
        ];
    }
}
