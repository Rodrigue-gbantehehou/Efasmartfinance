<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PayementService
{
    public function __construct(
        private MailerInterface $mailer
    ) {}
    
}