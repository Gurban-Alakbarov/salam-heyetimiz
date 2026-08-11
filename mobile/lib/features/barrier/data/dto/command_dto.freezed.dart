// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'command_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$OpenAckDto {

 int get commandId; String? get state; int? get expectedCompletionMs; bool? get driverConfirmsActuation; String? get websocketChannel;
/// Create a copy of OpenAckDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OpenAckDtoCopyWith<OpenAckDto> get copyWith => _$OpenAckDtoCopyWithImpl<OpenAckDto>(this as OpenAckDto, _$identity);

  /// Serializes this OpenAckDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OpenAckDto&&(identical(other.commandId, commandId) || other.commandId == commandId)&&(identical(other.state, state) || other.state == state)&&(identical(other.expectedCompletionMs, expectedCompletionMs) || other.expectedCompletionMs == expectedCompletionMs)&&(identical(other.driverConfirmsActuation, driverConfirmsActuation) || other.driverConfirmsActuation == driverConfirmsActuation)&&(identical(other.websocketChannel, websocketChannel) || other.websocketChannel == websocketChannel));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,commandId,state,expectedCompletionMs,driverConfirmsActuation,websocketChannel);

@override
String toString() {
  return 'OpenAckDto(commandId: $commandId, state: $state, expectedCompletionMs: $expectedCompletionMs, driverConfirmsActuation: $driverConfirmsActuation, websocketChannel: $websocketChannel)';
}


}

/// @nodoc
abstract mixin class $OpenAckDtoCopyWith<$Res>  {
  factory $OpenAckDtoCopyWith(OpenAckDto value, $Res Function(OpenAckDto) _then) = _$OpenAckDtoCopyWithImpl;
@useResult
$Res call({
 int commandId, String? state, int? expectedCompletionMs, bool? driverConfirmsActuation, String? websocketChannel
});




}
/// @nodoc
class _$OpenAckDtoCopyWithImpl<$Res>
    implements $OpenAckDtoCopyWith<$Res> {
  _$OpenAckDtoCopyWithImpl(this._self, this._then);

  final OpenAckDto _self;
  final $Res Function(OpenAckDto) _then;

/// Create a copy of OpenAckDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? commandId = null,Object? state = freezed,Object? expectedCompletionMs = freezed,Object? driverConfirmsActuation = freezed,Object? websocketChannel = freezed,}) {
  return _then(_self.copyWith(
commandId: null == commandId ? _self.commandId : commandId // ignore: cast_nullable_to_non_nullable
as int,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,expectedCompletionMs: freezed == expectedCompletionMs ? _self.expectedCompletionMs : expectedCompletionMs // ignore: cast_nullable_to_non_nullable
as int?,driverConfirmsActuation: freezed == driverConfirmsActuation ? _self.driverConfirmsActuation : driverConfirmsActuation // ignore: cast_nullable_to_non_nullable
as bool?,websocketChannel: freezed == websocketChannel ? _self.websocketChannel : websocketChannel // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [OpenAckDto].
extension OpenAckDtoPatterns on OpenAckDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OpenAckDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OpenAckDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OpenAckDto value)  $default,){
final _that = this;
switch (_that) {
case _OpenAckDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OpenAckDto value)?  $default,){
final _that = this;
switch (_that) {
case _OpenAckDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int commandId,  String? state,  int? expectedCompletionMs,  bool? driverConfirmsActuation,  String? websocketChannel)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OpenAckDto() when $default != null:
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation,_that.websocketChannel);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int commandId,  String? state,  int? expectedCompletionMs,  bool? driverConfirmsActuation,  String? websocketChannel)  $default,) {final _that = this;
switch (_that) {
case _OpenAckDto():
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation,_that.websocketChannel);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int commandId,  String? state,  int? expectedCompletionMs,  bool? driverConfirmsActuation,  String? websocketChannel)?  $default,) {final _that = this;
switch (_that) {
case _OpenAckDto() when $default != null:
return $default(_that.commandId,_that.state,_that.expectedCompletionMs,_that.driverConfirmsActuation,_that.websocketChannel);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _OpenAckDto implements OpenAckDto {
  const _OpenAckDto({required this.commandId, this.state, this.expectedCompletionMs, this.driverConfirmsActuation, this.websocketChannel});
  factory _OpenAckDto.fromJson(Map<String, dynamic> json) => _$OpenAckDtoFromJson(json);

@override final  int commandId;
@override final  String? state;
@override final  int? expectedCompletionMs;
@override final  bool? driverConfirmsActuation;
@override final  String? websocketChannel;

/// Create a copy of OpenAckDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OpenAckDtoCopyWith<_OpenAckDto> get copyWith => __$OpenAckDtoCopyWithImpl<_OpenAckDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OpenAckDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OpenAckDto&&(identical(other.commandId, commandId) || other.commandId == commandId)&&(identical(other.state, state) || other.state == state)&&(identical(other.expectedCompletionMs, expectedCompletionMs) || other.expectedCompletionMs == expectedCompletionMs)&&(identical(other.driverConfirmsActuation, driverConfirmsActuation) || other.driverConfirmsActuation == driverConfirmsActuation)&&(identical(other.websocketChannel, websocketChannel) || other.websocketChannel == websocketChannel));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,commandId,state,expectedCompletionMs,driverConfirmsActuation,websocketChannel);

@override
String toString() {
  return 'OpenAckDto(commandId: $commandId, state: $state, expectedCompletionMs: $expectedCompletionMs, driverConfirmsActuation: $driverConfirmsActuation, websocketChannel: $websocketChannel)';
}


}

/// @nodoc
abstract mixin class _$OpenAckDtoCopyWith<$Res> implements $OpenAckDtoCopyWith<$Res> {
  factory _$OpenAckDtoCopyWith(_OpenAckDto value, $Res Function(_OpenAckDto) _then) = __$OpenAckDtoCopyWithImpl;
@override @useResult
$Res call({
 int commandId, String? state, int? expectedCompletionMs, bool? driverConfirmsActuation, String? websocketChannel
});




}
/// @nodoc
class __$OpenAckDtoCopyWithImpl<$Res>
    implements _$OpenAckDtoCopyWith<$Res> {
  __$OpenAckDtoCopyWithImpl(this._self, this._then);

  final _OpenAckDto _self;
  final $Res Function(_OpenAckDto) _then;

/// Create a copy of OpenAckDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? commandId = null,Object? state = freezed,Object? expectedCompletionMs = freezed,Object? driverConfirmsActuation = freezed,Object? websocketChannel = freezed,}) {
  return _then(_OpenAckDto(
commandId: null == commandId ? _self.commandId : commandId // ignore: cast_nullable_to_non_nullable
as int,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,expectedCompletionMs: freezed == expectedCompletionMs ? _self.expectedCompletionMs : expectedCompletionMs // ignore: cast_nullable_to_non_nullable
as int?,driverConfirmsActuation: freezed == driverConfirmsActuation ? _self.driverConfirmsActuation : driverConfirmsActuation // ignore: cast_nullable_to_non_nullable
as bool?,websocketChannel: freezed == websocketChannel ? _self.websocketChannel : websocketChannel // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$CommandStatusDto {

 int get id; int? get deviceId; String? get state; String? get failureReason; String? get driver; String? get requestedAt; String? get dispatchedAt; String? get completedAt; int? get latencyMs; int? get attempts;
/// Create a copy of CommandStatusDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CommandStatusDtoCopyWith<CommandStatusDto> get copyWith => _$CommandStatusDtoCopyWithImpl<CommandStatusDto>(this as CommandStatusDto, _$identity);

  /// Serializes this CommandStatusDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CommandStatusDto&&(identical(other.id, id) || other.id == id)&&(identical(other.deviceId, deviceId) || other.deviceId == deviceId)&&(identical(other.state, state) || other.state == state)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.driver, driver) || other.driver == driver)&&(identical(other.requestedAt, requestedAt) || other.requestedAt == requestedAt)&&(identical(other.dispatchedAt, dispatchedAt) || other.dispatchedAt == dispatchedAt)&&(identical(other.completedAt, completedAt) || other.completedAt == completedAt)&&(identical(other.latencyMs, latencyMs) || other.latencyMs == latencyMs)&&(identical(other.attempts, attempts) || other.attempts == attempts));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,deviceId,state,failureReason,driver,requestedAt,dispatchedAt,completedAt,latencyMs,attempts);

@override
String toString() {
  return 'CommandStatusDto(id: $id, deviceId: $deviceId, state: $state, failureReason: $failureReason, driver: $driver, requestedAt: $requestedAt, dispatchedAt: $dispatchedAt, completedAt: $completedAt, latencyMs: $latencyMs, attempts: $attempts)';
}


}

/// @nodoc
abstract mixin class $CommandStatusDtoCopyWith<$Res>  {
  factory $CommandStatusDtoCopyWith(CommandStatusDto value, $Res Function(CommandStatusDto) _then) = _$CommandStatusDtoCopyWithImpl;
@useResult
$Res call({
 int id, int? deviceId, String? state, String? failureReason, String? driver, String? requestedAt, String? dispatchedAt, String? completedAt, int? latencyMs, int? attempts
});




}
/// @nodoc
class _$CommandStatusDtoCopyWithImpl<$Res>
    implements $CommandStatusDtoCopyWith<$Res> {
  _$CommandStatusDtoCopyWithImpl(this._self, this._then);

  final CommandStatusDto _self;
  final $Res Function(CommandStatusDto) _then;

/// Create a copy of CommandStatusDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? deviceId = freezed,Object? state = freezed,Object? failureReason = freezed,Object? driver = freezed,Object? requestedAt = freezed,Object? dispatchedAt = freezed,Object? completedAt = freezed,Object? latencyMs = freezed,Object? attempts = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,deviceId: freezed == deviceId ? _self.deviceId : deviceId // ignore: cast_nullable_to_non_nullable
as int?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,driver: freezed == driver ? _self.driver : driver // ignore: cast_nullable_to_non_nullable
as String?,requestedAt: freezed == requestedAt ? _self.requestedAt : requestedAt // ignore: cast_nullable_to_non_nullable
as String?,dispatchedAt: freezed == dispatchedAt ? _self.dispatchedAt : dispatchedAt // ignore: cast_nullable_to_non_nullable
as String?,completedAt: freezed == completedAt ? _self.completedAt : completedAt // ignore: cast_nullable_to_non_nullable
as String?,latencyMs: freezed == latencyMs ? _self.latencyMs : latencyMs // ignore: cast_nullable_to_non_nullable
as int?,attempts: freezed == attempts ? _self.attempts : attempts // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}

}


/// Adds pattern-matching-related methods to [CommandStatusDto].
extension CommandStatusDtoPatterns on CommandStatusDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CommandStatusDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CommandStatusDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CommandStatusDto value)  $default,){
final _that = this;
switch (_that) {
case _CommandStatusDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CommandStatusDto value)?  $default,){
final _that = this;
switch (_that) {
case _CommandStatusDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  int? deviceId,  String? state,  String? failureReason,  String? driver,  String? requestedAt,  String? dispatchedAt,  String? completedAt,  int? latencyMs,  int? attempts)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CommandStatusDto() when $default != null:
return $default(_that.id,_that.deviceId,_that.state,_that.failureReason,_that.driver,_that.requestedAt,_that.dispatchedAt,_that.completedAt,_that.latencyMs,_that.attempts);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  int? deviceId,  String? state,  String? failureReason,  String? driver,  String? requestedAt,  String? dispatchedAt,  String? completedAt,  int? latencyMs,  int? attempts)  $default,) {final _that = this;
switch (_that) {
case _CommandStatusDto():
return $default(_that.id,_that.deviceId,_that.state,_that.failureReason,_that.driver,_that.requestedAt,_that.dispatchedAt,_that.completedAt,_that.latencyMs,_that.attempts);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  int? deviceId,  String? state,  String? failureReason,  String? driver,  String? requestedAt,  String? dispatchedAt,  String? completedAt,  int? latencyMs,  int? attempts)?  $default,) {final _that = this;
switch (_that) {
case _CommandStatusDto() when $default != null:
return $default(_that.id,_that.deviceId,_that.state,_that.failureReason,_that.driver,_that.requestedAt,_that.dispatchedAt,_that.completedAt,_that.latencyMs,_that.attempts);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CommandStatusDto implements CommandStatusDto {
  const _CommandStatusDto({required this.id, this.deviceId, this.state, this.failureReason, this.driver, this.requestedAt, this.dispatchedAt, this.completedAt, this.latencyMs, this.attempts});
  factory _CommandStatusDto.fromJson(Map<String, dynamic> json) => _$CommandStatusDtoFromJson(json);

@override final  int id;
@override final  int? deviceId;
@override final  String? state;
@override final  String? failureReason;
@override final  String? driver;
@override final  String? requestedAt;
@override final  String? dispatchedAt;
@override final  String? completedAt;
@override final  int? latencyMs;
@override final  int? attempts;

/// Create a copy of CommandStatusDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CommandStatusDtoCopyWith<_CommandStatusDto> get copyWith => __$CommandStatusDtoCopyWithImpl<_CommandStatusDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CommandStatusDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CommandStatusDto&&(identical(other.id, id) || other.id == id)&&(identical(other.deviceId, deviceId) || other.deviceId == deviceId)&&(identical(other.state, state) || other.state == state)&&(identical(other.failureReason, failureReason) || other.failureReason == failureReason)&&(identical(other.driver, driver) || other.driver == driver)&&(identical(other.requestedAt, requestedAt) || other.requestedAt == requestedAt)&&(identical(other.dispatchedAt, dispatchedAt) || other.dispatchedAt == dispatchedAt)&&(identical(other.completedAt, completedAt) || other.completedAt == completedAt)&&(identical(other.latencyMs, latencyMs) || other.latencyMs == latencyMs)&&(identical(other.attempts, attempts) || other.attempts == attempts));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,deviceId,state,failureReason,driver,requestedAt,dispatchedAt,completedAt,latencyMs,attempts);

@override
String toString() {
  return 'CommandStatusDto(id: $id, deviceId: $deviceId, state: $state, failureReason: $failureReason, driver: $driver, requestedAt: $requestedAt, dispatchedAt: $dispatchedAt, completedAt: $completedAt, latencyMs: $latencyMs, attempts: $attempts)';
}


}

/// @nodoc
abstract mixin class _$CommandStatusDtoCopyWith<$Res> implements $CommandStatusDtoCopyWith<$Res> {
  factory _$CommandStatusDtoCopyWith(_CommandStatusDto value, $Res Function(_CommandStatusDto) _then) = __$CommandStatusDtoCopyWithImpl;
@override @useResult
$Res call({
 int id, int? deviceId, String? state, String? failureReason, String? driver, String? requestedAt, String? dispatchedAt, String? completedAt, int? latencyMs, int? attempts
});




}
/// @nodoc
class __$CommandStatusDtoCopyWithImpl<$Res>
    implements _$CommandStatusDtoCopyWith<$Res> {
  __$CommandStatusDtoCopyWithImpl(this._self, this._then);

  final _CommandStatusDto _self;
  final $Res Function(_CommandStatusDto) _then;

/// Create a copy of CommandStatusDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? deviceId = freezed,Object? state = freezed,Object? failureReason = freezed,Object? driver = freezed,Object? requestedAt = freezed,Object? dispatchedAt = freezed,Object? completedAt = freezed,Object? latencyMs = freezed,Object? attempts = freezed,}) {
  return _then(_CommandStatusDto(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,deviceId: freezed == deviceId ? _self.deviceId : deviceId // ignore: cast_nullable_to_non_nullable
as int?,state: freezed == state ? _self.state : state // ignore: cast_nullable_to_non_nullable
as String?,failureReason: freezed == failureReason ? _self.failureReason : failureReason // ignore: cast_nullable_to_non_nullable
as String?,driver: freezed == driver ? _self.driver : driver // ignore: cast_nullable_to_non_nullable
as String?,requestedAt: freezed == requestedAt ? _self.requestedAt : requestedAt // ignore: cast_nullable_to_non_nullable
as String?,dispatchedAt: freezed == dispatchedAt ? _self.dispatchedAt : dispatchedAt // ignore: cast_nullable_to_non_nullable
as String?,completedAt: freezed == completedAt ? _self.completedAt : completedAt // ignore: cast_nullable_to_non_nullable
as String?,latencyMs: freezed == latencyMs ? _self.latencyMs : latencyMs // ignore: cast_nullable_to_non_nullable
as int?,attempts: freezed == attempts ? _self.attempts : attempts // ignore: cast_nullable_to_non_nullable
as int?,
  ));
}


}

// dart format on
