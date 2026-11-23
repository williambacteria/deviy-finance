<?php

namespace App\Services\Provisioning;

/**
 * Provider capabilities used by the finance integration.
 *
 * Finance must not assume that every node is a KVM host. Proxmox, Hyper-V and
 * bare-metal nodes have different capabilities even though they are all exposed
 * through the same Controller.
 */
class ProviderProfile
{
    /** @var array<string, array<string, mixed>> */
    private const PROFILES = [
        'pve' => [
            'label' => 'Proxmox VE',
            'short' => 'PVE',
            'virtual_machine' => true,
            'template' => true,
            'ip_pool' => true,
            'snapshot' => true,
            'note' => '通过 Proxmox VE API 创建和管理虚拟机。',
        ],
        'hyperv' => [
            'label' => 'Hyper-V',
            'short' => 'Hyper-V',
            'virtual_machine' => true,
            'template' => true,
            'ip_pool' => true,
            'snapshot' => true,
            'note' => '通过 Hyper-V PowerShell Agent 创建和管理虚拟机。',
        ],
        'kvm' => [
            'label' => 'KVM / 裸机虚拟化',
            'short' => 'KVM',
            'virtual_machine' => true,
            'template' => true,
            'ip_pool' => true,
            'snapshot' => true,
            'note' => '通过 libvirt Agent 创建和管理 KVM 虚拟机。',
        ],
        'baremetal' => [
            'label' => '裸金属',
            'short' => 'Bare Metal',
            'virtual_machine' => false,
            'template' => false,
            'ip_pool' => false,
            'snapshot' => false,
            'note' => '裸金属节点在财务侧作为物理资源登记，不通过虚拟机接口创建。',
        ],
    ];

    /** @return array<string, mixed> */
    public function for(?string $provider): array
    {
        $key = strtolower(trim((string) $provider));

        return self::PROFILES[$key] ?? [
            'label' => $key !== '' ? strtoupper($key) : '未知平台',
            'short' => $key !== '' ? strtoupper($key) : '未知',
            'virtual_machine' => false,
            'template' => false,
            'ip_pool' => false,
            'snapshot' => false,
            'note' => '未知的节点类型，仅支持查看。',
        ];
    }

    /** @return array<int, string> */
    public function supportedProviders(): array
    {
        return array_keys(self::PROFILES);
    }
}
