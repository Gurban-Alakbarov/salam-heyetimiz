import 'package:freezed_annotation/freezed_annotation.dart';

part 'barrier_entities.freezed.dart';

/// Open-command lifecycle states (verbatim from the backend OpenCommandState).
enum CommandState {
  queued,
  dispatching,
  dispatched,
  opened,
  failed,
  expired,
  unknown;

  /// Polling stops on a terminal state.
  bool get isTerminal =>
      this == dispatched || this == opened || this == failed || this == expired;

  /// `opened` (actuation confirmed) or `dispatched` (sent, no feedback).
  bool get isSuccess => this == dispatched || this == opened;

  bool get isFailure => this == failed || this == expired;

  static CommandState parse(String? value) => switch (value) {
    'queued' => queued,
    'dispatching' => dispatching,
    'dispatched' => dispatched,
    'opened' => opened,
    'failed' => failed,
    'expired' => expired,
    _ => unknown,
  };
}

/// 202 acknowledgement of an open request — carries the command id to poll.
@freezed
abstract class OpenAck with _$OpenAck {
  const factory OpenAck({
    required int commandId,
    @Default(CommandState.queued) CommandState state,
    @Default(5000) int expectedCompletionMs,
    @Default(true) bool driverConfirmsActuation,
  }) = _OpenAck;
}

/// Polled command status.
@freezed
abstract class CommandStatus with _$CommandStatus {
  const factory CommandStatus({
    required int id,
    @Default(CommandState.unknown) CommandState state,
    String? failureReason,
    String? driver,
    int? latencyMs,
  }) = _CommandStatus;
}
