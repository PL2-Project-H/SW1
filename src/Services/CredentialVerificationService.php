<?php

class CredentialVerificationService
{
    private FreelancerRepository $freelancers;
    private NotificationService $notifications;
    private AuditService $audit;

    public function __construct()
    {
        $this->freelancers = new FreelancerRepository();
        $this->notifications = new NotificationService();
        $this->audit = new AuditService();
    }

    public function processCredential(int $credentialId, int $adminId, string $decision): void
    {
        $queue = $this->freelancers->listPendingCredentials();
        $credential = null;
        foreach ($queue as $item) {
            if ((int) $item['id'] === $credentialId) {
                $credential = $item;
                break;
            }
        }
        if (!$credential) {
            Response::error('Credential not found in review queue', 404);
        }
        $pdo = Database::getInstance()->getConnection();
        try {
            $pdo->beginTransaction();
            $this->freelancers->updateCredentialStatus($credentialId, $decision, $adminId);
            if ($decision === 'verified') {
                $this->freelancers->setVerifiedFlag((int) $credential['freelancer_id'], true);
            }
            $this->notifications->send((int) $credential['freelancer_id'], 'credential_status', 'Credential review updated to ' . $decision . '.');
            $this->audit->log($adminId, 'credential_status_change', 'credential', $credentialId, ['status' => $credential['status']], ['status' => $decision]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
