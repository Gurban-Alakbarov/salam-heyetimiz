// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'config_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$AppConfigDto {

 bool get maintenanceMode; String? get minVersion; String? get latestVersion; bool get forceUpdate;
/// Create a copy of AppConfigDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AppConfigDtoCopyWith<AppConfigDto> get copyWith => _$AppConfigDtoCopyWithImpl<AppConfigDto>(this as AppConfigDto, _$identity);

  /// Serializes this AppConfigDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AppConfigDto&&(identical(other.maintenanceMode, maintenanceMode) || other.maintenanceMode == maintenanceMode)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.latestVersion, latestVersion) || other.latestVersion == latestVersion)&&(identical(other.forceUpdate, forceUpdate) || other.forceUpdate == forceUpdate));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,maintenanceMode,minVersion,latestVersion,forceUpdate);

@override
String toString() {
  return 'AppConfigDto(maintenanceMode: $maintenanceMode, minVersion: $minVersion, latestVersion: $latestVersion, forceUpdate: $forceUpdate)';
}


}

/// @nodoc
abstract mixin class $AppConfigDtoCopyWith<$Res>  {
  factory $AppConfigDtoCopyWith(AppConfigDto value, $Res Function(AppConfigDto) _then) = _$AppConfigDtoCopyWithImpl;
@useResult
$Res call({
 bool maintenanceMode, String? minVersion, String? latestVersion, bool forceUpdate
});




}
/// @nodoc
class _$AppConfigDtoCopyWithImpl<$Res>
    implements $AppConfigDtoCopyWith<$Res> {
  _$AppConfigDtoCopyWithImpl(this._self, this._then);

  final AppConfigDto _self;
  final $Res Function(AppConfigDto) _then;

/// Create a copy of AppConfigDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? maintenanceMode = null,Object? minVersion = freezed,Object? latestVersion = freezed,Object? forceUpdate = null,}) {
  return _then(_self.copyWith(
maintenanceMode: null == maintenanceMode ? _self.maintenanceMode : maintenanceMode // ignore: cast_nullable_to_non_nullable
as bool,minVersion: freezed == minVersion ? _self.minVersion : minVersion // ignore: cast_nullable_to_non_nullable
as String?,latestVersion: freezed == latestVersion ? _self.latestVersion : latestVersion // ignore: cast_nullable_to_non_nullable
as String?,forceUpdate: null == forceUpdate ? _self.forceUpdate : forceUpdate // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [AppConfigDto].
extension AppConfigDtoPatterns on AppConfigDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AppConfigDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AppConfigDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AppConfigDto value)  $default,){
final _that = this;
switch (_that) {
case _AppConfigDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AppConfigDto value)?  $default,){
final _that = this;
switch (_that) {
case _AppConfigDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( bool maintenanceMode,  String? minVersion,  String? latestVersion,  bool forceUpdate)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _AppConfigDto() when $default != null:
return $default(_that.maintenanceMode,_that.minVersion,_that.latestVersion,_that.forceUpdate);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( bool maintenanceMode,  String? minVersion,  String? latestVersion,  bool forceUpdate)  $default,) {final _that = this;
switch (_that) {
case _AppConfigDto():
return $default(_that.maintenanceMode,_that.minVersion,_that.latestVersion,_that.forceUpdate);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( bool maintenanceMode,  String? minVersion,  String? latestVersion,  bool forceUpdate)?  $default,) {final _that = this;
switch (_that) {
case _AppConfigDto() when $default != null:
return $default(_that.maintenanceMode,_that.minVersion,_that.latestVersion,_that.forceUpdate);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _AppConfigDto implements AppConfigDto {
  const _AppConfigDto({this.maintenanceMode = false, this.minVersion, this.latestVersion, this.forceUpdate = false});
  factory _AppConfigDto.fromJson(Map<String, dynamic> json) => _$AppConfigDtoFromJson(json);

@override@JsonKey() final  bool maintenanceMode;
@override final  String? minVersion;
@override final  String? latestVersion;
@override@JsonKey() final  bool forceUpdate;

/// Create a copy of AppConfigDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AppConfigDtoCopyWith<_AppConfigDto> get copyWith => __$AppConfigDtoCopyWithImpl<_AppConfigDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$AppConfigDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AppConfigDto&&(identical(other.maintenanceMode, maintenanceMode) || other.maintenanceMode == maintenanceMode)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.latestVersion, latestVersion) || other.latestVersion == latestVersion)&&(identical(other.forceUpdate, forceUpdate) || other.forceUpdate == forceUpdate));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,maintenanceMode,minVersion,latestVersion,forceUpdate);

@override
String toString() {
  return 'AppConfigDto(maintenanceMode: $maintenanceMode, minVersion: $minVersion, latestVersion: $latestVersion, forceUpdate: $forceUpdate)';
}


}

/// @nodoc
abstract mixin class _$AppConfigDtoCopyWith<$Res> implements $AppConfigDtoCopyWith<$Res> {
  factory _$AppConfigDtoCopyWith(_AppConfigDto value, $Res Function(_AppConfigDto) _then) = __$AppConfigDtoCopyWithImpl;
@override @useResult
$Res call({
 bool maintenanceMode, String? minVersion, String? latestVersion, bool forceUpdate
});




}
/// @nodoc
class __$AppConfigDtoCopyWithImpl<$Res>
    implements _$AppConfigDtoCopyWith<$Res> {
  __$AppConfigDtoCopyWithImpl(this._self, this._then);

  final _AppConfigDto _self;
  final $Res Function(_AppConfigDto) _then;

/// Create a copy of AppConfigDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? maintenanceMode = null,Object? minVersion = freezed,Object? latestVersion = freezed,Object? forceUpdate = null,}) {
  return _then(_AppConfigDto(
maintenanceMode: null == maintenanceMode ? _self.maintenanceMode : maintenanceMode // ignore: cast_nullable_to_non_nullable
as bool,minVersion: freezed == minVersion ? _self.minVersion : minVersion // ignore: cast_nullable_to_non_nullable
as String?,latestVersion: freezed == latestVersion ? _self.latestVersion : latestVersion // ignore: cast_nullable_to_non_nullable
as String?,forceUpdate: null == forceUpdate ? _self.forceUpdate : forceUpdate // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$OtpConfigDto {

 int get length; int get ttlSeconds; int get resendSeconds;
/// Create a copy of OtpConfigDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OtpConfigDtoCopyWith<OtpConfigDto> get copyWith => _$OtpConfigDtoCopyWithImpl<OtpConfigDto>(this as OtpConfigDto, _$identity);

  /// Serializes this OtpConfigDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OtpConfigDto&&(identical(other.length, length) || other.length == length)&&(identical(other.ttlSeconds, ttlSeconds) || other.ttlSeconds == ttlSeconds)&&(identical(other.resendSeconds, resendSeconds) || other.resendSeconds == resendSeconds));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,length,ttlSeconds,resendSeconds);

@override
String toString() {
  return 'OtpConfigDto(length: $length, ttlSeconds: $ttlSeconds, resendSeconds: $resendSeconds)';
}


}

/// @nodoc
abstract mixin class $OtpConfigDtoCopyWith<$Res>  {
  factory $OtpConfigDtoCopyWith(OtpConfigDto value, $Res Function(OtpConfigDto) _then) = _$OtpConfigDtoCopyWithImpl;
@useResult
$Res call({
 int length, int ttlSeconds, int resendSeconds
});




}
/// @nodoc
class _$OtpConfigDtoCopyWithImpl<$Res>
    implements $OtpConfigDtoCopyWith<$Res> {
  _$OtpConfigDtoCopyWithImpl(this._self, this._then);

  final OtpConfigDto _self;
  final $Res Function(OtpConfigDto) _then;

/// Create a copy of OtpConfigDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? length = null,Object? ttlSeconds = null,Object? resendSeconds = null,}) {
  return _then(_self.copyWith(
length: null == length ? _self.length : length // ignore: cast_nullable_to_non_nullable
as int,ttlSeconds: null == ttlSeconds ? _self.ttlSeconds : ttlSeconds // ignore: cast_nullable_to_non_nullable
as int,resendSeconds: null == resendSeconds ? _self.resendSeconds : resendSeconds // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [OtpConfigDto].
extension OtpConfigDtoPatterns on OtpConfigDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OtpConfigDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OtpConfigDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OtpConfigDto value)  $default,){
final _that = this;
switch (_that) {
case _OtpConfigDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OtpConfigDto value)?  $default,){
final _that = this;
switch (_that) {
case _OtpConfigDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int length,  int ttlSeconds,  int resendSeconds)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OtpConfigDto() when $default != null:
return $default(_that.length,_that.ttlSeconds,_that.resendSeconds);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int length,  int ttlSeconds,  int resendSeconds)  $default,) {final _that = this;
switch (_that) {
case _OtpConfigDto():
return $default(_that.length,_that.ttlSeconds,_that.resendSeconds);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int length,  int ttlSeconds,  int resendSeconds)?  $default,) {final _that = this;
switch (_that) {
case _OtpConfigDto() when $default != null:
return $default(_that.length,_that.ttlSeconds,_that.resendSeconds);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _OtpConfigDto implements OtpConfigDto {
  const _OtpConfigDto({this.length = 6, this.ttlSeconds = 120, this.resendSeconds = 30});
  factory _OtpConfigDto.fromJson(Map<String, dynamic> json) => _$OtpConfigDtoFromJson(json);

@override@JsonKey() final  int length;
@override@JsonKey() final  int ttlSeconds;
@override@JsonKey() final  int resendSeconds;

/// Create a copy of OtpConfigDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OtpConfigDtoCopyWith<_OtpConfigDto> get copyWith => __$OtpConfigDtoCopyWithImpl<_OtpConfigDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$OtpConfigDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OtpConfigDto&&(identical(other.length, length) || other.length == length)&&(identical(other.ttlSeconds, ttlSeconds) || other.ttlSeconds == ttlSeconds)&&(identical(other.resendSeconds, resendSeconds) || other.resendSeconds == resendSeconds));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,length,ttlSeconds,resendSeconds);

@override
String toString() {
  return 'OtpConfigDto(length: $length, ttlSeconds: $ttlSeconds, resendSeconds: $resendSeconds)';
}


}

/// @nodoc
abstract mixin class _$OtpConfigDtoCopyWith<$Res> implements $OtpConfigDtoCopyWith<$Res> {
  factory _$OtpConfigDtoCopyWith(_OtpConfigDto value, $Res Function(_OtpConfigDto) _then) = __$OtpConfigDtoCopyWithImpl;
@override @useResult
$Res call({
 int length, int ttlSeconds, int resendSeconds
});




}
/// @nodoc
class __$OtpConfigDtoCopyWithImpl<$Res>
    implements _$OtpConfigDtoCopyWith<$Res> {
  __$OtpConfigDtoCopyWithImpl(this._self, this._then);

  final _OtpConfigDto _self;
  final $Res Function(_OtpConfigDto) _then;

/// Create a copy of OtpConfigDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? length = null,Object? ttlSeconds = null,Object? resendSeconds = null,}) {
  return _then(_OtpConfigDto(
length: null == length ? _self.length : length // ignore: cast_nullable_to_non_nullable
as int,ttlSeconds: null == ttlSeconds ? _self.ttlSeconds : ttlSeconds // ignore: cast_nullable_to_non_nullable
as int,resendSeconds: null == resendSeconds ? _self.resendSeconds : resendSeconds // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}


/// @nodoc
mixin _$FeatureFlagsDto {

 bool get emailOtp; bool get smsLogin; bool get registration;
/// Create a copy of FeatureFlagsDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$FeatureFlagsDtoCopyWith<FeatureFlagsDto> get copyWith => _$FeatureFlagsDtoCopyWithImpl<FeatureFlagsDto>(this as FeatureFlagsDto, _$identity);

  /// Serializes this FeatureFlagsDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is FeatureFlagsDto&&(identical(other.emailOtp, emailOtp) || other.emailOtp == emailOtp)&&(identical(other.smsLogin, smsLogin) || other.smsLogin == smsLogin)&&(identical(other.registration, registration) || other.registration == registration));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,emailOtp,smsLogin,registration);

@override
String toString() {
  return 'FeatureFlagsDto(emailOtp: $emailOtp, smsLogin: $smsLogin, registration: $registration)';
}


}

/// @nodoc
abstract mixin class $FeatureFlagsDtoCopyWith<$Res>  {
  factory $FeatureFlagsDtoCopyWith(FeatureFlagsDto value, $Res Function(FeatureFlagsDto) _then) = _$FeatureFlagsDtoCopyWithImpl;
@useResult
$Res call({
 bool emailOtp, bool smsLogin, bool registration
});




}
/// @nodoc
class _$FeatureFlagsDtoCopyWithImpl<$Res>
    implements $FeatureFlagsDtoCopyWith<$Res> {
  _$FeatureFlagsDtoCopyWithImpl(this._self, this._then);

  final FeatureFlagsDto _self;
  final $Res Function(FeatureFlagsDto) _then;

/// Create a copy of FeatureFlagsDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? emailOtp = null,Object? smsLogin = null,Object? registration = null,}) {
  return _then(_self.copyWith(
emailOtp: null == emailOtp ? _self.emailOtp : emailOtp // ignore: cast_nullable_to_non_nullable
as bool,smsLogin: null == smsLogin ? _self.smsLogin : smsLogin // ignore: cast_nullable_to_non_nullable
as bool,registration: null == registration ? _self.registration : registration // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [FeatureFlagsDto].
extension FeatureFlagsDtoPatterns on FeatureFlagsDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _FeatureFlagsDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _FeatureFlagsDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _FeatureFlagsDto value)  $default,){
final _that = this;
switch (_that) {
case _FeatureFlagsDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _FeatureFlagsDto value)?  $default,){
final _that = this;
switch (_that) {
case _FeatureFlagsDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( bool emailOtp,  bool smsLogin,  bool registration)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _FeatureFlagsDto() when $default != null:
return $default(_that.emailOtp,_that.smsLogin,_that.registration);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( bool emailOtp,  bool smsLogin,  bool registration)  $default,) {final _that = this;
switch (_that) {
case _FeatureFlagsDto():
return $default(_that.emailOtp,_that.smsLogin,_that.registration);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( bool emailOtp,  bool smsLogin,  bool registration)?  $default,) {final _that = this;
switch (_that) {
case _FeatureFlagsDto() when $default != null:
return $default(_that.emailOtp,_that.smsLogin,_that.registration);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _FeatureFlagsDto implements FeatureFlagsDto {
  const _FeatureFlagsDto({this.emailOtp = true, this.smsLogin = false, this.registration = true});
  factory _FeatureFlagsDto.fromJson(Map<String, dynamic> json) => _$FeatureFlagsDtoFromJson(json);

@override@JsonKey() final  bool emailOtp;
@override@JsonKey() final  bool smsLogin;
@override@JsonKey() final  bool registration;

/// Create a copy of FeatureFlagsDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$FeatureFlagsDtoCopyWith<_FeatureFlagsDto> get copyWith => __$FeatureFlagsDtoCopyWithImpl<_FeatureFlagsDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$FeatureFlagsDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _FeatureFlagsDto&&(identical(other.emailOtp, emailOtp) || other.emailOtp == emailOtp)&&(identical(other.smsLogin, smsLogin) || other.smsLogin == smsLogin)&&(identical(other.registration, registration) || other.registration == registration));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,emailOtp,smsLogin,registration);

@override
String toString() {
  return 'FeatureFlagsDto(emailOtp: $emailOtp, smsLogin: $smsLogin, registration: $registration)';
}


}

/// @nodoc
abstract mixin class _$FeatureFlagsDtoCopyWith<$Res> implements $FeatureFlagsDtoCopyWith<$Res> {
  factory _$FeatureFlagsDtoCopyWith(_FeatureFlagsDto value, $Res Function(_FeatureFlagsDto) _then) = __$FeatureFlagsDtoCopyWithImpl;
@override @useResult
$Res call({
 bool emailOtp, bool smsLogin, bool registration
});




}
/// @nodoc
class __$FeatureFlagsDtoCopyWithImpl<$Res>
    implements _$FeatureFlagsDtoCopyWith<$Res> {
  __$FeatureFlagsDtoCopyWithImpl(this._self, this._then);

  final _FeatureFlagsDto _self;
  final $Res Function(_FeatureFlagsDto) _then;

/// Create a copy of FeatureFlagsDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? emailOtp = null,Object? smsLogin = null,Object? registration = null,}) {
  return _then(_FeatureFlagsDto(
emailOtp: null == emailOtp ? _self.emailOtp : emailOtp // ignore: cast_nullable_to_non_nullable
as bool,smsLogin: null == smsLogin ? _self.smsLogin : smsLogin // ignore: cast_nullable_to_non_nullable
as bool,registration: null == registration ? _self.registration : registration // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}


/// @nodoc
mixin _$PublicSettingsDto {

 String? get brand; String? get defaultLocale; String? get defaultTimezone; String? get currency; String? get logoUrl;
/// Create a copy of PublicSettingsDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$PublicSettingsDtoCopyWith<PublicSettingsDto> get copyWith => _$PublicSettingsDtoCopyWithImpl<PublicSettingsDto>(this as PublicSettingsDto, _$identity);

  /// Serializes this PublicSettingsDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is PublicSettingsDto&&(identical(other.brand, brand) || other.brand == brand)&&(identical(other.defaultLocale, defaultLocale) || other.defaultLocale == defaultLocale)&&(identical(other.defaultTimezone, defaultTimezone) || other.defaultTimezone == defaultTimezone)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.logoUrl, logoUrl) || other.logoUrl == logoUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,brand,defaultLocale,defaultTimezone,currency,logoUrl);

@override
String toString() {
  return 'PublicSettingsDto(brand: $brand, defaultLocale: $defaultLocale, defaultTimezone: $defaultTimezone, currency: $currency, logoUrl: $logoUrl)';
}


}

/// @nodoc
abstract mixin class $PublicSettingsDtoCopyWith<$Res>  {
  factory $PublicSettingsDtoCopyWith(PublicSettingsDto value, $Res Function(PublicSettingsDto) _then) = _$PublicSettingsDtoCopyWithImpl;
@useResult
$Res call({
 String? brand, String? defaultLocale, String? defaultTimezone, String? currency, String? logoUrl
});




}
/// @nodoc
class _$PublicSettingsDtoCopyWithImpl<$Res>
    implements $PublicSettingsDtoCopyWith<$Res> {
  _$PublicSettingsDtoCopyWithImpl(this._self, this._then);

  final PublicSettingsDto _self;
  final $Res Function(PublicSettingsDto) _then;

/// Create a copy of PublicSettingsDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? brand = freezed,Object? defaultLocale = freezed,Object? defaultTimezone = freezed,Object? currency = freezed,Object? logoUrl = freezed,}) {
  return _then(_self.copyWith(
brand: freezed == brand ? _self.brand : brand // ignore: cast_nullable_to_non_nullable
as String?,defaultLocale: freezed == defaultLocale ? _self.defaultLocale : defaultLocale // ignore: cast_nullable_to_non_nullable
as String?,defaultTimezone: freezed == defaultTimezone ? _self.defaultTimezone : defaultTimezone // ignore: cast_nullable_to_non_nullable
as String?,currency: freezed == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String?,logoUrl: freezed == logoUrl ? _self.logoUrl : logoUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [PublicSettingsDto].
extension PublicSettingsDtoPatterns on PublicSettingsDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _PublicSettingsDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _PublicSettingsDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _PublicSettingsDto value)  $default,){
final _that = this;
switch (_that) {
case _PublicSettingsDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _PublicSettingsDto value)?  $default,){
final _that = this;
switch (_that) {
case _PublicSettingsDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? brand,  String? defaultLocale,  String? defaultTimezone,  String? currency,  String? logoUrl)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _PublicSettingsDto() when $default != null:
return $default(_that.brand,_that.defaultLocale,_that.defaultTimezone,_that.currency,_that.logoUrl);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? brand,  String? defaultLocale,  String? defaultTimezone,  String? currency,  String? logoUrl)  $default,) {final _that = this;
switch (_that) {
case _PublicSettingsDto():
return $default(_that.brand,_that.defaultLocale,_that.defaultTimezone,_that.currency,_that.logoUrl);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? brand,  String? defaultLocale,  String? defaultTimezone,  String? currency,  String? logoUrl)?  $default,) {final _that = this;
switch (_that) {
case _PublicSettingsDto() when $default != null:
return $default(_that.brand,_that.defaultLocale,_that.defaultTimezone,_that.currency,_that.logoUrl);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _PublicSettingsDto implements PublicSettingsDto {
  const _PublicSettingsDto({this.brand, this.defaultLocale, this.defaultTimezone, this.currency, this.logoUrl});
  factory _PublicSettingsDto.fromJson(Map<String, dynamic> json) => _$PublicSettingsDtoFromJson(json);

@override final  String? brand;
@override final  String? defaultLocale;
@override final  String? defaultTimezone;
@override final  String? currency;
@override final  String? logoUrl;

/// Create a copy of PublicSettingsDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$PublicSettingsDtoCopyWith<_PublicSettingsDto> get copyWith => __$PublicSettingsDtoCopyWithImpl<_PublicSettingsDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$PublicSettingsDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _PublicSettingsDto&&(identical(other.brand, brand) || other.brand == brand)&&(identical(other.defaultLocale, defaultLocale) || other.defaultLocale == defaultLocale)&&(identical(other.defaultTimezone, defaultTimezone) || other.defaultTimezone == defaultTimezone)&&(identical(other.currency, currency) || other.currency == currency)&&(identical(other.logoUrl, logoUrl) || other.logoUrl == logoUrl));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,brand,defaultLocale,defaultTimezone,currency,logoUrl);

@override
String toString() {
  return 'PublicSettingsDto(brand: $brand, defaultLocale: $defaultLocale, defaultTimezone: $defaultTimezone, currency: $currency, logoUrl: $logoUrl)';
}


}

/// @nodoc
abstract mixin class _$PublicSettingsDtoCopyWith<$Res> implements $PublicSettingsDtoCopyWith<$Res> {
  factory _$PublicSettingsDtoCopyWith(_PublicSettingsDto value, $Res Function(_PublicSettingsDto) _then) = __$PublicSettingsDtoCopyWithImpl;
@override @useResult
$Res call({
 String? brand, String? defaultLocale, String? defaultTimezone, String? currency, String? logoUrl
});




}
/// @nodoc
class __$PublicSettingsDtoCopyWithImpl<$Res>
    implements _$PublicSettingsDtoCopyWith<$Res> {
  __$PublicSettingsDtoCopyWithImpl(this._self, this._then);

  final _PublicSettingsDto _self;
  final $Res Function(_PublicSettingsDto) _then;

/// Create a copy of PublicSettingsDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? brand = freezed,Object? defaultLocale = freezed,Object? defaultTimezone = freezed,Object? currency = freezed,Object? logoUrl = freezed,}) {
  return _then(_PublicSettingsDto(
brand: freezed == brand ? _self.brand : brand // ignore: cast_nullable_to_non_nullable
as String?,defaultLocale: freezed == defaultLocale ? _self.defaultLocale : defaultLocale // ignore: cast_nullable_to_non_nullable
as String?,defaultTimezone: freezed == defaultTimezone ? _self.defaultTimezone : defaultTimezone // ignore: cast_nullable_to_non_nullable
as String?,currency: freezed == currency ? _self.currency : currency // ignore: cast_nullable_to_non_nullable
as String?,logoUrl: freezed == logoUrl ? _self.logoUrl : logoUrl // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$SupportDto {

 String? get email; String? get phone;
/// Create a copy of SupportDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SupportDtoCopyWith<SupportDto> get copyWith => _$SupportDtoCopyWithImpl<SupportDto>(this as SupportDto, _$identity);

  /// Serializes this SupportDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SupportDto&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,email,phone);

@override
String toString() {
  return 'SupportDto(email: $email, phone: $phone)';
}


}

/// @nodoc
abstract mixin class $SupportDtoCopyWith<$Res>  {
  factory $SupportDtoCopyWith(SupportDto value, $Res Function(SupportDto) _then) = _$SupportDtoCopyWithImpl;
@useResult
$Res call({
 String? email, String? phone
});




}
/// @nodoc
class _$SupportDtoCopyWithImpl<$Res>
    implements $SupportDtoCopyWith<$Res> {
  _$SupportDtoCopyWithImpl(this._self, this._then);

  final SupportDto _self;
  final $Res Function(SupportDto) _then;

/// Create a copy of SupportDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? email = freezed,Object? phone = freezed,}) {
  return _then(_self.copyWith(
email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [SupportDto].
extension SupportDtoPatterns on SupportDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SupportDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SupportDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SupportDto value)  $default,){
final _that = this;
switch (_that) {
case _SupportDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SupportDto value)?  $default,){
final _that = this;
switch (_that) {
case _SupportDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? email,  String? phone)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SupportDto() when $default != null:
return $default(_that.email,_that.phone);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? email,  String? phone)  $default,) {final _that = this;
switch (_that) {
case _SupportDto():
return $default(_that.email,_that.phone);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? email,  String? phone)?  $default,) {final _that = this;
switch (_that) {
case _SupportDto() when $default != null:
return $default(_that.email,_that.phone);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SupportDto implements SupportDto {
  const _SupportDto({this.email, this.phone});
  factory _SupportDto.fromJson(Map<String, dynamic> json) => _$SupportDtoFromJson(json);

@override final  String? email;
@override final  String? phone;

/// Create a copy of SupportDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SupportDtoCopyWith<_SupportDto> get copyWith => __$SupportDtoCopyWithImpl<_SupportDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SupportDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SupportDto&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,email,phone);

@override
String toString() {
  return 'SupportDto(email: $email, phone: $phone)';
}


}

/// @nodoc
abstract mixin class _$SupportDtoCopyWith<$Res> implements $SupportDtoCopyWith<$Res> {
  factory _$SupportDtoCopyWith(_SupportDto value, $Res Function(_SupportDto) _then) = __$SupportDtoCopyWithImpl;
@override @useResult
$Res call({
 String? email, String? phone
});




}
/// @nodoc
class __$SupportDtoCopyWithImpl<$Res>
    implements _$SupportDtoCopyWith<$Res> {
  __$SupportDtoCopyWithImpl(this._self, this._then);

  final _SupportDto _self;
  final $Res Function(_SupportDto) _then;

/// Create a copy of SupportDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? email = freezed,Object? phone = freezed,}) {
  return _then(_SupportDto(
email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
