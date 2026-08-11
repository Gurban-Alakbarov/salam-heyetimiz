// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'device_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$DeviceDto {

 int get id; String? get label; String? get serial; String? get imageUrl; String? get address; String? get status; String? get role; bool? get canOpen; String? get suspensionReason; double? get latitude; double? get longitude; String? get lastOnlineAt; DeviceModelDto? get deviceModel; int? get cooldownSecondsRemaining; SubscriptionBriefDto? get subscription;
/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeviceDtoCopyWith<DeviceDto> get copyWith => _$DeviceDtoCopyWithImpl<DeviceDto>(this as DeviceDto, _$identity);

  /// Serializes this DeviceDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DeviceDto&&(identical(other.id, id) || other.id == id)&&(identical(other.label, label) || other.label == label)&&(identical(other.serial, serial) || other.serial == serial)&&(identical(other.imageUrl, imageUrl) || other.imageUrl == imageUrl)&&(identical(other.address, address) || other.address == address)&&(identical(other.status, status) || other.status == status)&&(identical(other.role, role) || other.role == role)&&(identical(other.canOpen, canOpen) || other.canOpen == canOpen)&&(identical(other.suspensionReason, suspensionReason) || other.suspensionReason == suspensionReason)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude)&&(identical(other.lastOnlineAt, lastOnlineAt) || other.lastOnlineAt == lastOnlineAt)&&(identical(other.deviceModel, deviceModel) || other.deviceModel == deviceModel)&&(identical(other.cooldownSecondsRemaining, cooldownSecondsRemaining) || other.cooldownSecondsRemaining == cooldownSecondsRemaining)&&(identical(other.subscription, subscription) || other.subscription == subscription));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,label,serial,imageUrl,address,status,role,canOpen,suspensionReason,latitude,longitude,lastOnlineAt,deviceModel,cooldownSecondsRemaining,subscription);

@override
String toString() {
  return 'DeviceDto(id: $id, label: $label, serial: $serial, imageUrl: $imageUrl, address: $address, status: $status, role: $role, canOpen: $canOpen, suspensionReason: $suspensionReason, latitude: $latitude, longitude: $longitude, lastOnlineAt: $lastOnlineAt, deviceModel: $deviceModel, cooldownSecondsRemaining: $cooldownSecondsRemaining, subscription: $subscription)';
}


}

/// @nodoc
abstract mixin class $DeviceDtoCopyWith<$Res>  {
  factory $DeviceDtoCopyWith(DeviceDto value, $Res Function(DeviceDto) _then) = _$DeviceDtoCopyWithImpl;
@useResult
$Res call({
 int id, String? label, String? serial, String? imageUrl, String? address, String? status, String? role, bool? canOpen, String? suspensionReason, double? latitude, double? longitude, String? lastOnlineAt, DeviceModelDto? deviceModel, int? cooldownSecondsRemaining, SubscriptionBriefDto? subscription
});


$DeviceModelDtoCopyWith<$Res>? get deviceModel;$SubscriptionBriefDtoCopyWith<$Res>? get subscription;

}
/// @nodoc
class _$DeviceDtoCopyWithImpl<$Res>
    implements $DeviceDtoCopyWith<$Res> {
  _$DeviceDtoCopyWithImpl(this._self, this._then);

  final DeviceDto _self;
  final $Res Function(DeviceDto) _then;

/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? label = freezed,Object? serial = freezed,Object? imageUrl = freezed,Object? address = freezed,Object? status = freezed,Object? role = freezed,Object? canOpen = freezed,Object? suspensionReason = freezed,Object? latitude = freezed,Object? longitude = freezed,Object? lastOnlineAt = freezed,Object? deviceModel = freezed,Object? cooldownSecondsRemaining = freezed,Object? subscription = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,label: freezed == label ? _self.label : label // ignore: cast_nullable_to_non_nullable
as String?,serial: freezed == serial ? _self.serial : serial // ignore: cast_nullable_to_non_nullable
as String?,imageUrl: freezed == imageUrl ? _self.imageUrl : imageUrl // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,role: freezed == role ? _self.role : role // ignore: cast_nullable_to_non_nullable
as String?,canOpen: freezed == canOpen ? _self.canOpen : canOpen // ignore: cast_nullable_to_non_nullable
as bool?,suspensionReason: freezed == suspensionReason ? _self.suspensionReason : suspensionReason // ignore: cast_nullable_to_non_nullable
as String?,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,lastOnlineAt: freezed == lastOnlineAt ? _self.lastOnlineAt : lastOnlineAt // ignore: cast_nullable_to_non_nullable
as String?,deviceModel: freezed == deviceModel ? _self.deviceModel : deviceModel // ignore: cast_nullable_to_non_nullable
as DeviceModelDto?,cooldownSecondsRemaining: freezed == cooldownSecondsRemaining ? _self.cooldownSecondsRemaining : cooldownSecondsRemaining // ignore: cast_nullable_to_non_nullable
as int?,subscription: freezed == subscription ? _self.subscription : subscription // ignore: cast_nullable_to_non_nullable
as SubscriptionBriefDto?,
  ));
}
/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceModelDtoCopyWith<$Res>? get deviceModel {
    if (_self.deviceModel == null) {
    return null;
  }

  return $DeviceModelDtoCopyWith<$Res>(_self.deviceModel!, (value) {
    return _then(_self.copyWith(deviceModel: value));
  });
}/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SubscriptionBriefDtoCopyWith<$Res>? get subscription {
    if (_self.subscription == null) {
    return null;
  }

  return $SubscriptionBriefDtoCopyWith<$Res>(_self.subscription!, (value) {
    return _then(_self.copyWith(subscription: value));
  });
}
}


/// Adds pattern-matching-related methods to [DeviceDto].
extension DeviceDtoPatterns on DeviceDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DeviceDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DeviceDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DeviceDto value)  $default,){
final _that = this;
switch (_that) {
case _DeviceDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DeviceDto value)?  $default,){
final _that = this;
switch (_that) {
case _DeviceDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String? label,  String? serial,  String? imageUrl,  String? address,  String? status,  String? role,  bool? canOpen,  String? suspensionReason,  double? latitude,  double? longitude,  String? lastOnlineAt,  DeviceModelDto? deviceModel,  int? cooldownSecondsRemaining,  SubscriptionBriefDto? subscription)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DeviceDto() when $default != null:
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.lastOnlineAt,_that.deviceModel,_that.cooldownSecondsRemaining,_that.subscription);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String? label,  String? serial,  String? imageUrl,  String? address,  String? status,  String? role,  bool? canOpen,  String? suspensionReason,  double? latitude,  double? longitude,  String? lastOnlineAt,  DeviceModelDto? deviceModel,  int? cooldownSecondsRemaining,  SubscriptionBriefDto? subscription)  $default,) {final _that = this;
switch (_that) {
case _DeviceDto():
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.lastOnlineAt,_that.deviceModel,_that.cooldownSecondsRemaining,_that.subscription);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String? label,  String? serial,  String? imageUrl,  String? address,  String? status,  String? role,  bool? canOpen,  String? suspensionReason,  double? latitude,  double? longitude,  String? lastOnlineAt,  DeviceModelDto? deviceModel,  int? cooldownSecondsRemaining,  SubscriptionBriefDto? subscription)?  $default,) {final _that = this;
switch (_that) {
case _DeviceDto() when $default != null:
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.lastOnlineAt,_that.deviceModel,_that.cooldownSecondsRemaining,_that.subscription);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DeviceDto implements DeviceDto {
  const _DeviceDto({required this.id, this.label, this.serial, this.imageUrl, this.address, this.status, this.role, this.canOpen, this.suspensionReason, this.latitude, this.longitude, this.lastOnlineAt, this.deviceModel, this.cooldownSecondsRemaining, this.subscription});
  factory _DeviceDto.fromJson(Map<String, dynamic> json) => _$DeviceDtoFromJson(json);

@override final  int id;
@override final  String? label;
@override final  String? serial;
@override final  String? imageUrl;
@override final  String? address;
@override final  String? status;
@override final  String? role;
@override final  bool? canOpen;
@override final  String? suspensionReason;
@override final  double? latitude;
@override final  double? longitude;
@override final  String? lastOnlineAt;
@override final  DeviceModelDto? deviceModel;
@override final  int? cooldownSecondsRemaining;
@override final  SubscriptionBriefDto? subscription;

/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeviceDtoCopyWith<_DeviceDto> get copyWith => __$DeviceDtoCopyWithImpl<_DeviceDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DeviceDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DeviceDto&&(identical(other.id, id) || other.id == id)&&(identical(other.label, label) || other.label == label)&&(identical(other.serial, serial) || other.serial == serial)&&(identical(other.imageUrl, imageUrl) || other.imageUrl == imageUrl)&&(identical(other.address, address) || other.address == address)&&(identical(other.status, status) || other.status == status)&&(identical(other.role, role) || other.role == role)&&(identical(other.canOpen, canOpen) || other.canOpen == canOpen)&&(identical(other.suspensionReason, suspensionReason) || other.suspensionReason == suspensionReason)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude)&&(identical(other.lastOnlineAt, lastOnlineAt) || other.lastOnlineAt == lastOnlineAt)&&(identical(other.deviceModel, deviceModel) || other.deviceModel == deviceModel)&&(identical(other.cooldownSecondsRemaining, cooldownSecondsRemaining) || other.cooldownSecondsRemaining == cooldownSecondsRemaining)&&(identical(other.subscription, subscription) || other.subscription == subscription));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,label,serial,imageUrl,address,status,role,canOpen,suspensionReason,latitude,longitude,lastOnlineAt,deviceModel,cooldownSecondsRemaining,subscription);

@override
String toString() {
  return 'DeviceDto(id: $id, label: $label, serial: $serial, imageUrl: $imageUrl, address: $address, status: $status, role: $role, canOpen: $canOpen, suspensionReason: $suspensionReason, latitude: $latitude, longitude: $longitude, lastOnlineAt: $lastOnlineAt, deviceModel: $deviceModel, cooldownSecondsRemaining: $cooldownSecondsRemaining, subscription: $subscription)';
}


}

/// @nodoc
abstract mixin class _$DeviceDtoCopyWith<$Res> implements $DeviceDtoCopyWith<$Res> {
  factory _$DeviceDtoCopyWith(_DeviceDto value, $Res Function(_DeviceDto) _then) = __$DeviceDtoCopyWithImpl;
@override @useResult
$Res call({
 int id, String? label, String? serial, String? imageUrl, String? address, String? status, String? role, bool? canOpen, String? suspensionReason, double? latitude, double? longitude, String? lastOnlineAt, DeviceModelDto? deviceModel, int? cooldownSecondsRemaining, SubscriptionBriefDto? subscription
});


@override $DeviceModelDtoCopyWith<$Res>? get deviceModel;@override $SubscriptionBriefDtoCopyWith<$Res>? get subscription;

}
/// @nodoc
class __$DeviceDtoCopyWithImpl<$Res>
    implements _$DeviceDtoCopyWith<$Res> {
  __$DeviceDtoCopyWithImpl(this._self, this._then);

  final _DeviceDto _self;
  final $Res Function(_DeviceDto) _then;

/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? label = freezed,Object? serial = freezed,Object? imageUrl = freezed,Object? address = freezed,Object? status = freezed,Object? role = freezed,Object? canOpen = freezed,Object? suspensionReason = freezed,Object? latitude = freezed,Object? longitude = freezed,Object? lastOnlineAt = freezed,Object? deviceModel = freezed,Object? cooldownSecondsRemaining = freezed,Object? subscription = freezed,}) {
  return _then(_DeviceDto(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,label: freezed == label ? _self.label : label // ignore: cast_nullable_to_non_nullable
as String?,serial: freezed == serial ? _self.serial : serial // ignore: cast_nullable_to_non_nullable
as String?,imageUrl: freezed == imageUrl ? _self.imageUrl : imageUrl // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,role: freezed == role ? _self.role : role // ignore: cast_nullable_to_non_nullable
as String?,canOpen: freezed == canOpen ? _self.canOpen : canOpen // ignore: cast_nullable_to_non_nullable
as bool?,suspensionReason: freezed == suspensionReason ? _self.suspensionReason : suspensionReason // ignore: cast_nullable_to_non_nullable
as String?,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,lastOnlineAt: freezed == lastOnlineAt ? _self.lastOnlineAt : lastOnlineAt // ignore: cast_nullable_to_non_nullable
as String?,deviceModel: freezed == deviceModel ? _self.deviceModel : deviceModel // ignore: cast_nullable_to_non_nullable
as DeviceModelDto?,cooldownSecondsRemaining: freezed == cooldownSecondsRemaining ? _self.cooldownSecondsRemaining : cooldownSecondsRemaining // ignore: cast_nullable_to_non_nullable
as int?,subscription: freezed == subscription ? _self.subscription : subscription // ignore: cast_nullable_to_non_nullable
as SubscriptionBriefDto?,
  ));
}

/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceModelDtoCopyWith<$Res>? get deviceModel {
    if (_self.deviceModel == null) {
    return null;
  }

  return $DeviceModelDtoCopyWith<$Res>(_self.deviceModel!, (value) {
    return _then(_self.copyWith(deviceModel: value));
  });
}/// Create a copy of DeviceDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$SubscriptionBriefDtoCopyWith<$Res>? get subscription {
    if (_self.subscription == null) {
    return null;
  }

  return $SubscriptionBriefDtoCopyWith<$Res>(_self.subscription!, (value) {
    return _then(_self.copyWith(subscription: value));
  });
}
}


/// @nodoc
mixin _$DeviceModelDto {

 String? get vendor; String? get modelCode;
/// Create a copy of DeviceModelDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeviceModelDtoCopyWith<DeviceModelDto> get copyWith => _$DeviceModelDtoCopyWithImpl<DeviceModelDto>(this as DeviceModelDto, _$identity);

  /// Serializes this DeviceModelDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DeviceModelDto&&(identical(other.vendor, vendor) || other.vendor == vendor)&&(identical(other.modelCode, modelCode) || other.modelCode == modelCode));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,vendor,modelCode);

@override
String toString() {
  return 'DeviceModelDto(vendor: $vendor, modelCode: $modelCode)';
}


}

/// @nodoc
abstract mixin class $DeviceModelDtoCopyWith<$Res>  {
  factory $DeviceModelDtoCopyWith(DeviceModelDto value, $Res Function(DeviceModelDto) _then) = _$DeviceModelDtoCopyWithImpl;
@useResult
$Res call({
 String? vendor, String? modelCode
});




}
/// @nodoc
class _$DeviceModelDtoCopyWithImpl<$Res>
    implements $DeviceModelDtoCopyWith<$Res> {
  _$DeviceModelDtoCopyWithImpl(this._self, this._then);

  final DeviceModelDto _self;
  final $Res Function(DeviceModelDto) _then;

/// Create a copy of DeviceModelDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? vendor = freezed,Object? modelCode = freezed,}) {
  return _then(_self.copyWith(
vendor: freezed == vendor ? _self.vendor : vendor // ignore: cast_nullable_to_non_nullable
as String?,modelCode: freezed == modelCode ? _self.modelCode : modelCode // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [DeviceModelDto].
extension DeviceModelDtoPatterns on DeviceModelDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DeviceModelDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DeviceModelDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DeviceModelDto value)  $default,){
final _that = this;
switch (_that) {
case _DeviceModelDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DeviceModelDto value)?  $default,){
final _that = this;
switch (_that) {
case _DeviceModelDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? vendor,  String? modelCode)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DeviceModelDto() when $default != null:
return $default(_that.vendor,_that.modelCode);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? vendor,  String? modelCode)  $default,) {final _that = this;
switch (_that) {
case _DeviceModelDto():
return $default(_that.vendor,_that.modelCode);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? vendor,  String? modelCode)?  $default,) {final _that = this;
switch (_that) {
case _DeviceModelDto() when $default != null:
return $default(_that.vendor,_that.modelCode);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _DeviceModelDto implements DeviceModelDto {
  const _DeviceModelDto({this.vendor, this.modelCode});
  factory _DeviceModelDto.fromJson(Map<String, dynamic> json) => _$DeviceModelDtoFromJson(json);

@override final  String? vendor;
@override final  String? modelCode;

/// Create a copy of DeviceModelDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeviceModelDtoCopyWith<_DeviceModelDto> get copyWith => __$DeviceModelDtoCopyWithImpl<_DeviceModelDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$DeviceModelDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DeviceModelDto&&(identical(other.vendor, vendor) || other.vendor == vendor)&&(identical(other.modelCode, modelCode) || other.modelCode == modelCode));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,vendor,modelCode);

@override
String toString() {
  return 'DeviceModelDto(vendor: $vendor, modelCode: $modelCode)';
}


}

/// @nodoc
abstract mixin class _$DeviceModelDtoCopyWith<$Res> implements $DeviceModelDtoCopyWith<$Res> {
  factory _$DeviceModelDtoCopyWith(_DeviceModelDto value, $Res Function(_DeviceModelDto) _then) = __$DeviceModelDtoCopyWithImpl;
@override @useResult
$Res call({
 String? vendor, String? modelCode
});




}
/// @nodoc
class __$DeviceModelDtoCopyWithImpl<$Res>
    implements _$DeviceModelDtoCopyWith<$Res> {
  __$DeviceModelDtoCopyWithImpl(this._self, this._then);

  final _DeviceModelDto _self;
  final $Res Function(_DeviceModelDto) _then;

/// Create a copy of DeviceModelDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? vendor = freezed,Object? modelCode = freezed,}) {
  return _then(_DeviceModelDto(
vendor: freezed == vendor ? _self.vendor : vendor // ignore: cast_nullable_to_non_nullable
as String?,modelCode: freezed == modelCode ? _self.modelCode : modelCode // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}


/// @nodoc
mixin _$SubscriptionBriefDto {

 int? get id; String? get tier; String? get status; String? get endsAt; int? get daysRemaining; bool? get autoRenew;
/// Create a copy of SubscriptionBriefDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$SubscriptionBriefDtoCopyWith<SubscriptionBriefDto> get copyWith => _$SubscriptionBriefDtoCopyWithImpl<SubscriptionBriefDto>(this as SubscriptionBriefDto, _$identity);

  /// Serializes this SubscriptionBriefDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is SubscriptionBriefDto&&(identical(other.id, id) || other.id == id)&&(identical(other.tier, tier) || other.tier == tier)&&(identical(other.status, status) || other.status == status)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.daysRemaining, daysRemaining) || other.daysRemaining == daysRemaining)&&(identical(other.autoRenew, autoRenew) || other.autoRenew == autoRenew));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,tier,status,endsAt,daysRemaining,autoRenew);

@override
String toString() {
  return 'SubscriptionBriefDto(id: $id, tier: $tier, status: $status, endsAt: $endsAt, daysRemaining: $daysRemaining, autoRenew: $autoRenew)';
}


}

/// @nodoc
abstract mixin class $SubscriptionBriefDtoCopyWith<$Res>  {
  factory $SubscriptionBriefDtoCopyWith(SubscriptionBriefDto value, $Res Function(SubscriptionBriefDto) _then) = _$SubscriptionBriefDtoCopyWithImpl;
@useResult
$Res call({
 int? id, String? tier, String? status, String? endsAt, int? daysRemaining, bool? autoRenew
});




}
/// @nodoc
class _$SubscriptionBriefDtoCopyWithImpl<$Res>
    implements $SubscriptionBriefDtoCopyWith<$Res> {
  _$SubscriptionBriefDtoCopyWithImpl(this._self, this._then);

  final SubscriptionBriefDto _self;
  final $Res Function(SubscriptionBriefDto) _then;

/// Create a copy of SubscriptionBriefDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = freezed,Object? tier = freezed,Object? status = freezed,Object? endsAt = freezed,Object? daysRemaining = freezed,Object? autoRenew = freezed,}) {
  return _then(_self.copyWith(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,tier: freezed == tier ? _self.tier : tier // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,endsAt: freezed == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as String?,daysRemaining: freezed == daysRemaining ? _self.daysRemaining : daysRemaining // ignore: cast_nullable_to_non_nullable
as int?,autoRenew: freezed == autoRenew ? _self.autoRenew : autoRenew // ignore: cast_nullable_to_non_nullable
as bool?,
  ));
}

}


/// Adds pattern-matching-related methods to [SubscriptionBriefDto].
extension SubscriptionBriefDtoPatterns on SubscriptionBriefDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _SubscriptionBriefDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _SubscriptionBriefDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _SubscriptionBriefDto value)  $default,){
final _that = this;
switch (_that) {
case _SubscriptionBriefDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _SubscriptionBriefDto value)?  $default,){
final _that = this;
switch (_that) {
case _SubscriptionBriefDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int? id,  String? tier,  String? status,  String? endsAt,  int? daysRemaining,  bool? autoRenew)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _SubscriptionBriefDto() when $default != null:
return $default(_that.id,_that.tier,_that.status,_that.endsAt,_that.daysRemaining,_that.autoRenew);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int? id,  String? tier,  String? status,  String? endsAt,  int? daysRemaining,  bool? autoRenew)  $default,) {final _that = this;
switch (_that) {
case _SubscriptionBriefDto():
return $default(_that.id,_that.tier,_that.status,_that.endsAt,_that.daysRemaining,_that.autoRenew);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int? id,  String? tier,  String? status,  String? endsAt,  int? daysRemaining,  bool? autoRenew)?  $default,) {final _that = this;
switch (_that) {
case _SubscriptionBriefDto() when $default != null:
return $default(_that.id,_that.tier,_that.status,_that.endsAt,_that.daysRemaining,_that.autoRenew);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _SubscriptionBriefDto implements SubscriptionBriefDto {
  const _SubscriptionBriefDto({this.id, this.tier, this.status, this.endsAt, this.daysRemaining, this.autoRenew});
  factory _SubscriptionBriefDto.fromJson(Map<String, dynamic> json) => _$SubscriptionBriefDtoFromJson(json);

@override final  int? id;
@override final  String? tier;
@override final  String? status;
@override final  String? endsAt;
@override final  int? daysRemaining;
@override final  bool? autoRenew;

/// Create a copy of SubscriptionBriefDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$SubscriptionBriefDtoCopyWith<_SubscriptionBriefDto> get copyWith => __$SubscriptionBriefDtoCopyWithImpl<_SubscriptionBriefDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$SubscriptionBriefDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _SubscriptionBriefDto&&(identical(other.id, id) || other.id == id)&&(identical(other.tier, tier) || other.tier == tier)&&(identical(other.status, status) || other.status == status)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.daysRemaining, daysRemaining) || other.daysRemaining == daysRemaining)&&(identical(other.autoRenew, autoRenew) || other.autoRenew == autoRenew));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,tier,status,endsAt,daysRemaining,autoRenew);

@override
String toString() {
  return 'SubscriptionBriefDto(id: $id, tier: $tier, status: $status, endsAt: $endsAt, daysRemaining: $daysRemaining, autoRenew: $autoRenew)';
}


}

/// @nodoc
abstract mixin class _$SubscriptionBriefDtoCopyWith<$Res> implements $SubscriptionBriefDtoCopyWith<$Res> {
  factory _$SubscriptionBriefDtoCopyWith(_SubscriptionBriefDto value, $Res Function(_SubscriptionBriefDto) _then) = __$SubscriptionBriefDtoCopyWithImpl;
@override @useResult
$Res call({
 int? id, String? tier, String? status, String? endsAt, int? daysRemaining, bool? autoRenew
});




}
/// @nodoc
class __$SubscriptionBriefDtoCopyWithImpl<$Res>
    implements _$SubscriptionBriefDtoCopyWith<$Res> {
  __$SubscriptionBriefDtoCopyWithImpl(this._self, this._then);

  final _SubscriptionBriefDto _self;
  final $Res Function(_SubscriptionBriefDto) _then;

/// Create a copy of SubscriptionBriefDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = freezed,Object? tier = freezed,Object? status = freezed,Object? endsAt = freezed,Object? daysRemaining = freezed,Object? autoRenew = freezed,}) {
  return _then(_SubscriptionBriefDto(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,tier: freezed == tier ? _self.tier : tier // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,endsAt: freezed == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as String?,daysRemaining: freezed == daysRemaining ? _self.daysRemaining : daysRemaining // ignore: cast_nullable_to_non_nullable
as int?,autoRenew: freezed == autoRenew ? _self.autoRenew : autoRenew // ignore: cast_nullable_to_non_nullable
as bool?,
  ));
}


}

// dart format on
