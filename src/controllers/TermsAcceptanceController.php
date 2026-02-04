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

namespace CSSKillerMailContract\controllers;

use CSSKillerMailContract\services\TokenSignatureService;

/**
 * Example controller demonstrating the Token Signature Service
 * for email confirmation in terms acceptance flow
 */
class TermsAcceptanceController
{

    private TokenSignatureService $tokenSignatureService;

    public function __construct(TokenSignatureService $tokenSignatureService)
    {
        $this->tokenSignatureService = $tokenSignatureService;
    }

    /**
     * Display the terms acceptance form
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function showTermsForm(): void
    {
        view('CSSKillerMailContract::terms-form');
    }

    /**
     * Handle terms submission - generate token and send email
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function submitTerms(): void
    {
        $email = input()->fromPost()->retrieve('email', '');

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            response()->json(['error' => 'Invalid email address'], 400);
            return;
        }

        try {
            // Generate confirmation link
            $confirmation = $this->tokenSignatureService->generateConfirmationLink(
                email: $email,
                baseUrl: env('BASE_URL') . '/confirm-terms',
                metadata: [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'form_type' => 'terms_acceptance',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );

            // Send confirmation email
            $this->sendConfirmationEmail($email, $confirmation['url'], $confirmation['expires_at']);

            response()->json([
                'success' => true,
                'message' => 'Please check your email to confirm your acceptance. The confirmation link will expire in 24 hours.',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            response()->json([
                'error' => 'Failed to process request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle token confirmation when user clicks email link
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function confirmTerms(): void
    {
        $token = input()->fromGet()->retrieve('token', '');
        if (empty($token)) {
            $this->renderError('No confirmation token provided');
            return;
        }

        try {
            // Validate the token
            $result = $this->tokenSignatureService->validateToken($token);

            if (!$result['valid']) {
                $this->renderError($result['message']);
                return;
            }

            // Token is valid - mark as confirmed
            $confirmed = $this->tokenSignatureService->markAsConfirmed(
                token: $token,
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
                additionalData: [
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'confirmed_at' => date('Y-m-d H:i:s'),
                    'referer' => $_SERVER['HTTP_REFERER'] ?? ''
                ]
            );

            if ($confirmed) {
                $email = $result['data']['email'];

                // Here you can store the acceptance in your own database
                // $this->storeTermsAcceptanceRecord($email, $token, $result['data']);

                $this->renderSuccess($email, $result['data']);
            } else {
                $this->renderError('Unable to confirm. This token may have already been used.');
            }

        } catch (\Exception $e) {
            $this->renderError('An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Check status of tokens for an email
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function checkTokenStatus(): void
    {
        $email = input()->fromGet()->retrieve('email', '');

        if (empty($email)) {
            response()->json(['error' => 'Email required'], 400);
            return;
        }

        try {
            $tokens = $this->tokenSignatureService->getTokensByEmail($email);

            response()->json([
                'email' => $email,
                'total_tokens' => count($tokens),
                'tokens' => $tokens
            ]);

        } catch (\Exception $e) {
            response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send the confirmation email
     */
    private function sendConfirmationEmail(string $email, string $confirmUrl, string $expiresAt): void
    {
        // This is a simple example using PHP's mail() function
        // In production, you'd use a proper email service (PHPMailer, SendGrid, etc.)
        // THIS IS AN EXAMPLE PLEASE REPLACE WITH PHPMAILER OR ANY OTHER EMAIL SENDING LIBRARY

        $subject = 'Confirm Your Terms Acceptance';

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { 
                    display: inline-block; 
                    padding: 12px 24px; 
                    background: #4CAF50; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .footer { font-size: 12px; color: #666; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Confirm Your Terms Acceptance</h2>
                <p>Hello,</p>
                <p>Thank you for accepting our terms and conditions. Please confirm your acceptance by clicking the button below:</p>
                <p>
                    <a href='$confirmUrl' class='button'>Confirm Acceptance</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #f5f5f5; padding: 10px;'>$confirmUrl</p>
                <div class='footer'>
                    <p><strong>Important:</strong> This confirmation link will expire at $expiresAt (24 hours from now).</p>
                    <p>If you did not request this, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: noreply@' . parse_url(env('BASE_URL'), PHP_URL_HOST),
            'Reply-To: support@' . parse_url(env('BASE_URL'), PHP_URL_HOST)
        ];

        // Send email
        mail($email, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Render success page
     *
     * @throws \Exception
     */
    private function renderSuccess(string $email, array $data): void
    {
        view('CSSKillerMailContract::terms-success', [
            'email' => $email,
            'confirmed_at' => date('F j, Y \a\t g:i A')
        ]);
    }

    /**
     * Render error page
     *
     * @throws \Exception
     * @throws \Throwable
     */
    private function renderError(string $message): void
    {
        view('CSSKillerMailContract::terms-error', [
            'message' => $message
        ]);
    }
}
