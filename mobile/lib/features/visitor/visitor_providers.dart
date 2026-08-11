import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/di/providers.dart';
import '../../core/error/failure.dart';
import 'data/datasource/visitor_remote_datasource.dart';
import 'data/repository_impl.dart';
import 'domain/entity/visitor_entities.dart';
import 'domain/repository.dart';

final visitorRepositoryProvider = Provider<VisitorRepository>(
  (ref) => VisitorRepositoryImpl(
    VisitorRemoteDataSource(ref.watch(apiClientProvider)),
  ),
);

/// Invite-Visitor flow state (idle → creating → created | failed). Auto-disposed
/// with the bottom sheet; a single in-flight guard blocks double submits.
sealed class VisitorCreateState {
  const VisitorCreateState();
}

class VisitorCreateIdle extends VisitorCreateState {
  const VisitorCreateIdle();
}

class VisitorCreating extends VisitorCreateState {
  const VisitorCreating();
}

class VisitorCreated extends VisitorCreateState {
  const VisitorCreated(this.link);
  final CreatedVisitorLink link;
}

class VisitorCreateFailed extends VisitorCreateState {
  const VisitorCreateFailed(this.failure);
  final Failure failure;
}

class VisitorCreateNotifier extends Notifier<VisitorCreateState> {
  bool _inFlight = false;

  @override
  VisitorCreateState build() => const VisitorCreateIdle();

  Future<void> create(
    int deviceId, {
    required VisitorAccessType accessType,
    int? durationMinutes,
    String? visitorName,
    VisitorPurpose? purpose,
  }) async {
    if (_inFlight) return;
    _inFlight = true;
    state = const VisitorCreating();

    final result = await ref
        .read(visitorRepositoryProvider)
        .create(
          deviceId,
          accessType: accessType,
          durationMinutes: durationMinutes,
          visitorName: visitorName,
          purpose: purpose,
        );

    _inFlight = false;
    state = result.fold(
      (failure) => VisitorCreateFailed(failure),
      (link) => VisitorCreated(link),
    );
  }

  /// Return to the form (e.g. to fix a validation error and retry).
  void reset() {
    _inFlight = false;
    state = const VisitorCreateIdle();
  }
}

final visitorCreateProvider =
    NotifierProvider.autoDispose<VisitorCreateNotifier, VisitorCreateState>(
      VisitorCreateNotifier.new,
    );
