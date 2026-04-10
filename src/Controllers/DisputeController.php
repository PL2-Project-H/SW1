<?php

class DisputeController extends BaseController
{
    private DisputeRepository $disputes;
    private ContractRepository $contracts;
    private DisputeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->disputes = new DisputeRepository();
        $this->contracts = new ContractRepository();
        $this->service = new DisputeService();
    }

    public function file(array $data): void
    {
        $userId = $this->requireAuth();
        $id = $this->disputes->create([
            'contract_id' => $this->intField($data, 'contract_id', 1),
            'filed_by'    => $userId,
            'reason'      => $this->stringField($data, 'reason', 2000),
        ]);
        try {
            $this->service->assembleEvidence($id);
        } catch (Throwable $e) {
            
        }
        try {
            $this->service->assignArbitrator($id);
        } catch (Throwable $e) {
            
        }
        Response::json(['id' => $id]);
    }

    public function mine(): void
    {
        $userId = $this->requireAuth();
        Response::json($this->disputes->listMine($userId));
    }

    public function detail(int $id): void
    {
        $this->requireAuth();
        $dispute = $this->disputes->get($id);
        if (!$dispute) {
            Response::error('Dispute not found', 404);
        }
        if ($dispute['evidence_path']) {
            $path = $this->resolveEvidencePath((string) $dispute['evidence_path']);
            $dispute['evidence'] = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        }
        Response::json($dispute);
    }

    private function resolveEvidencePath(string $storedPath): string
    {
        if ($storedPath === '') {
            return '';
        }

        if (str_starts_with($storedPath, 'storage/')) {
            return dirname(__DIR__, 2) . '/' . $storedPath;
        }

        return dirname(__DIR__) . '/' . $storedPath;
    }

    public function safeRoomMessage(array $data): void
    {
        $userId = $this->requireAuth();
        $disputeId = $this->intField($data, 'dispute_id', 1);
        $message = $this->stringField($data, 'message', 8000);
        $dispute = $this->disputes->get($disputeId);
        if (!$dispute || !in_array($dispute['status'], ['open', 'in_mediation'], true)) {
            Response::error('Safe-room is closed', 422);
        }
        $contract = $this->contracts->getContract((int) $dispute['contract_id']);
        $allowed = [(int) $contract['client_id'], (int) $contract['freelancer_id'], (int) $dispute['assigned_admin']];
        if (!in_array($userId, $allowed, true)) {
            Response::error('Forbidden', 403);
        }
        $id = $this->disputes->addMessage($disputeId, $userId, $message);
        Response::json(['id' => $id]);
    }

    public function messages(int $disputeId): void
    {
        $userId = $this->requireAuth();
        $dispute = $this->disputes->get($disputeId);
        if (!$dispute) {
            Response::error('Dispute not found', 404);
        }
        $contract = $this->contracts->getContract((int) $dispute['contract_id']);
        if (!$contract) {
            Response::error('Contract not found', 404);
        }
        $messages = $this->disputes->listMessages($disputeId);
        if ($userId === (int) $dispute['assigned_admin']) {
            Response::json($messages);
        }
        $filtered = array_values(array_filter($messages, fn ($msg) => (int) $msg['sender_id'] === $userId || (int) $msg['sender_id'] === (int) $dispute['assigned_admin']));
        Response::json($filtered);
    }

    public function verdict(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('dispute/verdict');
        $this->service->executeVerdict((int) $data['dispute_id'], (int) $data['client_pct'], (int) $data['freelancer_pct'], $data['verdict']);
        Response::json(['message' => 'Verdict issued']);
    }

    public function appeal(array $data): void
    {
        $userId = $this->requireAuth();
        $this->service->fileAppeal((int) $data['dispute_id'], $userId, $data['reason']);
        Response::json(['message' => 'Appeal filed']);
    }

    public function arbitrators(): void
    {
        $this->requireAuth('admin');
        Response::json($this->disputes->listArbitrators());
    }

    public function assign(array $data): void
    {
        $this->requireAuth('admin');
        (new AuthService())->checkRole('dispute/assign');
        $this->disputes->updateAssignment((int) $data['dispute_id'], (int) $data['admin_id'], 'in_mediation');
        Response::json(['message' => 'Arbitrator assigned']);
    }
}
