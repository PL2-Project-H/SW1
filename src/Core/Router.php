<?php

class Router
{
    public static function action(): string
    {
        $action = $_GET['action'] ?? null;
        if ($action) {
            return trim($action);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        return trim($input['action'] ?? '');
    }

    public static function body(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }
}
