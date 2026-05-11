<?php

class Milestone extends DomainModel
{
    public int $id;
    public int $contract_id;
    public string $title;
    public float $amount;
    public int $order_index;
    public string $status;
    public string $due_date;
    public ?int $dependency_milestone_id;
    public ?string $started_at;
    public ?string $approved_at;
}
