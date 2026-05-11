<?php

class Deliverable extends DomainModel
{
    public int $id;
    public int $milestone_id;
    public string $file_path;
    public string $submitted_at;
    public int $revision_count;
    public int $free_revisions_allowed;
    public int $paid_revision_required;
    public float $paid_revision_fee;
    public ?string $client_approved_at;
    public ?string $freelancer_confirmed_at;
    public string $status;
}
