<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AuthController
{
    private array $container;
    private $pdo;
    private $twig;

    public function __construct(?array $container = null)
    {
        $this->container = $container ?? [];
        $this->pdo = $this->container['pdo'] ?? null;
        $this->twig = $this->container['twig'] ?? null;
    }

    public function showLogin(Request $request, Response $response): Response
    {
        $body = $this->twig->render('auth/login.twig', [
            'error' => $_SESSION['auth_error'] ?? null
        ]);
        // clear error
        unset($_SESSION['auth_error']);
        $response->getBody()->write($body);
        return $response;
    }

    public function handleLogin(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password) || !$this->pdo) {
            $_SESSION['auth_error'] = 'Email et mot de passe requis.';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $stmt = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $_SESSION['auth_error'] = 'Identifiants invalides.';
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
