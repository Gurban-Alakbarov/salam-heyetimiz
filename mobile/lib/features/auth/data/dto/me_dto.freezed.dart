// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'me_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$MeDto {

 UserDto get user; AppConfigDto get app; FeatureFlagsDto get featureFlags; bool get registrationCompleted; bool get emailVerified; bool get hasPassword; String? get locale;
/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MeDtoCopyWith<MeDto> get copyWith => _$MeDtoCopyWithImpl<MeDto>(this as MeDto, _$identity);

  /// Serializes this MeDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MeDto&&(identical(other.user, user) || other.user == user)&&(identical(other.app, app) || other.app == app)&&(identical(other.featureFlags, featureFlags) || other.featureFlags == featureFlags)&&(identical(other.registrationCompleted, registrationCompleted) || other.registrationCompleted == registrationCompleted)&&(identical(other.emailVerified, emailVerified) || other.emailVerified == emailVerified)&&(identical(other.hasPassword, hasPassword) || other.hasPassword == hasPassword)&&(identical(other.locale, locale) || other.locale == locale));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,user,app,featureFlags,registrationCompleted,emailVerified,hasPassword,locale);

@override
String toString() {
  return 'MeDto(user: $user, app: $app, featureFlags: $featureFlags, registrationCompleted: $registrationCompleted, emailVerified: $emailVerified, hasPassword: $hasPassword, locale: $locale)';
}


}

/// @nodoc
abstract mixin class $MeDtoCopyWith<$Res>  {
  factory $MeDtoCopyWith(MeDto value, $Res Function(MeDto) _then) = _$MeDtoCopyWithImpl;
@useResult
$Res call({
 UserDto user, AppConfigDto app, FeatureFlagsDto featureFlags, bool registrationCompleted, bool emailVerified, bool hasPassword, String? locale
});


$UserDtoCopyWith<$Res> get user;$AppConfigDtoCopyWith<$Res> get app;$FeatureFlagsDtoCopyWith<$Res> get featureFlags;

}
/// @nodoc
class _$MeDtoCopyWithImpl<$Res>
    implements $MeDtoCopyWith<$Res> {
  _$MeDtoCopyWithImpl(this._self, this._then);

  final MeDto _self;
  final $Res Function(MeDto) _then;

/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? user = null,Object? app = null,Object? featureFlags = null,Object? registrationCompleted = null,Object? emailVerified = null,Object? hasPassword = null,Object? locale = freezed,}) {
  return _then(_self.copyWith(
user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as UserDto,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppConfigDto,featureFlags: null == featureFlags ? _self.featureFlags : featureFlags // ignore: cast_nullable_to_non_nullable
as FeatureFlagsDto,registrationCompleted: null == registrationCompleted ? _self.registrationCompleted : registrationCompleted // ignore: cast_nullable_to_non_nullable
as bool,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,hasPassword: null == hasPassword ? _self.hasPassword : hasPassword // ignore: cast_nullable_to_non_nullable
as bool,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}
/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$UserDtoCopyWith<$Res> get user {
  
  return $UserDtoCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppConfigDtoCopyWith<$Res> get app {
  
  return $AppConfigDtoCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$FeatureFlagsDtoCopyWith<$Res> get featureFlags {
  
  return $FeatureFlagsDtoCopyWith<$Res>(_self.featureFlags, (value) {
    return _then(_self.copyWith(featureFlags: value));
  });
}
}


/// Adds pattern-matching-related methods to [MeDto].
extension MeDtoPatterns on MeDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MeDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MeDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MeDto value)  $default,){
final _that = this;
switch (_that) {
case _MeDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MeDto value)?  $default,){
final _that = this;
switch (_that) {
case _MeDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( UserDto user,  AppConfigDto app,  FeatureFlagsDto featureFlags,  bool registrationCompleted,  bool emailVerified,  bool hasPassword,  String? locale)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MeDto() when $default != null:
return $default(_that.user,_that.app,_that.featureFlags,_that.registrationCompleted,_that.emailVerified,_that.hasPassword,_that.locale);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( UserDto user,  AppConfigDto app,  FeatureFlagsDto featureFlags,  bool registrationCompleted,  bool emailVerified,  bool hasPassword,  String? locale)  $default,) {final _that = this;
switch (_that) {
case _MeDto():
return $default(_that.user,_that.app,_that.featureFlags,_that.registrationCompleted,_that.emailVerified,_that.hasPassword,_that.locale);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( UserDto user,  AppConfigDto app,  FeatureFlagsDto featureFlags,  bool registrationCompleted,  bool emailVerified,  bool hasPassword,  String? locale)?  $default,) {final _that = this;
switch (_that) {
case _MeDto() when $default != null:
return $default(_that.user,_that.app,_that.featureFlags,_that.registrationCompleted,_that.emailVerified,_that.hasPassword,_that.locale);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _MeDto implements MeDto {
  const _MeDto({required this.user, this.app = const AppConfigDto(), this.featureFlags = const FeatureFlagsDto(), this.registrationCompleted = false, this.emailVerified = false, this.hasPassword = false, this.locale});
  factory _MeDto.fromJson(Map<String, dynamic> json) => _$MeDtoFromJson(json);

@override final  UserDto user;
@override@JsonKey() final  AppConfigDto app;
@override@JsonKey() final  FeatureFlagsDto featureFlags;
@override@JsonKey() final  bool registrationCompleted;
@override@JsonKey() final  bool emailVerified;
@override@JsonKey() final  bool hasPassword;
@override final  String? locale;

/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MeDtoCopyWith<_MeDto> get copyWith => __$MeDtoCopyWithImpl<_MeDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$MeDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MeDto&&(identical(other.user, user) || other.user == user)&&(identical(other.app, app) || other.app == app)&&(identical(other.featureFlags, featureFlags) || other.featureFlags == featureFlags)&&(identical(other.registrationCompleted, registrationCompleted) || other.registrationCompleted == registrationCompleted)&&(identical(other.emailVerified, emailVerified) || other.emailVerified == emailVerified)&&(identical(other.hasPassword, hasPassword) || other.hasPassword == hasPassword)&&(identical(other.locale, locale) || other.locale == locale));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,user,app,featureFlags,registrationCompleted,emailVerified,hasPassword,locale);

@override
String toString() {
  return 'MeDto(user: $user, app: $app, featureFlags: $featureFlags, registrationCompleted: $registrationCompleted, emailVerified: $emailVerified, hasPassword: $hasPassword, locale: $locale)';
}


}

/// @nodoc
abstract mixin class _$MeDtoCopyWith<$Res> implements $MeDtoCopyWith<$Res> {
  factory _$MeDtoCopyWith(_MeDto value, $Res Function(_MeDto) _then) = __$MeDtoCopyWithImpl;
@override @useResult
$Res call({
 UserDto user, AppConfigDto app, FeatureFlagsDto featureFlags, bool registrationCompleted, bool emailVerified, bool hasPassword, String? locale
});


@override $UserDtoCopyWith<$Res> get user;@override $AppConfigDtoCopyWith<$Res> get app;@override $FeatureFlagsDtoCopyWith<$Res> get featureFlags;

}
/// @nodoc
class __$MeDtoCopyWithImpl<$Res>
    implements _$MeDtoCopyWith<$Res> {
  __$MeDtoCopyWithImpl(this._self, this._then);

  final _MeDto _self;
  final $Res Function(_MeDto) _then;

/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? user = null,Object? app = null,Object? featureFlags = null,Object? registrationCompleted = null,Object? emailVerified = null,Object? hasPassword = null,Object? locale = freezed,}) {
  return _then(_MeDto(
user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as UserDto,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppConfigDto,featureFlags: null == featureFlags ? _self.featureFlags : featureFlags // ignore: cast_nullable_to_non_nullable
as FeatureFlagsDto,registrationCompleted: null == registrationCompleted ? _self.registrationCompleted : registrationCompleted // ignore: cast_nullable_to_non_nullable
as bool,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,hasPassword: null == hasPassword ? _self.hasPassword : hasPassword // ignore: cast_nullable_to_non_nullable
as bool,locale: freezed == locale ? _self.locale : locale // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$UserDtoCopyWith<$Res> get user {
  
  return $UserDtoCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppConfigDtoCopyWith<$Res> get app {
  
  return $AppConfigDtoCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}/// Create a copy of MeDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$FeatureFlagsDtoCopyWith<$Res> get featureFlags {
  
  return $FeatureFlagsDtoCopyWith<$Res>(_self.featureFlags, (value) {
    return _then(_self.copyWith(featureFlags: value));
  });
}
}

// dart format on
