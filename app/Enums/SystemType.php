<?php

namespace App\Enums;

/**
 * The Nethesis product a target machine runs. It decides which documentation
 * the agent searches and which tools it is given.
 */
enum SystemType: string
{
    case NethSecurity = 'nethsecurity';

    case NethServer = 'nethserver';
}
