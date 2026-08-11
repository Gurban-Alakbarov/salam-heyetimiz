// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'bootstrap_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$BootstrapDto {

 AppConfigDto get app; OtpConfigDto get otp; FeatureFlagsDto get featureFlags; PublicSettingsDto? get publicSettings; SupportDto? get support;
/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BootstrapDtoCopyWith<BootstrapDto> get copyWith => _$BootstrapDtoCopyWithImpl<BootstrapDto>(this as BootstrapDto, _$identity);

  /// Serializes this BootstrapDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BootstrapDto&&(identical(other.app, app) || other.app == app)&&(identical(other.otp, otp) || other.otp == otp)&&(identical(other.featureFlags, featureFlags) || other.featureFlags == featureFlags)&&(identical(other.publicSettings, publicSettings) || other.publicSettings == publicSettings)&&(identical(other.support, support) || other.support == support));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,app,otp,featureFlags,publicSettings,support);

@override
String toString() {
  return 'BootstrapDto(app: $app, otp: $otp, featureFlags: $featureFlags, publicSettings: $publicSettings, support: $support)';
}


}

/// @nodoc
abstract mixin class $BootstrapDtoCopyWith<$Res>  {
  factory $BootstrapDtoCopyWith(BootstrapDto value, $Res Function(BootstrapDto) _then) = _$BootstrapDtoCopyWithImpl;
@useResult
$Res call({
 AppConfigDto app, OtpConfigDto otp, FeatureFlagsDto featureFlags, PublicSettingsDto? publicSettings, SupportDto? support
});


$AppConfigDtoCopyWith<$Res> get app;$OtpConfigDtoCopyWith<$Res> get otp;$FeatureFlagsDtoCopyWith<$Res> get featureFlags;$PublicSettingsDtoCopyWith<$Res>? get publicSettings;$SupportDtoCopyWith<$Res>? get support;

}
/// @nodoc
class _$BootstrapDtoCopyWithImpl<$Res>
    implements $BootstrapDtoCopyWith<$Res> {
  _$BootstrapDtoCopyWithImpl(this._self, this._then);

  final BootstrapDto _self;
  final $Res Function(BootstrapDto) _then;

/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? app = null,Object? otp = null,Object? featureFlags = null,Object? publicSettings = freezed,Object? support = freezed,}) {
  return _then(_self.copyWith(
app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppConfigDto,otp: null == otp ? _self.otp : otp // ignore: cast_nullable_to_non_nullable
as OtpConfigDto,featureFlags: null == featureFlags ? _self.featureFlags : featureFlags // ignore: cast_nullable_to_non_nullable
as FeatureFlagsDto,publicSettings: freezed == publicSettings ? _self.publicSettings : publicSettings // ignore: cast_nullable_to_non_nullable
as PublicSettingsDto?,support: freezed == support ? _self.support : support // ignore: cast_nullable_to_non_nullable
as SupportDto?,
  ));
}
/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppConfigDtoCopyWith<$Res> get app {
  
  return $AppConfigDtoCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$OtpConfigDtoCopyWith<$Res> get otp {
  
  return $OtpConfigDtoCopyWith<$Res>(_self.otp, (value) {
    return _then(_self.copyWith(otp: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$FeatureFlagsDtoCopyWith<$Res> get featureFlags {
  
  return $FeatureFlagsDtoCopyWith<$Res>(_self.featureFlags, (value) {
    return _then(_self.copyWith(featureFlags: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$PublicSettingsDtoCopyWith<$Res>? get publicSettings {
    if (_self.publicSettings == null) {
    return null;
  }

  return $PublicSettingsDtoCopyWith<$Res>(_self.publicSettings!, (value) {
    return _then(_self.copyWith(publicSettings: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SupportDtoCopyWith<$Res>? get support {
    if (_self.support == null) {
    return null;
  }

  return $SupportDtoCopyWith<$Res>(_self.support!, (value) {
    return _then(_self.copyWith(support: value));
  });
}
}


/// Adds pattern-matching-related methods to [BootstrapDto].
extension BootstrapDtoPatterns on BootstrapDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BootstrapDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BootstrapDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BootstrapDto value)  $default,){
final _that = this;
switch (_that) {
case _BootstrapDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BootstrapDto value)?  $default,){
final _that = this;
switch (_that) {
case _BootstrapDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( AppConfigDto app,  OtpConfigDto otp,  FeatureFlagsDto featureFlags,  PublicSettingsDto? publicSettings,  SupportDto? support)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BootstrapDto() when $default != null:
return $default(_that.app,_that.otp,_that.featureFlags,_that.publicSettings,_that.support);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( AppConfigDto app,  OtpConfigDto otp,  FeatureFlagsDto featureFlags,  PublicSettingsDto? publicSettings,  SupportDto? support)  $default,) {final _that = this;
switch (_that) {
case _BootstrapDto():
return $default(_that.app,_that.otp,_that.featureFlags,_that.publicSettings,_that.support);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( AppConfigDto app,  OtpConfigDto otp,  FeatureFlagsDto featureFlags,  PublicSettingsDto? publicSettings,  SupportDto? support)?  $default,) {final _that = this;
switch (_that) {
case _BootstrapDto() when $default != null:
return $default(_that.app,_that.otp,_that.featureFlags,_that.publicSettings,_that.support);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _BootstrapDto implements BootstrapDto {
  const _BootstrapDto({this.app = const AppConfigDto(), this.otp = const OtpConfigDto(), this.featureFlags = const FeatureFlagsDto(), this.publicSettings, this.support});
  factory _BootstrapDto.fromJson(Map<String, dynamic> json) => _$BootstrapDtoFromJson(json);

@override@JsonKey() final  AppConfigDto app;
@override@JsonKey() final  OtpConfigDto otp;
@override@JsonKey() final  FeatureFlagsDto featureFlags;
@override final  PublicSettingsDto? publicSettings;
@override final  SupportDto? support;

/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BootstrapDtoCopyWith<_BootstrapDto> get copyWith => __$BootstrapDtoCopyWithImpl<_BootstrapDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$BootstrapDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BootstrapDto&&(identical(other.app, app) || other.app == app)&&(identical(other.otp, otp) || other.otp == otp)&&(identical(other.featureFlags, featureFlags) || other.featureFlags == featureFlags)&&(identical(other.publicSettings, publicSettings) || other.publicSettings == publicSettings)&&(identical(other.support, support) || other.support == support));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,app,otp,featureFlags,publicSettings,support);

@override
String toString() {
  return 'BootstrapDto(app: $app, otp: $otp, featureFlags: $featureFlags, publicSettings: $publicSettings, support: $support)';
}


}

/// @nodoc
abstract mixin class _$BootstrapDtoCopyWith<$Res> implements $BootstrapDtoCopyWith<$Res> {
  factory _$BootstrapDtoCopyWith(_BootstrapDto value, $Res Function(_BootstrapDto) _then) = __$BootstrapDtoCopyWithImpl;
@override @useResult
$Res call({
 AppConfigDto app, OtpConfigDto otp, FeatureFlagsDto featureFlags, PublicSettingsDto? publicSettings, SupportDto? support
});


@override $AppConfigDtoCopyWith<$Res> get app;@override $OtpConfigDtoCopyWith<$Res> get otp;@override $FeatureFlagsDtoCopyWith<$Res> get featureFlags;@override $PublicSettingsDtoCopyWith<$Res>? get publicSettings;@override $SupportDtoCopyWith<$Res>? get support;

}
/// @nodoc
class __$BootstrapDtoCopyWithImpl<$Res>
    implements _$BootstrapDtoCopyWith<$Res> {
  __$BootstrapDtoCopyWithImpl(this._self, this._then);

  final _BootstrapDto _self;
  final $Res Function(_BootstrapDto) _then;

/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? app = null,Object? otp = null,Object? featureFlags = null,Object? publicSettings = freezed,Object? support = freezed,}) {
  return _then(_BootstrapDto(
app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppConfigDto,otp: null == otp ? _self.otp : otp // ignore: cast_nullable_to_non_nullable
as OtpConfigDto,featureFlags: null == featureFlags ? _self.featureFlags : featureFlags // ignore: cast_nullable_to_non_nullable
as FeatureFlagsDto,publicSettings: freezed == publicSettings ? _self.publicSettings : publicSettings // ignore: cast_nullable_to_non_nullable
as PublicSettingsDto?,support: freezed == support ? _self.support : support // ignore: cast_nullable_to_non_nullable
as SupportDto?,
  ));
}

/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppConfigDtoCopyWith<$Res> get app {
  
  return $AppConfigDtoCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$OtpConfigDtoCopyWith<$Res> get otp {
  
  return $OtpConfigDtoCopyWith<$Res>(_self.otp, (value) {
    return _then(_self.copyWith(otp: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$FeatureFlagsDtoCopyWith<$Res> get featureFlags {
  
  return $FeatureFlagsDtoCopyWith<$Res>(_self.featureFlags, (value) {
    return _then(_self.copyWith(featureFlags: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$PublicSettingsDtoCopyWith<$Res>? get publicSettings {
    if (_self.publicSettings == null) {
    return null;
  }

  return $PublicSettingsDtoCopyWith<$Res>(_self.publicSettings!, (value) {
    return _then(_self.copyWith(publicSettings: value));
  });
}/// Create a copy of BootstrapDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SupportDtoCopyWith<$Res>? get support {
    if (_self.support == null) {
    return null;
  }

  return $SupportDtoCopyWith<$Res>(_self.support!, (value) {
    return _then(_self.copyWith(support: value));
  });
}
}

// dart format on
