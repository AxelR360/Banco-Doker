<?php

namespace Core;

class Controller {
    /**
     * Renderiza una vista pasando datos a través de variables locales
     */
    public function render(string $viewPath, array $data = []): void {
        extract($data);
        $file = __DIR__ . '/../Views/' . $viewPath . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            die("Error MVC: La vista '{$viewPath}' no existe en {$file}");
        }
    }

    /**
     * Redirección HTTP
     */
    public function redirect(string $url): void {
        header("Location: {$url}");
        exit;
    }
}
