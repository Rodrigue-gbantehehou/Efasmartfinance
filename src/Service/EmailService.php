<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer
    ) {}

    public function send(
        string $to,
        string $subject,
        string $htmlContent
    ): void {
        $email = (new Email())
            ->from('EFA Smart Finance <contact@binajia.org>')
            ->to($to)
            ->subject($subject)
            ->html($htmlContent);

        $this->mailer->send($email);
    }
}
