<?php

declare(strict_types=1);

namespace Babod\NetManager\Zyxel;

interface ZyxelClientInterface
{
    public function getApiType(): string;

    /** @return array<string, mixed> */
    public function testConnection(): array;

    /** @return array<string, mixed> */
    public function collectSnapshot(): array;

    /** @param array<string, mixed> $vlanConfig */
    public function saveVlanConfig(array $vlanConfig): void;
}
