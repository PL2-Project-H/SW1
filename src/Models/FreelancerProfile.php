<?php

class FreelancerProfile extends DomainModel
{
    public int $id;
    public int $user_id;
    public ?string $bio;
    public string $niche;
    public float $hourly_rate;
    public string $availability_status;
    public string $timezone;
    public ?string $linkedin_url;
    public int $is_verified;
    public int $banner_hidden;
    public int $digest_opt_in;
    public string $created_at;
}
