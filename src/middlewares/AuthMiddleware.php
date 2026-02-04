<?php

namespace CSSKillerMailContract\middlewares;

use Devsrealm\TonicsRouterSystem\Events\OnRequestProcess;
use Devsrealm\TonicsRouterSystem\Interfaces\TonicsRouterRequestInterceptorInterface;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateLoaderError;

class AuthMiddleware implements TonicsRouterRequestInterceptorInterface
{
    /**
     * @param OnRequestProcess $request
     * @return void
     * @throws TonicsTemplateLoaderError
     * @throws \ReflectionException
     */
    public function handle(OnRequestProcess $request): void
    {
        $provided = '';

        // Check Authorization Bearer header first
        $bearer = $request->getBearerToken();
        if (is_string($bearer) && $bearer !== '') {
            $provided = $bearer;
        } else {
            // Fallback to token query parameter
            if ($request->hasParamAndValue('token')) {
                $provided = (string)$request->getParam('token');
            }
        }

        $expected = $this->getSharedSecret();
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            response()->onError(401, 'Unauthorized access');
        }
    }

    private function getSharedSecret(): string
    {
        $candidates = [
            'COMMANDER_UI_SECRET'
        ];

        foreach ($candidates as $key) {
            $val = env($key);
            if (is_string($val) && $val !== '') {
                return $val;
            }
        }

        return '';
    }
}
