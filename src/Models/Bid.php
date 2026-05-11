<?php

class Bid extends DomainModel
{
    public int $id;
    public int $job_id;
    public int $freelancer_id;
    public float $amount;
    public string $proposal_text;
    public int $version;
    public string $status;
    public string $submitted_at;
    public string $expires_at;
}
