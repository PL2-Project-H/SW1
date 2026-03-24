<?php

class BaseController
{
    protected AuthService $auth;
    protected NotificationService $notifications;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->notifications = new NotificationService();
        $this->notifications->checkDeadlines();
        (new MilestoneService())->autoApprove();
    }

    protected function requireAuth(?string $role = null): int
    {
        return $this->auth->requireAuth($role);
    }

    protected function stringField(array $data, string $key, int $maxLength = 255, bool $required = true): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                Response::error("Field {$key} is required", 422);
            }
            return null;
        }

        if (!is_scalar($value)) {
            Response::error("Field {$key} must be a string", 422);
        }

        $clean = trim((string) $value);
        if ($clean === '') {
            if ($required) {
                Response::error("Field {$key} is required", 422);
            }
            return null;
        }

        $clean = strip_tags($clean);
        if (mb_strlen($clean) > $maxLength) {
            Response::error("Field {$key} exceeds {$maxLength} characters", 422);
        }

        return $clean;
    }

    protected function emailField(array $data, string $key): string
    {
        $email = $this->stringField($data, $key, 190);
        $validated = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($validated === false) {
            Response::error("Field {$key} must be a valid email address", 422);
        }

        return strtolower($validated);
    }

    protected function intField(array $data, string $key, int $min = 0, ?int $max = null): int
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            Response::error("Field {$key} is required", 422);
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < $min || ($max !== null && $validated > $max)) {
            Response::error("Field {$key} must be a valid integer", 422);
        }

        return (int) $validated;
    }

    protected function floatField(array $data, string $key, float $min = 0): float
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            Response::error("Field {$key} is required", 422);
        }

        if (!is_scalar($value) || !is_numeric((string) $value)) {
            Response::error("Field {$key} must be numeric", 422);
        }

        $validated = (float) $value;
        if ($validated < $min) {
            Response::error("Field {$key} must be at least {$min}", 422);
        }

        return $validated;
    }

    protected function boolField(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    protected function enumField(array $data, string $key, array $allowed, bool $required = true): ?string
    {
        $value = $this->stringField($data, $key, 120, $required);
        if ($value === null) {
            return null;
        }

        if (!in_array($value, $allowed, true)) {
            Response::error("Field {$key} contains an invalid value", 422);
        }

        return $value;
    }

    protected function dateTimeField(array $data, string $key): string
    {
        $value = $this->stringField($data, $key, 32);
        $formats = ['Y-m-d H:i:s', DATE_ATOM, 'Y-m-d\TH:i:s'];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        Response::error("Field {$key} must be a valid datetime", 422);
    }

    protected function queryString(string $key, int $maxLength = 255): ?string
    {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if ($value === null || $value === false) {
            return null;
        }

        $clean = trim(strip_tags((string) $value));
        if ($clean === '') {
            return null;
        }

        if (mb_strlen($clean) > $maxLength) {
            Response::error("Query parameter {$key} exceeds {$maxLength} characters", 422);
        }

        return $clean;
    }

    protected function queryInt(string $key, int $min = 0): ?int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
        if ($value === null || $value === false) {
            return null;
        }

        if ($value < $min) {
            Response::error("Query parameter {$key} must be a valid integer", 422);
        }

        return (int) $value;
    }

    protected function uploadFile(string $type, int $userId, array $allowedExtensions): string
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload failed', 422);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 20 * 1024 * 1024) {
            Response::error('File exceeds 20MB limit', 422);
        }
        $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            Response::error('Invalid file type', 422);
        }
        $dir = dirname(__DIR__) . '/uploads/' . $type . '/' . $userId;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                Response::error('Upload directory could not be created', 500);
            }
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            Response::error('Uploaded file could not be stored', 500);
        }
        return 'uploads/' . $type . '/' . $userId . '/' . $name;
    }
}
