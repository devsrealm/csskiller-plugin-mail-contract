<?php
/*
 *     Copyright (c) 2022-2024. Olayemi Faruq <olayemi@tonics.app>
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU Affero General Public License as
 *     published by the Free Software Foundation, either version 3 of the
 *     License, or (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU Affero General Public License for more details.
 *
 *     You should have received a copy of the GNU Affero General Public License
 *     along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace CSSKillerMailContract\services;

use Core\Core;
use Devsrealm\TonicsQueryBuilder\TonicsQuery;
use Exception;

/**
 * TokenSignatureService
 *
 * Manages email confirmation tokens for terms acceptance and other email verification flows.
 * Tokens are stored in the system_global table with context MAIL_SIGN.
 *
 * Example usage:
 * ```php
 * $service = new TokenSignatureService(new Core());
 *
 * // Generate and send confirmation email
 * $token = $service->generateToken('user@example.com');
 * $confirmUrl = "https://yoursite.com/confirm?token=" . $token;
 * // Send email with $confirmUrl
 *
 * // Validate token when user clicks link
 * $result = $service->validateToken($_GET['token']);
 * if ($result['valid']) {
 *     echo "Email: " . $result['data']['email'];
 *     // Mark as confirmed
 *     $service->markAsConfirmed($_GET['token'], $_SERVER['REMOTE_ADDR']);
 * }
 * ```
 */
class TokenSignatureService
{
    private const TOKEN_EXPIRY_HOURS = 24;

    public function __construct(private Core $core){}

    /**
     * Generate a secure token for email confirmation
     *
     * @param string $email The email address to associate with this token
     * @param array $metadata Additional metadata to store (optional)
     * @return string The generated token
     * @throws Exception
     */
    public function generateToken(string $email, array $metadata = []): string
    {
        $token = bin2hex(random_bytes(32)); // 64 character secure token
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRY_HOURS . ' hours'));

        $data = [
            'email' => $email,
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'metadata' => $metadata
        ];

        $this->core->db(onGetDB: function (TonicsQuery $db) use ($token, $data) {
            $db->Insert(
                Core::getTable(Core::SYSTEM_GLOBAL),
                [
                    'context' => Core::CONTEXT_MAIL_SIGN,
                    'key' => $token,
                    'value_json' => json_encode($data)
                ]
            );
        });

        return $token;
    }

    /**
     * Validate a token and return its data
     *
     * @param string $token The token to validate
     * @return array Returns ['valid' => bool, 'data' => array|null, 'message' => string]
     * @throws Exception
     */
    public function validateToken(string $token): array
    {
        $result = null;

        $this->core->db(onGetDB: function (TonicsQuery $db) use ($token, &$result) {
            $result = $db
                ->Select('id, value_json')
                ->From(Core::getTable(Core::SYSTEM_GLOBAL))
                ->WhereEquals('context', Core::CONTEXT_MAIL_SIGN)
                ->WhereEquals('key', $token)
                ->FetchFirst();
        });

        if (!$result) {
            return [
                'valid' => false,
                'data' => null,
                'message' => 'Invalid token'
            ];
        }

        $data = json_decode($result->value_json, true);

        // Check if token has expired
        if (isset($data['expires_at']) && strtotime($data['expires_at']) < time()) {
            return [
                'valid' => false,
                'data' => $data,
                'message' => 'Token has expired'
            ];
        }

        // Check if already confirmed
        if (isset($data['status']) && $data['status'] === 'confirmed') {
            return [
                'valid' => false,
                'data' => $data,
                'message' => 'Token already used'
            ];
        }

        return [
            'valid' => true,
            'data' => $data,
            'message' => 'Token is valid'
        ];
    }

    /**
     * Mark a token as confirmed and store confirmation metadata
     *
     * @param string $token The token to confirm
     * @param string|null $ipAddress The IP address of the confirming user
     * @param array $additionalData Any additional data to store
     * @return bool Success status
     * @throws Exception
     */
    public function markAsConfirmed(string $token, ?string $ipAddress = null, array $additionalData = []): bool
    {
        $validation = $this->validateToken($token);

        if (!$validation['valid']) {
            return false;
        }

        $data = $validation['data'];
        $data['status'] = 'confirmed';
        $data['confirmed_at'] = date('Y-m-d H:i:s');
        $data['ip_address'] = $ipAddress;
        $data['confirmation_data'] = $additionalData;

        $success = false;
        $this->core->db(onGetDB: function (TonicsQuery $db) use ($token, $data, &$success) {
            $result = $db
                ->Q()
                ->Update(Core::getTable(Core::SYSTEM_GLOBAL))
                ->Set('value_json', json_encode($data))
                ->Set('updated_at', date('Y-m-d H:i:s'))
                ->WhereEquals('context', Core::CONTEXT_MAIL_SIGN)
                ->WhereEquals('key', $token)
                ->Exec();

            $success = $result !== false;
        });

        return $success;
    }

    /**
     * Get token data by email address
     *
     * @param string $email The email to search for
     * @param string|null $status Filter by status (optional): 'pending', 'confirmed', 'expired'
     * @return array Array of token data
     * @throws Exception
     */
    public function getTokensByEmail(string $email, ?string $status = null): array
    {
        $results = [];

        $this->core->db(onGetDB: function (TonicsQuery $db) use ($email, $status, &$results) {
            $query = $db
                ->Select('id, key, value_json, created_at, updated_at')
                ->From(Core::getTable(Core::SYSTEM_GLOBAL))
                ->WhereEquals('context', Core::CONTEXT_MAIL_SIGN);

            $allResults = $query->FetchResult();

            // Filter by email in JSON
            foreach ($allResults as $row) {
                $data = json_decode($row->value_json, true);
                if (isset($data['email']) && $data['email'] === $email) {
                    // Apply status filter if provided
                    if ($status === null || (isset($data['status']) && $data['status'] === $status)) {
                        $results[] = [
                            'id' => $row->id,
                            'token' => $row->key,
                            'data' => $data,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at
                        ];
                    }
                }
            }
        });

        return $results;
    }

    /**
     * Delete expired tokens that are still pending (unconfirmed)
     *
     * NOTE: Confirmed tokens are NEVER deleted - they serve as permanent records
     * for compliance and audit purposes.
     *
     * @return int Number of tokens deleted
     * @throws Exception
     */
    public function cleanupExpiredTokens(): int
    {
        $deletedCount = 0;

        $this->core->db(onGetDB: function (TonicsQuery $db) use (&$deletedCount) {
            // Get all MAIL_SIGN tokens
            $results = $db
                ->Select('id, value_json')
                ->From(Core::getTable(Core::SYSTEM_GLOBAL))
                ->WhereEquals('context', Core::CONTEXT_MAIL_SIGN)
                ->FetchResult();

            $idsToDelete = [];
            foreach ($results as $row) {
                $data = json_decode($row->value_json, true);

                // Only delete if:
                // 1. Token has expired AND
                // 2. Token is still pending (not confirmed)
                $isExpired = isset($data['expires_at']) && strtotime($data['expires_at']) < time();
                $isPending = !isset($data['status']) || $data['status'] === 'pending';

                if ($isExpired && $isPending) {
                    $idsToDelete[] = $row->id;
                }
            }

            if (!empty($idsToDelete)) {
                foreach ($idsToDelete as $id) {
                    $db
                        ->Q()
                        ->Delete(Core::getTable(Core::SYSTEM_GLOBAL))
                        ->WhereEquals('id', $id)
                        ->Exec();
                    $deletedCount++;
                }
            }
        });

        return $deletedCount;
    }

    /**
     * Get confirmation URL with token
     *
     * @param string $token The token
     * @param string $baseUrl The base URL (e.g., 'https://yoursite.com/confirm')
     * @return string The full confirmation URL
     */
    public function getConfirmationUrl(string $token, string $baseUrl): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . 'token=' . urlencode($token);
    }

    /**
     * Generate token and get confirmation URL in one call
     *
     * @param string $email The email address
     * @param string $baseUrl The base confirmation URL
     * @param array $metadata Additional metadata
     * @return array Returns ['token' => string, 'url' => string, 'expires_at' => string]
     * @throws Exception
     */
    public function generateConfirmationLink(string $email, string $baseUrl, array $metadata = []): array
    {
        $token = $this->generateToken($email, $metadata);
        $url = $this->getConfirmationUrl($token, $baseUrl);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRY_HOURS . ' hours'));

        return [
            'token' => $token,
            'url' => $url,
            'expires_at' => $expiresAt
        ];
    }
}
