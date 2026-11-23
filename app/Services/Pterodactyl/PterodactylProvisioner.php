<?php

namespace App\Services\Pterodactyl;

use App\Models\ProvisionTask;
use App\Models\Service;
use App\Models\User;
use App\Services\Mail\MailSettings;
use Illuminate\Support\Str;
use RuntimeException;

class PterodactylProvisioner
{
    public function syncUserPassword(User $user, string $password): void
    {
        $client = PterodactylSettings::client();
        $panelUser = null;
        foreach ($client->users((string) $user->email) as $candidate) {
            if ((string) data_get($candidate, 'attributes.email') === (string) $user->email) {
                $panelUser = (array) data_get($candidate, 'attributes');
                break;
            }
        }
        if ($panelUser === []) {
            return;
        }

        $client->updateUser((int) $panelUser['id'], [
            'email' => $panelUser['email'],
            'username' => $panelUser['username'],
            'first_name' => $panelUser['first_name'],
            'last_name' => $panelUser['last_name'],
            'password' => $password,
        ]);
    }

    public function dispatch(ProvisionTask $task, Service $service): void
    {
        $client = PterodactylSettings::client();
        $operation = (string) $task->operation;

        if ($operation === 'create') {
            $this->create($client, $task, $service);

            return;
        }

        $serverId = $this->serverId($service);
        if ($serverId === '') {
            throw new RuntimeException('实例尚未绑定翼龙服务器 ID。');
        }

        match ($operation) {
            'suspend' => $client->suspendServer($serverId),
            'unsuspend' => $client->unsuspendServer($serverId),
            'stop' => $client->power($serverId, 'stop'),
            'start' => $client->power($serverId, 'start'),
            'reboot' => $client->power($serverId, 'restart'),
            'terminate' => $client->deleteServer($serverId),
            'reinstall' => $client->reinstall($serverId),
            default => throw new RuntimeException('翼龙面板暂不支持该操作：'.$operation),
        };

        $service->update([
            'status' => match ($operation) {
                'terminate' => 'terminated',
                'suspend', 'stop' => 'suspended',
                default => 'active',
            },
            'provision_status' => 'succeeded',
        ]);

        $task->update([
            'status' => 'succeeded',
            'completed_at' => now(),
            'last_error' => null,
            'result' => ['operation' => $operation, 'server_id' => $serverId],
        ]);
    }

    private function create(PterodactylClient $client, ProvisionTask $task, Service $service): void
    {
        $existing = $this->serverId($service);
        if ($existing !== '') {
            $task->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'last_error' => null,
                'result' => ['server_id' => $existing, 'reused' => true],
            ]);

            return;
        }

        $service->loadMissing(['user', 'product']);
        $panelUser = $this->ensurePanelUser($client, $service);
        $panel = (array) data_get($service->product?->metadata, 'pterodactyl', []);
        $eggId = (int) ($panel['egg_id'] ?? 0);
        $nestId = (int) ($panel['nest_id'] ?? 0);
        $nodeId = (int) ($panel['node_id'] ?? 0);
        if ($eggId < 1 || $nestId < 1 || $nodeId < 1) {
            throw new RuntimeException('商品未配置翼龙 Nest / Egg / Node。');
        }

        $egg = $this->egg($client, $nestId, $eggId);
        $environment = $this->environment($egg, (array) ($panel['environment'] ?? []));
        $dockerImage = (string) ($panel['docker_image'] ?? data_get($egg, 'docker_image') ?? '');
        $startup = (string) ($panel['startup'] ?? data_get($egg, 'startup') ?? '');
        if ($dockerImage === '' || $startup === '') {
            throw new RuntimeException('翼龙 Egg 缺少 Docker 镜像或启动命令。');
        }

        $specs = is_array($service->specs) ? $service->specs : [];
        // 以实例实际选择的规格为准：商品里的 limits 只在实例缺少对应规格时作为缺省值。
        $limits = array_merge([
            'swap' => 0,
            'io' => 500,
        ], (array) ($panel['limits'] ?? []), [
            'memory' => max(128, (int) (($specs['memory_gb'] ?? 1) * 1024)),
            'disk' => max(512, (int) (($specs['disk_gb'] ?? 5) * 1024)),
            'cpu' => max(100, (int) (($specs['cpu'] ?? 1) * 100)),
        ]);
        $features = array_merge([
            'databases' => 0,
            'allocations' => 1,
            'backups' => 0,
        ], (array) ($panel['feature_limits'] ?? []));

        try {
            $server = $client->createServer([
                'name' => Str::limit($service->name ?: $service->service_no, 64, ''),
                'user' => $panelUser,
                'egg' => $eggId,
                'docker_image' => $dockerImage,
                'startup' => $startup,
                'environment' => $environment,
                'limits' => $limits,
                'feature_limits' => $features,
                'deploy' => [
                    'locations' => [$this->locationId($client, $nodeId)],
                    'dedicated_ip' => false,
                    'port_range' => [],
                ],
                'start_on_completion' => true,
            ]);
        } catch (\Throwable $exception) {
            $existingServer = $this->findServer($client, $service, $panelUser);
            if ($existingServer === []) {
                throw $exception;
            }
            $server = $existingServer;
        }

        $serverId = (string) (data_get($server, 'attributes.id') ?? data_get($server, 'id') ?? '');
        if ($serverId === '') {
            throw new RuntimeException('翼龙面板未返回服务器 ID。');
        }

        $specs['pterodactyl'] = [
            'server_id' => (int) $serverId,
            'panel_user_id' => (int) $panelUser,
            'nest_id' => $nestId,
            'egg_id' => $eggId,
            'node_id' => $nodeId,
            'email' => (string) $service->user?->email,
            'password' => (string) ($service->user?->pterodactyl_password ?: ''),
        ];
        $service->update([
            'status' => 'active',
            'provision_status' => 'succeeded',
            'external_ref' => 'pterodactyl:'.$serverId,
            'specs' => $specs,
        ]);

        $task->update([
            'status' => 'succeeded',
            'completed_at' => now(),
            'last_error' => null,
            'result' => ['server_id' => (int) $serverId, 'panel_user_id' => (int) $panelUser],
        ]);

        if ($service->user) {
            MailSettings::sendTemplate('service_provisioned', $service->user, [
                'service_name' => (string) $service->name,
                'service_no' => (string) $service->service_no,
            'date' => $service->expires_at?->format('Y-m-d H:i'),
                'console_url' => route('console.services.show', $service),
            ]);
        }
    }

    private function ensurePanelUser(PterodactylClient $client, Service $service): int
    {
        $user = $service->user;
        if (! $user) {
            throw new RuntimeException('实例缺少客户账号。');
        }

        $panelPassword = (string) ($user->pterodactyl_password ?: Str::password(20));
        $existing = $client->users((string) $user->email);
        $existingId = (int) data_get($existing, '0.attributes.id', 0);
        if ($existingId > 0) {
            try {
                $client->updateUser($existingId, ['password' => $panelPassword]);
            } catch (\Throwable) {
                // Existing panel user could not be updated; keep provisioning usable.
            }

            return $existingId;
        }

        $name = trim((string) $user->name) ?: 'Deviy Customer';
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $usernameBase = Str::lower(preg_replace('/[^A-Za-z0-9_.-]/', '', Str::before((string) $user->email, '@')) ?: 'user'.$user->id);
        $username = Str::limit($usernameBase, 24, '').$user->id;
        $created = $client->createUser([
            'email' => (string) $user->email,
            'username' => $username,
            'first_name' => $parts[0] ?: 'Deviy',
            'last_name' => $parts[1] ?? 'Customer',
            'password' => $panelPassword,
        ]);

        $id = (int) (data_get($created, 'attributes.id') ?? data_get($created, 'id') ?? 0);
        if ($id < 1) {
            throw new RuntimeException('创建翼龙面板用户失败。');
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function egg(PterodactylClient $client, int $nestId, int $eggId): array
    {
        $payload = $client->egg($nestId, $eggId);
        $egg = data_get($payload, 'attributes');
        if (! is_array($egg)) {
            throw new RuntimeException('读取翼龙 Egg 失败。');
        }

        $variables = [];
        foreach ((array) data_get($egg, 'relationships.variables.data', []) as $variable) {
            $env = (string) (data_get($variable, 'attributes.env_variable') ?? data_get($variable, 'attributes.env') ?? '');
            if ($env !== '') {
                $variables[$env] = (string) data_get($variable, 'attributes.default_value', '');
            }
        }
        $egg['variables'] = $variables;

        return $egg;
    }

    /** @param array<string, mixed> $egg @param array<string, mixed> $configured @return array<string, mixed> */
    private function environment(array $egg, array $configured): array
    {
        $environment = [];
        foreach ((array) ($egg['variables'] ?? []) as $key => $value) {
            $environment[$key] = $configured[$key] ?? $value;
        }
        foreach ($configured as $key => $value) {
            $environment[$key] = $value;
        }

        return $environment;
    }

    private function serverId(Service $service): string
    {
        $ref = (string) $service->external_ref;
        if (str_starts_with($ref, 'pterodactyl:')) {
            return substr($ref, 12);
        }

        return ctype_digit($ref) ? $ref : '';
    }

    private function locationId(PterodactylClient $client, int $nodeId): int
    {
        foreach ($client->nodes() as $node) {
            if ((int) data_get($node, 'attributes.id') === $nodeId) {
                $locationId = (int) data_get($node, 'attributes.location_id');
                if ($locationId > 0) {
                    return $locationId;
                }
            }
        }

        throw new RuntimeException('翼龙节点不存在或未绑定 Location。');
    }

    /** @return array<string, mixed> */
    private function findServer(PterodactylClient $client, Service $service, int $panelUser): array
    {
        $name = (string) ($service->name ?: $service->service_no);
        foreach ($client->servers() as $server) {
            if ((string) data_get($server, 'attributes.name') === $name
                && (int) data_get($server, 'attributes.user') === $panelUser) {
                return $server;
            }
        }

        return [];
    }
}
