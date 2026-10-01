<?php

namespace App\Enums;

enum MeterStatus: string
{
    case Active = 'active';
    case NoComms = 'no_comms';
    case Fault = 'fault';
    case Decommissioned = 'decommissioned';
}
