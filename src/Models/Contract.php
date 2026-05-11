<?php

class Contract extends DomainModel
{
    public int $id;
    public int $job_id;
    public int $client_id;
    public int $freelancer_id;
    public float $total_amount;
    public string $status;
    public string $started_at;
    public string $scope_text;
    public int $free_revisions_per_milestone;
    public int $partial_release_pct;
    public string $currency;
    public ?string $verdict_at;
}
