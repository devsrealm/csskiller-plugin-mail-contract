<?php
/*
 *     Copyright (c) 2022-2025. Olayemi Faruq <olayemi@tonics.app>
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

namespace CSSKillerMailContract\eventHandlers;

use Core\events\OnRegisterConsoleCommand;
use CSSKillerMailContract\commands\CleanupExpiredTokensCommand;
use Devsrealm\TonicsEventSystem\Interfaces\HandlerInterface;
use Devsrealm\TonicsTemplateSystem\Exceptions\TonicsTemplateLoaderError;

/**
 * Example Event Handler for Registering Custom Console Commands
 *
 * This is a template/example showing how plugins can register their own console commands.
 *
 * To use this pattern in your plugin:
 * 1. Create your custom command class that implements ConsoleCommand interface
 * 2. Create an event handler like this one
 * 3. Register the handler in your PluginEntry::events() method
 *
 * Example:
 * ```
 * OnRegisterConsoleCommand::class => [
 *     MyCustomCommandHandler::class
 * ]
 * ```
 */
class CustomCommandRegistrar implements HandlerInterface
{
    /**
     * Handle the OnRegisterConsoleCommand event
     *
     * @param object $event OnRegisterConsoleCommand event instance
     * @return void
     * @throws TonicsTemplateLoaderError
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function handleEvent(object $event): void
    {
        $core = core(true);
        /** @var OnRegisterConsoleCommand $event */
        $event->addCommand(new CleanupExpiredTokensCommand($core));
    }
}
