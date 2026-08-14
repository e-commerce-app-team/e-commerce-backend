<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public StaffInvitation $invitation;
    public string $storeName;
    public string $deepLink;

    public function __construct(StaffInvitation $invitation, string $storeName)
    {
        $this->invitation = $invitation;
        $this->storeName = $storeName;
        // Point to the backend web route using the local IP so mobile devices can access it
        $baseUrl = 'http://192.168.1.12:8000';
        $this->deepLink = $baseUrl . "/staff/accept-invite?token=" . $invitation->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation to join {$this->storeName} as Staff",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff_invitation',
        );
    }
}
