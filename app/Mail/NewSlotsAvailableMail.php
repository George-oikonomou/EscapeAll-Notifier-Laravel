<?php

namespace App\Mail;

use App\Models\Room;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSlotsAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Room   $room     The room with new availability
     * @param array  $newSlots Array of ['date' => 'Y-m-d', 'time' => 'HH:MM']
     * @param User   $user     The user being notified
     */
    public function __construct(
        public Room $room,
        public array $newSlots,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        $roomName = $this->room->title ?? $this->room->label;

        return new Envelope(
            subject: "🎉 Νέες διαθέσιμες ώρες: {$roomName}",
        );
    }

    public function content(): Content
    {
        // Group slots by date for display
        $slotsByDate = [];
        foreach ($this->newSlots as $slot) {
            $slotsByDate[$slot['date']][] = $slot['time'];
        }

        // Sort dates and times
        ksort($slotsByDate);
        foreach ($slotsByDate as &$times) {
            sort($times);
        }

        return new Content(
            view: 'emails.new-slots',
            with: [
                'room' => $this->room,
                'user' => $this->user,
                'slotsByDate' => $slotsByDate,
                'totalSlots' => count($this->newSlots),
            ],
        );
    }
}
