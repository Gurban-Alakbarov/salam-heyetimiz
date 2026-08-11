// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'barrier_entities.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$OpenAck {

 int get commandId; CommandState get state; int get expectedCompletionMs; bool get driverConfirmsActuation;
/// Create a copy of OpenAck
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OpenAckCopyWith<OpenAck> get copyWith => _$OpenAckCopyWithImpl<OpenAck>(this as OpenAck, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OpenAck&&(identical(other.commandId, commandId) || other.commandId == commandId)&&(identical(other.state, state) || other.state == state)&&(identical(other.expectedCompletionMs, expectedCompletionMs) || other.expectedCompletionMs == expectedCompletionMs)&&(identical(other.driverConfirmsActuation, driverConfirmsActuation) || other.driverConfirmsActuation == driverConfirmsActuation));
}


@override
int get hashCode => Object.hash(runtimeType,commandId,state,expectedCompletionMs,driverConfirmsActuation);

@override
String toString() {
  return 'OpenAck(commandId: $commandId, state: $state, expectedCompletionMs: $expectedCompletionMs, driverConfirmsActuation: $driverConfirmsActuation)';
}


}

/// @nodoc
abstract mixin class $OpenAckCopyWith<$Res>  {
  factory $OpenAckCopyWith(OpenAck value, $Res Function(OpenAck) _then) = _$OpenAckCopyWithImpl;
@useResult
$Res call({
 int commandId, CommandState state, int expectedCompletionMs, bool driverConfirmsActuation
});




}
/// @nodoc
class _$OpenAckCopyWithImpl<$Res>
    implements $OpenAckCopyWith<$Res> {
  _$OpenAckCopyWithImpl(this._self, this._then);

  final OpenAck _self;
  final $Res Function(OpenAck) _then;

/// Create a copy of OpenAck
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? commandId = null,Object? state = null,Object? expectedCompletionMs = null,Object? driverConfirmsActuation = null,}) {
  return _then(_self.copyWith(
commandId: null == commandId ? _self.commandId : commandId // ignore: cast_nullable_to_non_nullable
as int,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as CommandState,expectedCompletionMs: null == expectedCompletionMs ? _self.expectedCompletionMs : expectedCompletionMs // ignore: cast_nullable_to_non_nullable
as int,driverConfirmsActuation: null == driverConfirmsActuation ? _self.driverConfirmsActuation : driverConfirmsActuation // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [OpenAck].
extension OpenAckPatterns on OpenAck {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OpenAck value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OpenAck() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OpenAck value)  $default,){
final _that = this;
switch (_that) {
case _OpenAck():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OpenAck value)?  $default,){
final _that = this;
switch (_that) {
case _OpenAck() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int commandId,  CommandState state,  int expectedCompletionMs,  bool driverConfirmsActuation)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OpenAck() when $default != null:
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int commandId,  CommandState state,  int expectedCompletionMs,  bool driverConfirmsActuation)  $default,) {final _that = this;
switch (_that) {
case _OpenAck():
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int commandId,  CommandState state,  int expectedCompletionMs,  bool driverConfirmsActuation)?  $default,) {final _that = this;
switch (_that) {
case _OpenAck() when $default != null:
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation);case _:
  return null;

}
}

}

/// @nodoc


class _OpenAck implements OpenAck {
  const _OpenAck({required this.commandId, this.state = CommandState.queued, this.expectedCompletionMs = 5000, this.driverConfirmsActuation = true});
  

@override final  int commandId;
@override@JsonKey() final  CommandState state;
@override@JsonKey() final  int expectedCompletionMs;
@override@JsonKey() final  bool driverConfirmsActuation;

/// Create a copy of OpenAck
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OpenAckCopyWith<_OpenAck> get copyWith => __$OpenAckCopyWithImpl<_OpenAck>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OpenAck&&(identical(other.commandId, commandId) || other.commandId == commandId)&&(identical(other.state, state) || other.state == state)&&(identical(other.expectedCompletionMs, expectedCompletionMs) || other.expectedCompletionMs == expectedCompletionMs)&&(identical(other.driverConfirmsActuation, driverConfirmsActuation) || other.driverConfirmsActuation == driverConfirmsActuation));
}


@override
int get hashCode => Object.hash(runtimeType,commandId,state,expectedCompletionMs,driverConfirmsActuation);

@override
String toString() {
  return 'OpenAck(commandId: $commandId, state: $state, expectedCompletionMs: $expectedCompletionMs, driverConfirmsActuation: $driverConfirmsActuation)';
}


}

/// @nodoc
abstract mixin class _$OpenAckCopyWith<$Res> implements $OpenAckCopyWith<$Res> {
  factory _$OpenAckCopyWith(_OpenAck value, $Res Function(_OpenAck) _then) = __$OpenAckCopyWithImpl;
@override @useResult
$Res call({
 int commandId, CommandState state, int expectedCompletionMs, bool driverConfirmsActuation
});




}
/// @nodoc
class __$OpenAckCopyWithImpl<$Res>
    implements _$OpenAckCopyWith<$Res> {
  __$OpenAckCopyWithImpl(this._self, this._then);

  final _OpenAck _self;
  final $Res Function(_OpenAck) _then;

/// Create a copy of OpenAck
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? commandId = null,Object? state = null,Object? expectedCompletionMs = null,Object? driverConfirmsActuation = null,}) {
  return _then(_OpenAck(
commandId: null == commandId ? _self.commandId : commandId // ignore: cast_nullable_to_non_nullable
as int,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as CommandState,expectedCompletionMs: null == expectedCompletionMs ? _self.expectedCompletionMs : expectedCompletionMs // ignore: cast_nullable_to_non_nullable
as int,driverConfirmsActuation: null == driverConfirmsActuation ? _self.driverConfirmsActuation : driverConfirmsActuation // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

/// @nodoc
mixin _$CommandStatus {

 int get id; CommandState get state; String? get failureReason; String? get driver; int? get latencyMs;
/// Create a copy of CommandStatus
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CommandStatusCopyWith<CommandStatus> get copyWith => _$CommandStatusCopyWithImpl<CommandStatus>(this as CommandStatus, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CommandStatus&&(identical(other.id, id) || other.id == id)&&(identical(other.state, state) || other.state == state)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.driver, driver) || other.driver == driver)&&(identical(other.latencyMs, latencyMs) || other.latencyMs == latencyMs));
}


@override
int get hashCode => Object.hash(runtimeType,id,state,failureReason,driver,latencyMs);

@override
String toString() {
  return 'CommandStatus(id: $id, state: $state, failureReason: $failureReason, driver: $driver, latencyMs: $latencyMs)';
}


}

/// @nodoc
abstract mixin class $CommandStatusCopyWith<$Res>  {
  factory $CommandStatusCopyWith(CommandStatus value, $Res Function(CommandStatus) _then) = _$CommandStatusCopyWithImpl;
@useResult
$Res call({
 int id, CommandState state, String? failureReason, String? driver, int? latencyMs
});




}
/// @nodoc
class _$CommandStatusCopyWithImpl<$Res>
    implements $CommandStatusCopyWith<$Res> {
  _$CommandStatusCopyWithImpl(this._self, this._then);

  final CommandStatus _self;
  final $Res Function(CommandStatus) _then;

/// Create a copy of CommandStatus
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? state = null,Object? failureReason = freezed,Object? driver = freezed,Object? latencyMs = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as CommandState,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,driver: freezed == driver ? _self.driver : driver // ignore: cast_nullable_to_non_nullable
as String?,latencyMs: freezed == latencyMs ? _self.latencyMs : latencyMs // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}

}


/// Adds pattern-matching-related methods to [CommandStatus].
extension CommandStatusPatterns on CommandStatus {
/// A variant of `map` that fallback to returning `orElse`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CommandStatus value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CommandStatus() when $default != null:
return $default(_that);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// Callbacks receives the raw object, upcasted.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case final Subclass2 value:
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CommandStatus value)  $default,){
final _that = this;
switch (_that) {
case _CommandStatus():
return $default(_that);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `map` that fallback to returning `null`.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case final Subclass value:
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CommandStatus value)?  $default,){
final _that = this;
switch (_that) {
case _CommandStatus() when $default != null:
return $default(_that);case _:
  return null;

}
}
/// A variant of `when` that fallback to an `orElse` callback.
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return orElse();
/// }
/// ```

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  CommandState state,  String? failureReason,  String? driver,  int? latencyMs)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CommandStatus() when $default != null:
return $default(_that.id,_that.state,_that.failureReason,_that.driver,_that.latencyMs);case _:
  return orElse();

}
}
/// A `switch`-like method, using callbacks.
///
/// As opposed to `map`, this offers destructuring.
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case Subclass2(:final field2):
///     return ...;
/// }
/// ```

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  CommandState state,  String? failureReason,  String? driver,  int? latencyMs)  $default,) {final _that = this;
switch (_that) {
case _CommandStatus():
return $default(_that.id,_that.state,_that.failureReason,_that.driver,_that.latencyMs);case _:
  throw StateError('Unexpected subclass');

}
}
/// A variant of `when` that fallback to returning `null`
///
/// It is equivalent to doing:
/// ```dart
/// switch (sealedClass) {
///   case Subclass(:final field):
///     return ...;
///   case _:
///     return null;
/// }
/// ```

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  CommandState state,  String? failureReason,  String? driver,  int? latencyMs)?  $default,) {final _that = this;
switch (_that) {
case _CommandStatus() when $default != null:
return $default(_that.id,_that.state,_that.failureReason,_that.driver,_that.latencyMs);case _:
  return null;

}
}

}

/// @nodoc


class _CommandStatus implements CommandStatus {
  const _CommandStatus({required this.id, this.state = CommandState.unknown, this.failureReason, this.driver, this.latencyMs});
  

@override final  int id;
@override@JsonKey() final  CommandState state;
@override final  String? failureReason;
@override final  String? driver;
@override final  int? latencyMs;

/// Create a copy of CommandStatus
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CommandStatusCopyWith<_CommandStatus> get copyWith => __$CommandStatusCopyWithImpl<_CommandStatus>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CommandStatus&&(identical(other.id, id) || other.id == id)&&(identical(other.state, state) || other.state == state)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.driver, driver) || other.driver == driver)&&(identical(other.latencyMs, latencyMs) || other.latencyMs == latencyMs));
}


@override
int get hashCode => Object.hash(runtimeType,id,state,failureReason,driver,latencyMs);

@override
String toString() {
  return 'CommandStatus(id: $id, state: $state, failureReason: $failureReason, driver: $driver, latencyMs: $latencyMs)';
}


}

/// @nodoc
abstract mixin class _$CommandStatusCopyWith<$Res> implements $CommandStatusCopyWith<$Res> {
  factory _$CommandStatusCopyWith(_CommandStatus value, $Res Function(_CommandStatus) _then) = __$CommandStatusCopyWithImpl;
@override @useResult
$Res call({
 int id, CommandState state, String? failureReason, String? driver, int? latencyMs
});




}
/// @nodoc
class __$CommandStatusCopyWithImpl<$Res>
    implements _$CommandStatusCopyWith<$Res> {
  __$CommandStatusCopyWithImpl(this._self, this._then);

  final _CommandStatus _self;
  final $Res Function(_CommandStatus) _then;

/// Create a copy of CommandStatus
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? state = null,Object? failureReason = freezed,Object? driver = freezed,Object? latencyMs = freezed,}) {
  return _then(_CommandStatus(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,state: null == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as CommandState,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,driver: freezed == driver ? _self.driver : driver // ignore: cast_nullable_to_non_nullable
as String?,latencyMs: freezed == latencyMs ? _self.latencyMs : latencyMs // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}


}

// dart format on
