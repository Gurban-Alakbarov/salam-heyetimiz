<?php

namespace App\Domain\DeviceComm\Actions;

use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Models\OpenCommandFeedback;
use App\Domain\DeviceComm\Services\FeedbackService;
use App\Domain\Users\Models\User;

/** Record user-reported actuation feedback for a command (submitOpenFeedback, CRIT-06). */
final class SubmitOpenFeedback
{
    public function __construct(private readonly FeedbackService $feedback) {}

    /**
     * @return array{feedback: OpenCommandFeedback, replayed: bool}
     */
    public function handle(OpenCommand $command, User $user, bool $gateMoved, ?string $comment): array
    {
        return $this->feedback->submit($command, $user, $gateMoved, $comment);
    }
}
