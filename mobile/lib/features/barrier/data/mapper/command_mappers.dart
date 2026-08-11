import '../../domain/entity/barrier_entities.dart';
import '../dto/command_dto.dart';

OpenAck openAckDtoToEntity(OpenAckDto d) => OpenAck(
  commandId: d.commandId,
  state: CommandState.parse(d.state),
  expectedCompletionMs: d.expectedCompletionMs ?? 5000,
  driverConfirmsActuation: d.driverConfirmsActuation ?? true,
);

CommandStatus commandStatusDtoToEntity(CommandStatusDto d) => CommandStatus(
  id: d.id,
  state: CommandState.parse(d.state),
  failureReason: d.failureReason,
  driver: d.driver,
  latencyMs: d.latencyMs,
);
