<?php

namespace App\Enums;

/**
 * Communication state of a headend or gateway.
 */
enum DeviceStatus: string
{
    case Online = 'online';
    case Degraded = 'degraded';
    case Offline = 'offline';
}
