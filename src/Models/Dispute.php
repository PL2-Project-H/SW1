<?php

class Dispute extends DomainModel
{
    public int $id;
    public int $contract_id;
    public int $filed_by;
    public string $reason;
    public string $status;
    public ?string $evidence_path;
    public ?int $assigned_admin;
    public ?string $verdict;
    public ?int $client_pct;
    public ?int $freelancer_pct;
    public string $created_at;
}
