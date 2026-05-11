<?php

class EscrowTransaction extends DomainModel
{
    public int $id;
    public int $contract_id;
    public ?int $milestone_id;
    public float $amount;
    public string $currency;
    public string $type;
    public string $status;
    public string $created_at;
    public ?string $cleared_at;
}
