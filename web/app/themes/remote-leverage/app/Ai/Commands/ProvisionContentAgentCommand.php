<?php

declare(strict_types=1);

namespace App\Ai\Commands;

use App\Ai\Provisioning\ContentAgentProvisioner;
use Illuminate\Console\Command;
use Throwable;

class ProvisionContentAgentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:ai:agent
        {--rotate : Issue a new application password, revoking the previous one}
        {--revoke : Remove this tool\'s credentials and managed capabilities}
        {--status : Show the current state without changing anything}';

    /**
     * @var string
     */
    protected $description = 'Reconcile the MCP content-agent user against config/ai-wordpress.php. '.
        'Run with no options to create it and align its capabilities (idempotent; this is what rl:deploy calls). '.
        'Use --rotate to issue an application password, which is printed once and cannot be retrieved again.';

    public function handle(ContentAgentProvisioner $provisioner): int
    {
        try {
            if ($this->option('status')) {
                return $this->showStatus($provisioner);
            }

            if ($this->option('revoke')) {
                $provisioner->revoke();
                $this->info('Revoked the content agent\'s application passwords and managed capabilities.');

                return self::SUCCESS;
            }

            $this->reconcile($provisioner);

            if ($this->option('rotate')) {
                return $this->rotate($provisioner);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function reconcile(ContentAgentProvisioner $provisioner): void
    {
        $result = $provisioner->ensure();
        $login = $provisioner->status()['login'];

        $this->info($result['created']
            ? "Created the content agent user \"{$login}\"."
            : "Content agent user \"{$login}\" already exists.");

        foreach ($result['granted'] as $capability) {
            $this->line("  granted  {$capability}");
        }

        foreach ($result['revoked'] as $capability) {
            $this->line("  revoked  {$capability}");
        }

        if ($result['granted'] === [] && $result['revoked'] === []) {
            $this->line('  capabilities already match config.');
        }
    }

    private function rotate(ContentAgentProvisioner $provisioner): int
    {
        $credential = $provisioner->mintPassword();

        $this->newLine();
        $this->info('New application password (shown once — it cannot be retrieved again):');
        $this->newLine();
        $this->line("  user:     {$credential['user_login']}");
        $this->line("  password: {$credential['password']}");
        $this->newLine();
        $this->comment('Set this as STAGING_MCP_APP_PASSWORD or PRODUCTION_MCP_APP_PASSWORD in the');
        $this->comment('shell that runs Claude Code. It is read by .mcp.json at the repository root.');

        return self::SUCCESS;
    }

    private function showStatus(ContentAgentProvisioner $provisioner): int
    {
        $status = $provisioner->status();

        $this->line("login:             {$status['login']}");
        $this->line('exists:            '.($status['exists'] ? 'yes' : 'no'));
        $this->line("role:              {$status['role']}");
        $this->line("application passwords: {$status['password_count']}");
        $this->newLine();
        $this->line('capabilities:');

        foreach ($status['capabilities'] as $capability => $granted) {
            $this->line(sprintf('  %-24s %s', $capability, $granted ? 'yes' : 'no'));
        }

        if (! $status['available']) {
            $this->newLine();
            $this->warn('Cannot issue application passwords: '.$status['unavailable_reason']);
        }

        return self::SUCCESS;
    }
}
