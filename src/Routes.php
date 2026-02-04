<?php

namespace CSSKillerMailContract;


use CSSKillerMailContract\controllers\TermsAcceptanceController;
use Devsrealm\TonicsRouterSystem\Route;

class Routes
{
    /**
     * @param Route $route
     *
     * @return Route
     * @throws \ReflectionException
     */
    public static function routes(Route $route): Route
    {
        // Terms Acceptance Routes
        $route->group('terms', function (Route $route) {
            // Display terms acceptance form
            $route->get('', [TermsAcceptanceController::class, 'showTermsForm']);
            // Submit terms acceptance (generates token and sends email)
            $route->post('submit', [TermsAcceptanceController::class, 'submitTerms']);
            // Confirm terms via email link
            $route->get('confirm', [TermsAcceptanceController::class, 'confirmTerms']);
            // Check token status by email (optional utility endpoint)
            $route->get('status', [TermsAcceptanceController::class, 'checkTokenStatus']);
        });

        return $route;
    }

}