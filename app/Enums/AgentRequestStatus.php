<?php

namespace App\Enums;

/** Where an agent application stands (Admin → Agents → Agent requests). New applications are pending. */
enum AgentRequestStatus: string
{
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
