<?php

class JobPost extends DomainModel
{
    public int $id;
    public int $client_id;
    public string $title;
    public string $description;
    public string $niche;
    public float $budget;
    public string $deadline;
    public string $status;
    public string $visibility;
    public ?string $niche_metadata;
    public string $currency;
    public string $created_at;
}
