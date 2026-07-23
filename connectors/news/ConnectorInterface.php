<?php
declare(strict_types=1);

interface NewsConnectorInterface
{
    /** @return array<int,array<string,mixed>> */
    public function fetch(array $source): array;
}
