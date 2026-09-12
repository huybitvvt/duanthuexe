<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ForgotPassword extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.forgot-password', ['token' => $this->token]);
    }
}
