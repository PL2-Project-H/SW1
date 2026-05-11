<?php

class User extends DomainModel
{
    public int $id;
    public string $email;
    public string $password_hash;
    public string $role;
    public ?string $admin_role;
    public string $name;
    public string $country;
    public string $timezone;
    public string $kyc_status;
    public ?string $id_type;
    public ?string $csrf_token;
    public string $status;
    public string $created_at;
}
