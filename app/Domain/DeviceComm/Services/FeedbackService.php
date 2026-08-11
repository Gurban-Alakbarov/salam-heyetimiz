<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\DeviceComm\Events\OpenFeedbackSubmitted;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Models\OpenCommandFeedback;
use App\Domain\Users\Models\User;

/**
 * User-reported actuation confirmation (CRIT-06). One feedback per (command, user); a replay returns
 * the value already on record rather than recording a second row.
 */
final class FeedbackService
{
    /**
     * @return array{feedback: OpenCommandFeedback, replayed: bool}
     */
    public function submit(OpenCommand $command, User $user, bool $gateMoved, ?string $comment): array
    {
        $existing = OpenCommandFeedback::query()
            ->where('open_command_id', $command->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($existing !== null) {
            return ['feedback' => $existing, 'replayed' => true];
        }

        $feedback = OpenCommandFeedback::query()->create([
            'open_command_id' => $command->getKey(),
            'user_id' => $user->getKey(),
            'gate_moved' => $gateMoved,
            'comment' => $comment,
        ]);

        OpenFeedbackSubmitted::dispatch((int) $command->getKey(), (int) $user->getKey(), $gateMoved);

        return ['feedback' => $feedback, 'replayed' => false];
    }
}
