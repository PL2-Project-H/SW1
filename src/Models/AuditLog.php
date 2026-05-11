<?php

class AuditLog extends DomainModel
{
    public int $id;
    public ?int $user_id;
    public string $action;
    public string $entity_type;
    public ?int $entity_id;
    public ?string $old_value;
    public ?string $new_value;
    public ?string $ip_address;
    public string $created_at;
}
