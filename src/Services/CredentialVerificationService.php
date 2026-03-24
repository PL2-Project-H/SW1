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
        $this->freelancers->markCredentialUnderReview($credentialId, $adminId);
        $this->audit->log($adminId, 'credential_under_review', 'credential', $credentialId, ['status' => $credential['status']], ['status' => 'under_review']);
        $this->freelancers->updateCredentialStatus($credentialId, $decision, $adminId);
        if ($decision === 'verified') {
            $this->freelancers->setVerifiedFlag((int) $credential['freelancer_id'], true);
        }
        $this->notifications->send((int) $credential['freelancer_id'], 'credential_status', 'Credential review updated to ' . $decision . '.');
        $this->audit->log($adminId, 'credential_status_change', 'credential', $credentialId, ['status' => 'under_review'], ['status' => $decision]);
    }
}
