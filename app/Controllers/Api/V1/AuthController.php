<?php

namespace App\Controllers\Api\V1;

use App\Services\AuthService;
use App\Services\MfaService;
use App\Services\MfaChallengeService;
use App\Services\SessionService;

class AuthController extends BaseApiController
{
    public function login()
    {
        $data = $this->request->getJSON(true);

        // Also allow normal POST form data while testing.
        if (!is_array($data)) {
            $data = $this->request->getPost();
        }

        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->error(
                'Username and password are required',
                400
            );
        }

        $authService = new AuthService();
        $result = $authService->authenticate($username, $password);

        if (!$result['success']) {
            if ($result['reason'] === 'access_denied') {
                return $this->error(
                    'You do not have permission to access this application',
                    403
                );
            }

            return $this->error(
                'Invalid username or password',
                401
            );
        }

        $user = $result['user'];
        $userId = (int) $user['id'];

        /*
         * Password authentication has succeeded.
         *
         * Do NOT create a normal authenticated session here.
         * UAsset requires MFA before the full bearer session
         * can be issued.
         */
        $mfaService = new MfaService();
        $mfaState = $mfaService->getState($userId);

        $challengeService = new MfaChallengeService();

        /*
         * Existing YouAudit users who have never configured MFA,
         * or whose MFA is disabled, must complete MFA setup.
         */
        if (
            $mfaState['state'] === 'not_configured' ||
            $mfaState['state'] === 'disabled'
        ) {
            $challenge = $challengeService->createChallenge(
                $userId,
                'setup'
            );

            /*
             * Most legacy YouAudit usernames are email addresses.
             * This is only a setup suggestion for the client.
             * It is NOT considered a verified MFA email.
             */
            $suggestedEmail = filter_var(
                $username,
                FILTER_VALIDATE_EMAIL
            ) ? $username : null;

            return $this->success([
                'authentication_state' => 'mfa_setup_required',
                'challenge_token'      => $challenge['challenge_token'],
                'expires_at'           => $challenge['expires_at'],
                'suggested_email'      => $suggestedEmail,
                'available_methods'    => [
                    'email',
                    'sms',
                    'authenticator',
                ],
            ], 'MFA setup required');
        }

        /*
         * A stored MFA configuration exists but is not usable.
         * Do not allow authentication to continue.
         */
        if ($mfaState['state'] === 'invalid_configuration') {
            return $this->error(
                'MFA configuration is invalid',
                403
            );
        }

        /*
         * MFA is already configured.
         * Create a verification challenge using the user's
         * preferred MFA method.
         */
        if ($mfaState['state'] === 'enabled') {
            $challenge = $challengeService->createChallenge(
                $userId,
                'verification',
                $mfaState['method']
            );

            return $this->success([
                'authentication_state' => 'mfa_verification_required',
                'challenge_token'      => $challenge['challenge_token'],
                'expires_at'           => $challenge['expires_at'],
                'method'               => $mfaState['method'],
            ], 'MFA verification required');
        }

        /*
         * Fail closed if an unexpected MFA state is ever returned.
         */
        return $this->error(
            'Unable to determine MFA authentication state',
            500
        );
    }

    public function logout()
    {
        $sessionId = (int) $this->request->authSessionId;

        if ($sessionId <= 0) {
            return $this->error(
                'Invalid session',
                401
            );
        }

        $sessionService = new SessionService();

        $revoked = $sessionService->revokeSession($sessionId);

        if (!$revoked) {
            return $this->error(
                'Session could not be revoked',
                400
            );
        }

        return $this->success(
            null,
            'Logout successful'
        );
    }
}
