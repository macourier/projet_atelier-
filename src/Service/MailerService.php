<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Email;
use PDO;
use Throwable;

class MailerService
{
    private string $dsn;
    private ?PDO $pdo;
    private ?SymfonyMailer $mailer = null;

    public function __construct(string $dsn = '', ?PDO $pdo = null)
    {
        $this->dsn = $dsn;
        $this->pdo = $pdo;

        if (!empty($dsn)) {
            try {
                $transport = Transport::fromDsn($dsn);
                $this->mailer = new SymfonyMailer($transport);
            } catch (Throwable $e) {
                // Leave mailer null — we'll fallback to journaling
                $this->mailer = null;
            }
        }
    }

    /**
     * Send an email. If real mailer is not configured, write an entry in journal_emails.
     *
     * @param string $to
     * @param string $subject
     * @param string $body HTML body
     * @param string|null $from
     * @return bool true if sent or queued, false on error
     */
    public function send(string $to, string $subject, string $body, ?string $from = null): bool
    {
        $from = $from ?? ($_ENV['COMPANY_EMAIL'] ?? 'no-reply@example.com');

        // If mailer available, attempt to send
        if ($this->mailer instanceof SymfonyMailer) {
            try {
                $email = (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject($subject)
                    ->html($body);

                $this->mailer->send($email);

                // log success in DB if available
                $this->logEmail($to, $subject, $body, 'sent', null);
                return true;
            } catch (Throwable $e) {
                $this->logEmail($to, $subject, $body, 'error', $e->getMessage());
                return false;
            }
        }

        // Otherwise persist to journal_emails table as pending (dev mode)
        $this->logEmail($to, $subject, $body, 'pending', null);
        return true;
    }

    private function logEmail(string $to, string $subject, string $body, string $status = 'pending', ?string $error = null): void
    {
        if (!$this->pdo) {
            // no DB available; fallback to file log in data/
            $logDir = __DIR__ . '/../../data';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $entry = [
                'to' => $to,
                'subject' => $subject,
                'status' => $status,
                'error' => $error,
                'created_at' => date('c')
            ];
            @file_put_contents($logDir . '/emails.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO journal_emails (to_address, subject, body, status, error_message, created_at) VALUES (:to_address, :subject, :body, :status, :error_message, CURRENT_TIMESTAMP)');
            $stmt->execute([
                'to_address' => $to,
                'subject' => $subject,
                'body' => $body,
                'status' => $status,
                'error_message' => $error
            ]);
        } catch (Throwable $e) {
            // Swallow errors to avoid breaking app flow
        }
    }
}
