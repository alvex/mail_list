<?php
namespace App\Core;

/**
 * Bas-controller för MVC-lagret.
 *
 * Ger hjälpfunktioner för att rendera views och returnera JSON-svar.
 * Samlar gemensam funktionalitet som alla controllers kan återanvända.
 */
class Controller
{
    /**
     * Rendera en view-fil och gör data tillgänglig som variabler.
     *
     * Sökordning:
     *  1. `app/Views/...` (Versal V – nuvarande struktur)
     *  2. `app/views/...` (gemensam fallback, t.ex. på case-insensitive FS)
     *
     * @param string $view Namn på view (t.ex. 'home/index').
     * @param array  $data Assoc-array med data som ska extraheras till variabler.
     *
     * @return void
     */
    public function view($view, $data = [])
    {
        // Extrahera $data till lokala variabler för enklare templating.
        extract($data);

        $viewFileLimit = __DIR__ . '/../Views/' . $view . '.php'; // Först: nuvarande Views-katalog.
        if (file_exists($viewFileLimit)) {
            require_once $viewFileLimit;
        } else {
            // Fallback till lowercase 'views' för kompatibilitet (t.ex. historik/andra system).
            require_once __DIR__ . '/../views/' . $view . '.php';
        }
    }

    /**
     * Returnera ett JSON-svar och avsluta scriptet.
     *
     * Sätter rätt Content-Type-header och använder UTF-8-vänliga flaggor.
     *
     * @param mixed $data Godtycklig data som kan json-kodas.
     *
     * @return void
     */
    public function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
