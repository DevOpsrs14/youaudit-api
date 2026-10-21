<?php

namespace App\Services;

use Config\Database;

class MfaOtpService
{
    private $db;

    private const OTP_EXPIRY_MINUTES = 5;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function generateOtp(
        int $challengeId,
        string $destination
    ): array {
        $destination = trim($destination);

        if ($destination === '') {
            throw new \InvalidArgumentException(
                'MFA destination is required.'
            );
        }

        $otp = (string) random_int(100000, 999999);

        /*
         * Store only a hash of the OTP.
         * Plain OTP is returned temporarily for development testing.
         */
        $otpHash = hash('sha256', $otp);

        $expiresAt = date(
            'Y-m-d H:i:s',
            strtotime('+' . self::OTP_EXPIRY_MINUTES . ' minutes')
        );

        $this->db
            ->table('mfa_challenges')
            ->where('id', $challengeId)
            ->where('completed_at', null)
            ->update([
                'destination'    => $destination,
                'otp_hash'       => $otpHash,
                'otp_expires_at' => $expiresAt,
                'attempt_count'  => 0,
            ]);

        if ($this->db->affectedRows() !== 1) {
            throw new \RuntimeException(
                'Unable to generate MFA OTP.'
            );
        }

        return [
            'otp'        => $otp,
            'expires_at' => $expiresAt,
            'expires_in' => 300,
        ];
    }
}
