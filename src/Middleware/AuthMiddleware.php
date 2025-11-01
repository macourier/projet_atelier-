<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private array $container;

    public function __construct(array $container = [])
    {
        $this->container = $container;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Simple session-based auth: check for user id in session
        $isLogged = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

        if ($isLogged) {
            // proceed
            return $handler->handle($request);
        }

        // If AJAX/Fetch, return 401 JSON
        $accept = $request->getHeaderLine('Accept') ?? '';
        if (str_contains($accept, 'application/json') || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $resp = new SlimResponse();
            $resp->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $resp->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Otherwise redirect to login
        $response = new SlimResponse();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
