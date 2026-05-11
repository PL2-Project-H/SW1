<?php

class Credential extends DomainModel
{
    public int $id;
    public int $freelancer_id;
    public string $type;
    public string $file_path;
    public ?string $metadata_json;
    public string $status;
    public string $submitted_at;
    public ?string $reviewed_at;
    public ?int $reviewer_id;
}
