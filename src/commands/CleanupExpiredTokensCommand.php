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

namespace CSSKillerMailContract\commands;

use Core\Core;
use CSSKillerMailContract\services\TokenSignatureService;
use Devsrealm\TonicsConsole\Interfaces\ConsoleCommand;
use Devsrealm\TonicsConsole\Interfaces\DescribedConsoleCommand;
use Devsrealm\TonicsConsole\Interfaces\HumanReadableResult;

/**
 * Cleanup expired email confirmation tokens that are still pending
 *
 * NOTE: This only removes expired tokens that were never confirmed.
 * Confirmed tokens are kept permanently as audit records.
 *
 * Run: `php console --cleanup:terms:tokens`
 */
class CleanupExpiredTokensCommand implements ConsoleCommand, DescribedConsoleCommand, HumanReadableResult
{
    private Core $core;
    private array $humanReadableResult = [];

    /**
     * @throws \Exception
     */
    public function __construct(Core $core)
    {
        $this->core = $core;
    }

    public function name(): string
    {
        return 'cleanup:terms:tokens';
    }


    public function required(): array
    {
        return [
            '--cleanup:terms:tokens'
        ];
    }

    public function usage(): string
    {
        return 'php console --cleanup:terms:tokens';
    }

    public function description(): string
    {
        return 'Removes expired pending email confirmation tokens (keeps confirmed tokens as audit records)';
    }

    /**
     * @param array $commandOptions
     * @throws \Exception
     */
    public function run(array $commandOptions): void
    {
        $this->core->infoMessage("Starting cleanup of expired pending tokens...\n");
        $this->core->infoMessage("NOTE: Confirmed tokens are preserved as permanent audit records.\n");

        try {
            $tokenService = new TokenSignatureService($this->core);
            $deletedCount = $tokenService->cleanupExpiredTokens();

            $this->addHumanReadableResult("Cleanup completed successfully");
            $this->addHumanReadableResult("Deleted $deletedCount expired pending token(s)");
            $this->addHumanReadableResult("Confirmed tokens were preserved");

            $this->core->successMessage("✓ Cleanup completed: $deletedCount expired pending token(s) removed\n");
            $this->core->successMessage("✓ All confirmed tokens preserved for audit purposes\n");

        } catch (\Exception $e) {
            $this->addHumanReadableResult("Cleanup failed: " . $e->getMessage());
            $this->core->errorMessage("✗ Cleanup failed: " . $e->getMessage() . "\n");
        }
    }

    /**
     * @inheritDoc
     */
    public function getHumanReadableResult(): array
    {
        return $this->humanReadableResult;
    }

    private function addHumanReadableResult(string $message): void
    {
        $this->humanReadableResult[] = $message;
    }
}
