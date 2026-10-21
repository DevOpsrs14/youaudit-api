<?php

namespace App\Filters;

use App\Services\SessionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (
            $authorization === '' ||
            !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
        ) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication required',
                    'errors'  => null,
                ]);
        }

        $token = trim($matches[1]);

        $sessionService = new SessionService();
        $session = $sessionService->validateToken($token);

        if ($session === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired session',
                    'errors'  => null,
                ]);
        }

        /*
         * Make authenticated identity available to controllers.
         *
         * Controllers must use this server-resolved user ID
         * instead of trusting user_id sent by the client.
         */
        $request->authUserId = (int) $session['user_id'];
        $request->authSessionId = (int) $session['id'];

        return null;
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ) {
        // Nothing required after the request.
    }
}
