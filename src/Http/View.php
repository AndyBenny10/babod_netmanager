<?php

declare(strict_types=1);

namespace Babod\NetManager\Http;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Sablon nem található: ' . $template);
        }

        ob_start();
        require $templatePath;
        $content = ob_get_clean();

        $title = $data['title'] ?? 'Babod NetManager';
        require dirname(__DIR__, 2) . '/templates/layout.php';
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

  /** @return list<array{type: string, message: string}> */
    public static function consumeFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        return is_array($messages) ? $messages : [];
    }
}
