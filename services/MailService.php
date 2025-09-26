<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * MailService - thin wrapper around PHPMailer with Gmail SMTP defaults.
 * Falls back to logging to logs/<date>.log if sending fails or mail disabled.
 */
class MailService
{
    private PHPMailer $mailer;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = getenv('MAIL_ENABLED') !== 'false';
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    private function configure(): void
    {
        // Basic settings
        $host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $port = getenv('MAIL_PORT') ?: 587;
        $user = getenv('MAIL_USERNAME');
        $pass = getenv('MAIL_PASSWORD'); // For Gmail use App Password
        $from = getenv('MAIL_FROM_ADDRESS') ?: $user;
        $fromName = getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'SOSOL');

        $this->mailer->isSMTP();
        $this->mailer->Host = $host;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $user;
        $this->mailer->Password = $pass;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = (int)$port;
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom($from, $fromName);
    }

    /**
     * Send an email. Returns true on success, false on failure.
     */
    public function send(string $to, string $subject, string $html, ?string $textAlt = null): bool
    {
        if (!$this->enabled) {
            $this->log("MAIL_DISABLED to=$to subject=$subject");
            return false;
        }
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $html;
            $this->mailer->AltBody = $textAlt ?: strip_tags($html);
            $this->mailer->send();
            return true;
        } catch (MailException $e) {
            $this->log('MAIL_ERROR ' . $e->getMessage());
            return false;
        }
    }

    private function log(string $message): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}
