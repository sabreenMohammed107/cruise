<?php

namespace App\Enums;

/** What an offer does to an agent's commission (App\Services\AgentPricing) when an agent books with it. */
enum OfferAgentMode: string
{
    /** The offer comes off the agent price: commission and offer together. */
    case Stack = 'stack';

    /** The commission is dropped: the offer comes off the public price instead. */
    case Replace = 'replace';
}
