<?php

function getBranchByIp(string $ip): ?string
{
    $map = [
        'SHOWROOM' => [
            '192.168.40.0/24',
        ]
    ];

    foreach ($map as $branch => $ranges) {
        foreach ($ranges as $cidr) {
            if (ip_in_cidr($ip, $cidr)) {
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