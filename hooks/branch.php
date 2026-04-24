<?php

function getBranchByIp(string $ip): ?string
{
    static $map = [
        'HEAD OFFICE' => [
            '192.168.40.0/24',
            '::1' // localhost IPv6
        ]
    ];

    foreach ($map as $branch => $ranges) {
        foreach ($ranges as $cidr) {
            // Simple IPv6 direct match
            if ($ip === $cidr) {
                return $branch;
            }
            // IPv4 CIDR check
            if (strpos($cidr, '/') !== false && ip_in_cidr($ip, $cidr)) {
                return $branch;
            }
        }
    }

    return null; // unknown / outside WAN
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    [$subnet, $mask] = explode('/', $cidr);

    $ipLong     = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong   = -1 << (32 - (int)$mask);

    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

?>