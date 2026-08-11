// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'auth_entities.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$UserEntity {

 int get id; String get phone; String? get fullName; String? get email; bool get emailVerified; String get preferredLanguage; String get status; bool get hasActiveSubscription;
/// Create a copy of UserEntity
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$UserEntityCopyWith<UserEntity> get copyWith => _$UserEntityCopyWithImpl<UserEntity>(this as UserEntity, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is UserEntity&&(identical(other.id, id) || other.id == id)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.fullName, fullName) || other.fullName == fullName)&&(identical(other.email, email) || other.email == email)&&(identical(other.emailVerified, emailVerified) || other.emailVerified == emailVerified)&&(identical(other.preferredLanguage, preferredLanguage) || other.preferredLanguage == preferredLanguage)&&(identical(other.status, status) || other.status == status)&&(identical(other.hasActiveSubscription, hasActiveSubscription) || other.hasActiveSubscription == hasActiveSubscription));
}


@override
int get hashCode => Object.hash(runtimeType,id,phone,fullName,email,emailVerified,preferredLanguage,status,hasActiveSubscription);

@override
String toString() {
  return 'UserEntity(id: $id, phone: $phone, fullName: $fullName, email: $email, emailVerified: $emailVerified, preferredLanguage: $preferredLanguage, status: $status, hasActiveSubscription: $hasActiveSubscription)';
}


}

/// @nodoc
abstract mixin class $UserEntityCopyWith<$Res>  {
  factory $UserEntityCopyWith(UserEntity value, $Res Function(UserEntity) _then) = _$UserEntityCopyWithImpl;
@useResult
$Res call({
 int id, String phone, String? fullName, String? email, bool emailVerified, String preferredLanguage, String status, bool hasActiveSubscription
});




}
/// @nodoc
class _$UserEntityCopyWithImpl<$Res>
    implements $UserEntityCopyWith<$Res> {
  _$UserEntityCopyWithImpl(this._self, this._then);

  final UserEntity _self;
  final $Res Function(UserEntity) _then;

/// Create a copy of UserEntity
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? phone = null,Object? fullName = freezed,Object? email = freezed,Object? emailVerified = null,Object? preferredLanguage = null,Object? status = null,Object? hasActiveSubscription = null,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,phone: null == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String,fullName: freezed == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String?,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,preferredLanguage: null == preferredLanguage ? _self.preferredLanguage : preferredLanguage // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,hasActiveSubscription: null == hasActiveSubscription ? _self.hasActiveSubscription : hasActiveSubscription // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [UserEntity].
extension UserEntityPatterns on UserEntity {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _UserEntity value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _UserEntity() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _UserEntity value)  $default,){
final _that = this;
switch (_that) {
case _UserEntity():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _UserEntity value)?  $default,){
final _that = this;
switch (_that) {
case _UserEntity() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String phone,  String? fullName,  String? email,  bool emailVerified,  String preferredLanguage,  String status,  bool hasActiveSubscription)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _UserEntity() when $default != null:
return $default(_that.id,_that.phone,_that.fullName,_that.email,_that.emailVerified,_that.preferredLanguage,_that.status,_that.hasActiveSubscription);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String phone,  String? fullName,  String? email,  bool emailVerified,  String preferredLanguage,  String status,  bool hasActiveSubscription)  $default,) {final _that = this;
switch (_that) {
case _UserEntity():
return $default(_that.id,_that.phone,_that.fullName,_that.email,_that.emailVerified,_that.preferredLanguage,_that.status,_that.hasActiveSubscription);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String phone,  String? fullName,  String? email,  bool emailVerified,  String preferredLanguage,  String status,  bool hasActiveSubscription)?  $default,) {final _that = this;
switch (_that) {
case _UserEntity() when $default != null:
return $default(_that.id,_that.phone,_that.fullName,_that.email,_that.emailVerified,_that.preferredLanguage,_that.status,_that.hasActiveSubscription);case _:
  return null;

}
}

}

/// @nodoc


class _UserEntity implements UserEntity {
  const _UserEntity({required this.id, required this.phone, this.fullName, this.email, this.emailVerified = false, this.preferredLanguage = 'az', this.status = 'active', this.hasActiveSubscription = false});
  

@override final  int id;
@override final  String phone;
@override final  String? fullName;
@override final  String? email;
@override@JsonKey() final  bool emailVerified;
@override@JsonKey() final  String preferredLanguage;
@override@JsonKey() final  String status;
@override@JsonKey() final  bool hasActiveSubscription;

/// Create a copy of UserEntity
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$UserEntityCopyWith<_UserEntity> get copyWith => __$UserEntityCopyWithImpl<_UserEntity>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _UserEntity&&(identical(other.id, id) || other.id == id)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.fullName, fullName) || other.fullName == fullName)&&(identical(other.email, email) || other.email == email)&&(identical(other.emailVerified, emailVerified) || other.emailVerified == emailVerified)&&(identical(other.preferredLanguage, preferredLanguage) || other.preferredLanguage == preferredLanguage)&&(identical(other.status, status) || other.status == status)&&(identical(other.hasActiveSubscription, hasActiveSubscription) || other.hasActiveSubscription == hasActiveSubscription));
}


@override
int get hashCode => Object.hash(runtimeType,id,phone,fullName,email,emailVerified,preferredLanguage,status,hasActiveSubscription);

@override
String toString() {
  return 'UserEntity(id: $id, phone: $phone, fullName: $fullName, email: $email, emailVerified: $emailVerified, preferredLanguage: $preferredLanguage, status: $status, hasActiveSubscription: $hasActiveSubscription)';
}


}

/// @nodoc
abstract mixin class _$UserEntityCopyWith<$Res> implements $UserEntityCopyWith<$Res> {
  factory _$UserEntityCopyWith(_UserEntity value, $Res Function(_UserEntity) _then) = __$UserEntityCopyWithImpl;
@override @useResult
$Res call({
 int id, String phone, String? fullName, String? email, bool emailVerified, String preferredLanguage, String status, bool hasActiveSubscription
});




}
/// @nodoc
class __$UserEntityCopyWithImpl<$Res>
    implements _$UserEntityCopyWith<$Res> {
  __$UserEntityCopyWithImpl(this._self, this._then);

  final _UserEntity _self;
  final $Res Function(_UserEntity) _then;

/// Create a copy of UserEntity
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? phone = null,Object? fullName = freezed,Object? email = freezed,Object? emailVerified = null,Object? preferredLanguage = null,Object? status = null,Object? hasActiveSubscription = null,}) {
  return _then(_UserEntity(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,phone: null == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String,fullName: freezed == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String?,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,emailVerified: null == emailVerified ? _self.emailVerified : emailVerified // ignore: cast_nullable_to_non_nullable
as bool,preferredLanguage: null == preferredLanguage ? _self.preferredLanguage : preferredLanguage // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,hasActiveSubscription: null == hasActiveSubscription ? _self.hasActiveSubscription : hasActiveSubscription // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

/// @nodoc
mixin _$AppStatusEntity {

 bool get maintenanceMode; String? get minVersion; String? get latestVersion; bool get forceUpdate;
/// Create a copy of AppStatusEntity
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$AppStatusEntityCopyWith<AppStatusEntity> get copyWith => _$AppStatusEntityCopyWithImpl<AppStatusEntity>(this as AppStatusEntity, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is AppStatusEntity&&(identical(other.maintenanceMode, maintenanceMode) || other.maintenanceMode == maintenanceMode)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.latestVersion, latestVersion) || other.latestVersion == latestVersion)&&(identical(other.forceUpdate, forceUpdate) || other.forceUpdate == forceUpdate));
}


@override
int get hashCode => Object.hash(runtimeType,maintenanceMode,minVersion,latestVersion,forceUpdate);

@override
String toString() {
  return 'AppStatusEntity(maintenanceMode: $maintenanceMode, minVersion: $minVersion, latestVersion: $latestVersion, forceUpdate: $forceUpdate)';
}


}

/// @nodoc
abstract mixin class $AppStatusEntityCopyWith<$Res>  {
  factory $AppStatusEntityCopyWith(AppStatusEntity value, $Res Function(AppStatusEntity) _then) = _$AppStatusEntityCopyWithImpl;
@useResult
$Res call({
 bool maintenanceMode, String? minVersion, String? latestVersion, bool forceUpdate
});




}
/// @nodoc
class _$AppStatusEntityCopyWithImpl<$Res>
    implements $AppStatusEntityCopyWith<$Res> {
  _$AppStatusEntityCopyWithImpl(this._self, this._then);

  final AppStatusEntity _self;
  final $Res Function(AppStatusEntity) _then;

/// Create a copy of AppStatusEntity
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


/// Adds pattern-matching-related methods to [AppStatusEntity].
extension AppStatusEntityPatterns on AppStatusEntity {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _AppStatusEntity value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _AppStatusEntity() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _AppStatusEntity value)  $default,){
final _that = this;
switch (_that) {
case _AppStatusEntity():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _AppStatusEntity value)?  $default,){
final _that = this;
switch (_that) {
case _AppStatusEntity() when $default != null:
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
case _AppStatusEntity() when $default != null:
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
case _AppStatusEntity():
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
case _AppStatusEntity() when $default != null:
return $default(_that.maintenanceMode,_that.minVersion,_that.latestVersion,_that.forceUpdate);case _:
  return null;

}
}

}

/// @nodoc


class _AppStatusEntity implements AppStatusEntity {
  const _AppStatusEntity({this.maintenanceMode = false, this.minVersion, this.latestVersion, this.forceUpdate = false});
  

@override@JsonKey() final  bool maintenanceMode;
@override final  String? minVersion;
@override final  String? latestVersion;
@override@JsonKey() final  bool forceUpdate;

/// Create a copy of AppStatusEntity
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$AppStatusEntityCopyWith<_AppStatusEntity> get copyWith => __$AppStatusEntityCopyWithImpl<_AppStatusEntity>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _AppStatusEntity&&(identical(other.maintenanceMode, maintenanceMode) || other.maintenanceMode == maintenanceMode)&&(identical(other.minVersion, minVersion) || other.minVersion == minVersion)&&(identical(other.latestVersion, latestVersion) || other.latestVersion == latestVersion)&&(identical(other.forceUpdate, forceUpdate) || other.forceUpdate == forceUpdate));
}


@override
int get hashCode => Object.hash(runtimeType,maintenanceMode,minVersion,latestVersion,forceUpdate);

@override
String toString() {
  return 'AppStatusEntity(maintenanceMode: $maintenanceMode, minVersion: $minVersion, latestVersion: $latestVersion, forceUpdate: $forceUpdate)';
}


}

/// @nodoc
abstract mixin class _$AppStatusEntityCopyWith<$Res> implements $AppStatusEntityCopyWith<$Res> {
  factory _$AppStatusEntityCopyWith(_AppStatusEntity value, $Res Function(_AppStatusEntity) _then) = __$AppStatusEntityCopyWithImpl;
@override @useResult
$Res call({
 bool maintenanceMode, String? minVersion, String? latestVersion, bool forceUpdate
});




}
/// @nodoc
class __$AppStatusEntityCopyWithImpl<$Res>
    implements _$AppStatusEntityCopyWith<$Res> {
  __$AppStatusEntityCopyWithImpl(this._self, this._then);

  final _AppStatusEntity _self;
  final $Res Function(_AppStatusEntity) _then;

/// Create a copy of AppStatusEntity
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? maintenanceMode = null,Object? minVersion = freezed,Object? latestVersion = freezed,Object? forceUpdate = null,}) {
  return _then(_AppStatusEntity(
maintenanceMode: null == maintenanceMode ? _self.maintenanceMode : maintenanceMode // ignore: cast_nullable_to_non_nullable
as bool,minVersion: freezed == minVersion ? _self.minVersion : minVersion // ignore: cast_nullable_to_non_nullable
as String?,latestVersion: freezed == latestVersion ? _self.latestVersion : latestVersion // ignore: cast_nullable_to_non_nullable
as String?,forceUpdate: null == forceUpdate ? _self.forceUpdate : forceUpdate // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

/// @nodoc
mixin _$BootstrapEntity {

 AppStatusEntity get app; int get otpLength; int get otpTtlSeconds; int get otpResendSeconds; bool get emailOtpEnabled; bool get registrationEnabled; String? get brand; String? get supportEmail; String? get supportPhone;
/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$BootstrapEntityCopyWith<BootstrapEntity> get copyWith => _$BootstrapEntityCopyWithImpl<BootstrapEntity>(this as BootstrapEntity, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is BootstrapEntity&&(identical(other.app, app) || other.app == app)&&(identical(other.otpLength, otpLength) || other.otpLength == otpLength)&&(identical(other.otpTtlSeconds, otpTtlSeconds) || other.otpTtlSeconds == otpTtlSeconds)&&(identical(other.otpResendSeconds, otpResendSeconds) || other.otpResendSeconds == otpResendSeconds)&&(identical(other.emailOtpEnabled, emailOtpEnabled) || other.emailOtpEnabled == emailOtpEnabled)&&(identical(other.registrationEnabled, registrationEnabled) || other.registrationEnabled == registrationEnabled)&&(identical(other.brand, brand) || other.brand == brand)&&(identical(other.supportEmail, supportEmail) || other.supportEmail == supportEmail)&&(identical(other.supportPhone, supportPhone) || other.supportPhone == supportPhone));
}


@override
int get hashCode => Object.hash(runtimeType,app,otpLength,otpTtlSeconds,otpResendSeconds,emailOtpEnabled,registrationEnabled,brand,supportEmail,supportPhone);

@override
String toString() {
  return 'BootstrapEntity(app: $app, otpLength: $otpLength, otpTtlSeconds: $otpTtlSeconds, otpResendSeconds: $otpResendSeconds, emailOtpEnabled: $emailOtpEnabled, registrationEnabled: $registrationEnabled, brand: $brand, supportEmail: $supportEmail, supportPhone: $supportPhone)';
}


}

/// @nodoc
abstract mixin class $BootstrapEntityCopyWith<$Res>  {
  factory $BootstrapEntityCopyWith(BootstrapEntity value, $Res Function(BootstrapEntity) _then) = _$BootstrapEntityCopyWithImpl;
@useResult
$Res call({
 AppStatusEntity app, int otpLength, int otpTtlSeconds, int otpResendSeconds, bool emailOtpEnabled, bool registrationEnabled, String? brand, String? supportEmail, String? supportPhone
});


$AppStatusEntityCopyWith<$Res> get app;

}
/// @nodoc
class _$BootstrapEntityCopyWithImpl<$Res>
    implements $BootstrapEntityCopyWith<$Res> {
  _$BootstrapEntityCopyWithImpl(this._self, this._then);

  final BootstrapEntity _self;
  final $Res Function(BootstrapEntity) _then;

/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? app = null,Object? otpLength = null,Object? otpTtlSeconds = null,Object? otpResendSeconds = null,Object? emailOtpEnabled = null,Object? registrationEnabled = null,Object? brand = freezed,Object? supportEmail = freezed,Object? supportPhone = freezed,}) {
  return _then(_self.copyWith(
app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppStatusEntity,otpLength: null == otpLength ? _self.otpLength : otpLength // ignore: cast_nullable_to_non_nullable
as int,otpTtlSeconds: null == otpTtlSeconds ? _self.otpTtlSeconds : otpTtlSeconds // ignore: cast_nullable_to_non_nullable
as int,otpResendSeconds: null == otpResendSeconds ? _self.otpResendSeconds : otpResendSeconds // ignore: cast_nullable_to_non_nullable
as int,emailOtpEnabled: null == emailOtpEnabled ? _self.emailOtpEnabled : emailOtpEnabled // ignore: cast_nullable_to_non_nullable
as bool,registrationEnabled: null == registrationEnabled ? _self.registrationEnabled : registrationEnabled // ignore: cast_nullable_to_non_nullable
as bool,brand: freezed == brand ? _self.brand : brand // ignore: cast_nullable_to_non_nullable
as String?,supportEmail: freezed == supportEmail ? _self.supportEmail : supportEmail // ignore: cast_nullable_to_non_nullable
as String?,supportPhone: freezed == supportPhone ? _self.supportPhone : supportPhone // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}
/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppStatusEntityCopyWith<$Res> get app {
  
  return $AppStatusEntityCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}


/// Adds pattern-matching-related methods to [BootstrapEntity].
extension BootstrapEntityPatterns on BootstrapEntity {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _BootstrapEntity value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _BootstrapEntity() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _BootstrapEntity value)  $default,){
final _that = this;
switch (_that) {
case _BootstrapEntity():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _BootstrapEntity value)?  $default,){
final _that = this;
switch (_that) {
case _BootstrapEntity() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( AppStatusEntity app,  int otpLength,  int otpTtlSeconds,  int otpResendSeconds,  bool emailOtpEnabled,  bool registrationEnabled,  String? brand,  String? supportEmail,  String? supportPhone)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _BootstrapEntity() when $default != null:
return $default(_that.app,_that.otpLength,_that.otpTtlSeconds,_that.otpResendSeconds,_that.emailOtpEnabled,_that.registrationEnabled,_that.brand,_that.supportEmail,_that.supportPhone);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( AppStatusEntity app,  int otpLength,  int otpTtlSeconds,  int otpResendSeconds,  bool emailOtpEnabled,  bool registrationEnabled,  String? brand,  String? supportEmail,  String? supportPhone)  $default,) {final _that = this;
switch (_that) {
case _BootstrapEntity():
return $default(_that.app,_that.otpLength,_that.otpTtlSeconds,_that.otpResendSeconds,_that.emailOtpEnabled,_that.registrationEnabled,_that.brand,_that.supportEmail,_that.supportPhone);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( AppStatusEntity app,  int otpLength,  int otpTtlSeconds,  int otpResendSeconds,  bool emailOtpEnabled,  bool registrationEnabled,  String? brand,  String? supportEmail,  String? supportPhone)?  $default,) {final _that = this;
switch (_that) {
case _BootstrapEntity() when $default != null:
return $default(_that.app,_that.otpLength,_that.otpTtlSeconds,_that.otpResendSeconds,_that.emailOtpEnabled,_that.registrationEnabled,_that.brand,_that.supportEmail,_that.supportPhone);case _:
  return null;

}
}

}

/// @nodoc


class _BootstrapEntity implements BootstrapEntity {
  const _BootstrapEntity({required this.app, this.otpLength = 6, this.otpTtlSeconds = 120, this.otpResendSeconds = 30, this.emailOtpEnabled = true, this.registrationEnabled = true, this.brand, this.supportEmail, this.supportPhone});
  

@override final  AppStatusEntity app;
@override@JsonKey() final  int otpLength;
@override@JsonKey() final  int otpTtlSeconds;
@override@JsonKey() final  int otpResendSeconds;
@override@JsonKey() final  bool emailOtpEnabled;
@override@JsonKey() final  bool registrationEnabled;
@override final  String? brand;
@override final  String? supportEmail;
@override final  String? supportPhone;

/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$BootstrapEntityCopyWith<_BootstrapEntity> get copyWith => __$BootstrapEntityCopyWithImpl<_BootstrapEntity>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _BootstrapEntity&&(identical(other.app, app) || other.app == app)&&(identical(other.otpLength, otpLength) || other.otpLength == otpLength)&&(identical(other.otpTtlSeconds, otpTtlSeconds) || other.otpTtlSeconds == otpTtlSeconds)&&(identical(other.otpResendSeconds, otpResendSeconds) || other.otpResendSeconds == otpResendSeconds)&&(identical(other.emailOtpEnabled, emailOtpEnabled) || other.emailOtpEnabled == emailOtpEnabled)&&(identical(other.registrationEnabled, registrationEnabled) || other.registrationEnabled == registrationEnabled)&&(identical(other.brand, brand) || other.brand == brand)&&(identical(other.supportEmail, supportEmail) || other.supportEmail == supportEmail)&&(identical(other.supportPhone, supportPhone) || other.supportPhone == supportPhone));
}


@override
int get hashCode => Object.hash(runtimeType,app,otpLength,otpTtlSeconds,otpResendSeconds,emailOtpEnabled,registrationEnabled,brand,supportEmail,supportPhone);

@override
String toString() {
  return 'BootstrapEntity(app: $app, otpLength: $otpLength, otpTtlSeconds: $otpTtlSeconds, otpResendSeconds: $otpResendSeconds, emailOtpEnabled: $emailOtpEnabled, registrationEnabled: $registrationEnabled, brand: $brand, supportEmail: $supportEmail, supportPhone: $supportPhone)';
}


}

/// @nodoc
abstract mixin class _$BootstrapEntityCopyWith<$Res> implements $BootstrapEntityCopyWith<$Res> {
  factory _$BootstrapEntityCopyWith(_BootstrapEntity value, $Res Function(_BootstrapEntity) _then) = __$BootstrapEntityCopyWithImpl;
@override @useResult
$Res call({
 AppStatusEntity app, int otpLength, int otpTtlSeconds, int otpResendSeconds, bool emailOtpEnabled, bool registrationEnabled, String? brand, String? supportEmail, String? supportPhone
});


@override $AppStatusEntityCopyWith<$Res> get app;

}
/// @nodoc
class __$BootstrapEntityCopyWithImpl<$Res>
    implements _$BootstrapEntityCopyWith<$Res> {
  __$BootstrapEntityCopyWithImpl(this._self, this._then);

  final _BootstrapEntity _self;
  final $Res Function(_BootstrapEntity) _then;

/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? app = null,Object? otpLength = null,Object? otpTtlSeconds = null,Object? otpResendSeconds = null,Object? emailOtpEnabled = null,Object? registrationEnabled = null,Object? brand = freezed,Object? supportEmail = freezed,Object? supportPhone = freezed,}) {
  return _then(_BootstrapEntity(
app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppStatusEntity,otpLength: null == otpLength ? _self.otpLength : otpLength // ignore: cast_nullable_to_non_nullable
as int,otpTtlSeconds: null == otpTtlSeconds ? _self.otpTtlSeconds : otpTtlSeconds // ignore: cast_nullable_to_non_nullable
as int,otpResendSeconds: null == otpResendSeconds ? _self.otpResendSeconds : otpResendSeconds // ignore: cast_nullable_to_non_nullable
as int,emailOtpEnabled: null == emailOtpEnabled ? _self.emailOtpEnabled : emailOtpEnabled // ignore: cast_nullable_to_non_nullable
as bool,registrationEnabled: null == registrationEnabled ? _self.registrationEnabled : registrationEnabled // ignore: cast_nullable_to_non_nullable
as bool,brand: freezed == brand ? _self.brand : brand // ignore: cast_nullable_to_non_nullable
as String?,supportEmail: freezed == supportEmail ? _self.supportEmail : supportEmail // ignore: cast_nullable_to_non_nullable
as String?,supportPhone: freezed == supportPhone ? _self.supportPhone : supportPhone // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

/// Create a copy of BootstrapEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppStatusEntityCopyWith<$Res> get app {
  
  return $AppStatusEntityCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}

/// @nodoc
mixin _$OtpDispatch {

 int get expiresInSeconds; int get resendAvailableInSeconds;
/// Create a copy of OtpDispatch
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$OtpDispatchCopyWith<OtpDispatch> get copyWith => _$OtpDispatchCopyWithImpl<OtpDispatch>(this as OtpDispatch, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is OtpDispatch&&(identical(other.expiresInSeconds, expiresInSeconds) || other.expiresInSeconds == expiresInSeconds)&&(identical(other.resendAvailableInSeconds, resendAvailableInSeconds) || other.resendAvailableInSeconds == resendAvailableInSeconds));
}


@override
int get hashCode => Object.hash(runtimeType,expiresInSeconds,resendAvailableInSeconds);

@override
String toString() {
  return 'OtpDispatch(expiresInSeconds: $expiresInSeconds, resendAvailableInSeconds: $resendAvailableInSeconds)';
}


}

/// @nodoc
abstract mixin class $OtpDispatchCopyWith<$Res>  {
  factory $OtpDispatchCopyWith(OtpDispatch value, $Res Function(OtpDispatch) _then) = _$OtpDispatchCopyWithImpl;
@useResult
$Res call({
 int expiresInSeconds, int resendAvailableInSeconds
});




}
/// @nodoc
class _$OtpDispatchCopyWithImpl<$Res>
    implements $OtpDispatchCopyWith<$Res> {
  _$OtpDispatchCopyWithImpl(this._self, this._then);

  final OtpDispatch _self;
  final $Res Function(OtpDispatch) _then;

/// Create a copy of OtpDispatch
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? expiresInSeconds = null,Object? resendAvailableInSeconds = null,}) {
  return _then(_self.copyWith(
expiresInSeconds: null == expiresInSeconds ? _self.expiresInSeconds : expiresInSeconds // ignore: cast_nullable_to_non_nullable
as int,resendAvailableInSeconds: null == resendAvailableInSeconds ? _self.resendAvailableInSeconds : resendAvailableInSeconds // ignore: cast_nullable_to_non_nullable
as int,
  ));
}

}


/// Adds pattern-matching-related methods to [OtpDispatch].
extension OtpDispatchPatterns on OtpDispatch {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _OtpDispatch value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _OtpDispatch() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _OtpDispatch value)  $default,){
final _that = this;
switch (_that) {
case _OtpDispatch():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _OtpDispatch value)?  $default,){
final _that = this;
switch (_that) {
case _OtpDispatch() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int expiresInSeconds,  int resendAvailableInSeconds)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _OtpDispatch() when $default != null:
return $default(_that.expiresInSeconds,_that.resendAvailableInSeconds);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int expiresInSeconds,  int resendAvailableInSeconds)  $default,) {final _that = this;
switch (_that) {
case _OtpDispatch():
return $default(_that.expiresInSeconds,_that.resendAvailableInSeconds);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int expiresInSeconds,  int resendAvailableInSeconds)?  $default,) {final _that = this;
switch (_that) {
case _OtpDispatch() when $default != null:
return $default(_that.expiresInSeconds,_that.resendAvailableInSeconds);case _:
  return null;

}
}

}

/// @nodoc


class _OtpDispatch implements OtpDispatch {
  const _OtpDispatch({this.expiresInSeconds = 120, this.resendAvailableInSeconds = 30});
  

@override@JsonKey() final  int expiresInSeconds;
@override@JsonKey() final  int resendAvailableInSeconds;

/// Create a copy of OtpDispatch
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$OtpDispatchCopyWith<_OtpDispatch> get copyWith => __$OtpDispatchCopyWithImpl<_OtpDispatch>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _OtpDispatch&&(identical(other.expiresInSeconds, expiresInSeconds) || other.expiresInSeconds == expiresInSeconds)&&(identical(other.resendAvailableInSeconds, resendAvailableInSeconds) || other.resendAvailableInSeconds == resendAvailableInSeconds));
}


@override
int get hashCode => Object.hash(runtimeType,expiresInSeconds,resendAvailableInSeconds);

@override
String toString() {
  return 'OtpDispatch(expiresInSeconds: $expiresInSeconds, resendAvailableInSeconds: $resendAvailableInSeconds)';
}


}

/// @nodoc
abstract mixin class _$OtpDispatchCopyWith<$Res> implements $OtpDispatchCopyWith<$Res> {
  factory _$OtpDispatchCopyWith(_OtpDispatch value, $Res Function(_OtpDispatch) _then) = __$OtpDispatchCopyWithImpl;
@override @useResult
$Res call({
 int expiresInSeconds, int resendAvailableInSeconds
});




}
/// @nodoc
class __$OtpDispatchCopyWithImpl<$Res>
    implements _$OtpDispatchCopyWith<$Res> {
  __$OtpDispatchCopyWithImpl(this._self, this._then);

  final _OtpDispatch _self;
  final $Res Function(_OtpDispatch) _then;

/// Create a copy of OtpDispatch
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? expiresInSeconds = null,Object? resendAvailableInSeconds = null,}) {
  return _then(_OtpDispatch(
expiresInSeconds: null == expiresInSeconds ? _self.expiresInSeconds : expiresInSeconds // ignore: cast_nullable_to_non_nullable
as int,resendAvailableInSeconds: null == resendAvailableInSeconds ? _self.resendAvailableInSeconds : resendAvailableInSeconds // ignore: cast_nullable_to_non_nullable
as int,
  ));
}


}

/// @nodoc
mixin _$MeEntity {

 UserEntity get user; AppStatusEntity get app;
/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$MeEntityCopyWith<MeEntity> get copyWith => _$MeEntityCopyWithImpl<MeEntity>(this as MeEntity, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is MeEntity&&(identical(other.user, user) || other.user == user)&&(identical(other.app, app) || other.app == app));
}


@override
int get hashCode => Object.hash(runtimeType,user,app);

@override
String toString() {
  return 'MeEntity(user: $user, app: $app)';
}


}

/// @nodoc
abstract mixin class $MeEntityCopyWith<$Res>  {
  factory $MeEntityCopyWith(MeEntity value, $Res Function(MeEntity) _then) = _$MeEntityCopyWithImpl;
@useResult
$Res call({
 UserEntity user, AppStatusEntity app
});


$UserEntityCopyWith<$Res> get user;$AppStatusEntityCopyWith<$Res> get app;

}
/// @nodoc
class _$MeEntityCopyWithImpl<$Res>
    implements $MeEntityCopyWith<$Res> {
  _$MeEntityCopyWithImpl(this._self, this._then);

  final MeEntity _self;
  final $Res Function(MeEntity) _then;

/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? user = null,Object? app = null,}) {
  return _then(_self.copyWith(
user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as UserEntity,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppStatusEntity,
  ));
}
/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$UserEntityCopyWith<$Res> get user {
  
  return $UserEntityCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppStatusEntityCopyWith<$Res> get app {
  
  return $AppStatusEntityCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}


/// Adds pattern-matching-related methods to [MeEntity].
extension MeEntityPatterns on MeEntity {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _MeEntity value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _MeEntity() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _MeEntity value)  $default,){
final _that = this;
switch (_that) {
case _MeEntity():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _MeEntity value)?  $default,){
final _that = this;
switch (_that) {
case _MeEntity() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( UserEntity user,  AppStatusEntity app)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _MeEntity() when $default != null:
return $default(_that.user,_that.app);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( UserEntity user,  AppStatusEntity app)  $default,) {final _that = this;
switch (_that) {
case _MeEntity():
return $default(_that.user,_that.app);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( UserEntity user,  AppStatusEntity app)?  $default,) {final _that = this;
switch (_that) {
case _MeEntity() when $default != null:
return $default(_that.user,_that.app);case _:
  return null;

}
}

}

/// @nodoc


class _MeEntity implements MeEntity {
  const _MeEntity({required this.user, required this.app});
  

@override final  UserEntity user;
@override final  AppStatusEntity app;

/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$MeEntityCopyWith<_MeEntity> get copyWith => __$MeEntityCopyWithImpl<_MeEntity>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _MeEntity&&(identical(other.user, user) || other.user == user)&&(identical(other.app, app) || other.app == app));
}


@override
int get hashCode => Object.hash(runtimeType,user,app);

@override
String toString() {
  return 'MeEntity(user: $user, app: $app)';
}


}

/// @nodoc
abstract mixin class _$MeEntityCopyWith<$Res> implements $MeEntityCopyWith<$Res> {
  factory _$MeEntityCopyWith(_MeEntity value, $Res Function(_MeEntity) _then) = __$MeEntityCopyWithImpl;
@override @useResult
$Res call({
 UserEntity user, AppStatusEntity app
});


@override $UserEntityCopyWith<$Res> get user;@override $AppStatusEntityCopyWith<$Res> get app;

}
/// @nodoc
class __$MeEntityCopyWithImpl<$Res>
    implements _$MeEntityCopyWith<$Res> {
  __$MeEntityCopyWithImpl(this._self, this._then);

  final _MeEntity _self;
  final $Res Function(_MeEntity) _then;

/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? user = null,Object? app = null,}) {
  return _then(_MeEntity(
user: null == user ? _self.user : user // ignore: cast_nullable_to_non_nullable
as UserEntity,app: null == app ? _self.app : app // ignore: cast_nullable_to_non_nullable
as AppStatusEntity,
  ));
}

/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$UserEntityCopyWith<$Res> get user {
  
  return $UserEntityCopyWith<$Res>(_self.user, (value) {
    return _then(_self.copyWith(user: value));
  });
}/// Create a copy of MeEntity
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$AppStatusEntityCopyWith<$Res> get app {
  
  return $AppStatusEntityCopyWith<$Res>(_self.app, (value) {
    return _then(_self.copyWith(app: value));
  });
}
}

// dart format on
