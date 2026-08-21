<?php

use App\Domain\DeviceComm\Enums\OpenCommandState;

/*
| Open-command lifecycle classification (R-GSM-06).
*/

it('classifies terminal states', function (OpenCommandState $state, bool $terminal) {
    expect($state->isTerminal())->toBe($terminal);
})->with([
    'queued' => [OpenCommandState::Queued, false],
    'dispatching' => [OpenCommandState::Dispatching, false],
    'dispatched' => [OpenCommandState::Dispatched, true],
    'opened' => [OpenCommandState::Opened, true],
    'failed' => [OpenCommandState::Failed, true],
    'expired' => [OpenCommandState::Expired, true],
]);

it('classifies success states', function (OpenCommandState $state, bool $success) {
    expect($state->isSuccess())->toBe($success);
})->with([
    'dispatched' => [OpenCommandState::Dispatched, true],
    'opened' => [OpenCommandState::Opened, true],
    'failed' => [OpenCommandState::Failed, false],
    'expired' => [OpenCommandState::Expired, false],
    'queued' => [OpenCommandState::Queued, false],
]);
