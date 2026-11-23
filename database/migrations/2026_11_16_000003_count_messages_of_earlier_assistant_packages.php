<?php

use App\Models\AssistantPackage;
use Illuminate\Database\Migrations\Migration;

/**
 * Packages created before `assistant_packages.messages` was counted show 0 messages: count them from the
 * conversations' transcripts (AssistantPackage::countMessagesFromConversations()). Only packages still at 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        AssistantPackage::where('messages', 0)->each(function (AssistantPackage $package) {
            $package->forceFill(['messages' => $package->countMessagesFromConversations()])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Nothing to undo: the counts are right either way
    }
};
