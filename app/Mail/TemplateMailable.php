<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $subject  Resolved, variable-substituted subject line.
     * @param  string  $body     Resolved, variable-substituted body (HTML or plain text).
     * @param  bool    $isHtml   True renders the HTML view; false renders the plain-text view.
     */
    public function __construct(
        string $subject,
        public string $body,
        public bool $isHtml = true,
    ) {
        // Assign to the inherited (untyped) Mailable::$subject property.
        $this->subject = $subject;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        $with = [
            'subject' => $this->subject,
            'body' => $this->body,
        ];

        return $this->isHtml
            ? new Content(view: 'mail.template', with: $with)
            : new Content(text: 'mail.template-plain', with: $with);
    }
}
