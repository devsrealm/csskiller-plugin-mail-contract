<?php

namespace CSSKillerMailContract;

use Core\events\OnRegisterConsoleCommand;
use Core\interfaces\ExtensionConfig;
use CSSKillerMailContract\commands\CleanupExpiredTokensCommand;
use Devsrealm\TonicsRouterSystem\Route;

class PluginEntry implements ExtensionConfig
{
    const TemplateNameSpace = 'CSSKillerMailContract';

    /**
     * @inheritDoc
     */
    public function events(): array
    {
        return [
            OnRegisterConsoleCommand::class => [
                CleanupExpiredTokensCommand::class
            ]
        ];
    }

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    public function route(Route $routes): Route
    {
        return Routes::routes($routes);
    }

    /**
     * @inheritDoc
     */
    public function templates(): array
    {
        return [
            self::TemplateNameSpace => __DIR__ . '/templates'
        ];
    }
}