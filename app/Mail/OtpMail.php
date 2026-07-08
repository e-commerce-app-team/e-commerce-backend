<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $userName;
    public string $purpose; // 'verification', 'reset', 'login_2fa'

    public function __construct(string $otpCode, string $userName, string $purpose = 'verification')
    {
        $this->otpCode   = $otpCode;
        $this->userName  = $userName;
        $this->purpose   = $purpose;
    }

    public function envelope(): Envelope
    {
        $subjects = [
            'verification' => 'Verify Your Email - OTP Code',
            'reset'        => 'Reset Your Password - OTP Code',
            'login_2fa'    => 'Login Verification Code',
        ];

        return new Envelope(
            subject: $subjects[$this->purpose] ?? 'Your OTP Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }
}
