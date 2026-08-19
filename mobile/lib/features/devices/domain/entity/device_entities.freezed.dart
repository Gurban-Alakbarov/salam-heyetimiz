// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'device_entities.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$Device {

 int get id; String get label; String? get serial; String? get imageUrl; String? get address; String get status; String get role; bool get canOpen; String get suspensionReason; double? get latitude; double? get longitude; bool get geofenceEnabled; int? get geofenceRadiusM; DateTime? get lastOnlineAt; DeviceModel? get model; int? get cooldownSecondsRemaining; DeviceSubscription? get subscription;
/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeviceCopyWith<Device> get copyWith => _$DeviceCopyWithImpl<Device>(this as Device, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Device&&(identical(other.id, id) || other.id == id)&&(identical(other.label, label) || other.label == label)&&(identical(other.serial, serial) || other.serial == serial)&&(identical(other.imageUrl, imageUrl) || other.imageUrl == imageUrl)&&(identical(other.address, address) || other.address == address)&&(identical(other.status, status) || other.status == status)&&(identical(other.role, role) || other.role == role)&&(identical(other.canOpen, canOpen) || other.canOpen == canOpen)&&(identical(other.suspensionReason, suspensionReason) || other.suspensionReason == suspensionReason)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude)&&(identical(other.geofenceEnabled, geofenceEnabled) || other.geofenceEnabled == geofenceEnabled)&&(identical(other.geofenceRadiusM, geofenceRadiusM) || other.geofenceRadiusM == geofenceRadiusM)&&(identical(other.lastOnlineAt, lastOnlineAt) || other.lastOnlineAt == lastOnlineAt)&&(identical(other.model, model) || other.model == model)&&(identical(other.cooldownSecondsRemaining, cooldownSecondsRemaining) || other.cooldownSecondsRemaining == cooldownSecondsRemaining)&&(identical(other.subscription, subscription) || other.subscription == subscription));
}


@override
int get hashCode => Object.hash(runtimeType,id,label,serial,imageUrl,address,status,role,canOpen,suspensionReason,latitude,longitude,geofenceEnabled,geofenceRadiusM,lastOnlineAt,model,cooldownSecondsRemaining,subscription);

@override
String toString() {
  return 'Device(id: $id, label: $label, serial: $serial, imageUrl: $imageUrl, address: $address, status: $status, role: $role, canOpen: $canOpen, suspensionReason: $suspensionReason, latitude: $latitude, longitude: $longitude, geofenceEnabled: $geofenceEnabled, geofenceRadiusM: $geofenceRadiusM, lastOnlineAt: $lastOnlineAt, model: $model, cooldownSecondsRemaining: $cooldownSecondsRemaining, subscription: $subscription)';
}


}

/// @nodoc
abstract mixin class $DeviceCopyWith<$Res>  {
  factory $DeviceCopyWith(Device value, $Res Function(Device) _then) = _$DeviceCopyWithImpl;
@useResult
$Res call({
 int id, String label, String? serial, String? imageUrl, String? address, String status, String role, bool canOpen, String suspensionReason, double? latitude, double? longitude, bool geofenceEnabled, int? geofenceRadiusM, DateTime? lastOnlineAt, DeviceModel? model, int? cooldownSecondsRemaining, DeviceSubscription? subscription
});


$DeviceModelCopyWith<$Res>? get model;$DeviceSubscriptionCopyWith<$Res>? get subscription;

}
/// @nodoc
class _$DeviceCopyWithImpl<$Res>
    implements $DeviceCopyWith<$Res> {
  _$DeviceCopyWithImpl(this._self, this._then);

  final Device _self;
  final $Res Function(Device) _then;

/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? label = null,Object? serial = freezed,Object? imageUrl = freezed,Object? address = freezed,Object? status = null,Object? role = null,Object? canOpen = null,Object? suspensionReason = null,Object? latitude = freezed,Object? longitude = freezed,Object? geofenceEnabled = null,Object? geofenceRadiusM = freezed,Object? lastOnlineAt = freezed,Object? model = freezed,Object? cooldownSecondsRemaining = freezed,Object? subscription = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,label: null == label ? _self.label : label // ignore: cast_nullable_to_non_nullable
as String,serial: freezed == serial ? _self.serial : serial // ignore: cast_nullable_to_non_nullable
as String?,imageUrl: freezed == imageUrl ? _self.imageUrl : imageUrl // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,role: null == role ? _self.role : role // ignore: cast_nullable_to_non_nullable
as String,canOpen: null == canOpen ? _self.canOpen : canOpen // ignore: cast_nullable_to_non_nullable
as bool,suspensionReason: null == suspensionReason ? _self.suspensionReason : suspensionReason // ignore: cast_nullable_to_non_nullable
as String,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,geofenceEnabled: null == geofenceEnabled ? _self.geofenceEnabled : geofenceEnabled // ignore: cast_nullable_to_non_nullable
as bool,geofenceRadiusM: freezed == geofenceRadiusM ? _self.geofenceRadiusM : geofenceRadiusM // ignore: cast_nullable_to_non_nullable
as int?,lastOnlineAt: freezed == lastOnlineAt ? _self.lastOnlineAt : lastOnlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,model: freezed == model ? _self.model : model // ignore: cast_nullable_to_non_nullable
as DeviceModel?,cooldownSecondsRemaining: freezed == cooldownSecondsRemaining ? _self.cooldownSecondsRemaining : cooldownSecondsRemaining // ignore: cast_nullable_to_non_nullable
as int?,subscription: freezed == subscription ? _self.subscription : subscription // ignore: cast_nullable_to_non_nullable
as DeviceSubscription?,
  ));
}
/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceModelCopyWith<$Res>? get model {
    if (_self.model == null) {
    return null;
  }

  return $DeviceModelCopyWith<$Res>(_self.model!, (value) {
    return _then(_self.copyWith(model: value));
  });
}/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceSubscriptionCopyWith<$Res>? get subscription {
    if (_self.subscription == null) {
    return null;
  }

  return $DeviceSubscriptionCopyWith<$Res>(_self.subscription!, (value) {
    return _then(_self.copyWith(subscription: value));
  });
}
}


/// Adds pattern-matching-related methods to [Device].
extension DevicePatterns on Device {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Device value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Device() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Device value)  $default,){
final _that = this;
switch (_that) {
case _Device():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Device value)?  $default,){
final _that = this;
switch (_that) {
case _Device() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String label,  String? serial,  String? imageUrl,  String? address,  String status,  String role,  bool canOpen,  String suspensionReason,  double? latitude,  double? longitude,  bool geofenceEnabled,  int? geofenceRadiusM,  DateTime? lastOnlineAt,  DeviceModel? model,  int? cooldownSecondsRemaining,  DeviceSubscription? subscription)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Device() when $default != null:
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.geofenceEnabled,_that.geofenceRadiusM,_that.lastOnlineAt,_that.model,_that.cooldownSecondsRemaining,_that.subscription);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String label,  String? serial,  String? imageUrl,  String? address,  String status,  String role,  bool canOpen,  String suspensionReason,  double? latitude,  double? longitude,  bool geofenceEnabled,  int? geofenceRadiusM,  DateTime? lastOnlineAt,  DeviceModel? model,  int? cooldownSecondsRemaining,  DeviceSubscription? subscription)  $default,) {final _that = this;
switch (_that) {
case _Device():
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.geofenceEnabled,_that.geofenceRadiusM,_that.lastOnlineAt,_that.model,_that.cooldownSecondsRemaining,_that.subscription);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String label,  String? serial,  String? imageUrl,  String? address,  String status,  String role,  bool canOpen,  String suspensionReason,  double? latitude,  double? longitude,  bool geofenceEnabled,  int? geofenceRadiusM,  DateTime? lastOnlineAt,  DeviceModel? model,  int? cooldownSecondsRemaining,  DeviceSubscription? subscription)?  $default,) {final _that = this;
switch (_that) {
case _Device() when $default != null:
return $default(_that.id,_that.label,_that.serial,_that.imageUrl,_that.address,_that.status,_that.role,_that.canOpen,_that.suspensionReason,_that.latitude,_that.longitude,_that.geofenceEnabled,_that.geofenceRadiusM,_that.lastOnlineAt,_that.model,_that.cooldownSecondsRemaining,_that.subscription);case _:
  return null;

}
}

}

/// @nodoc


class _Device extends Device {
  const _Device({required this.id, required this.label, this.serial, this.imageUrl, this.address, this.status = 'unknown', this.role = 'user', this.canOpen = false, this.suspensionReason = 'none', this.latitude, this.longitude, this.geofenceEnabled = false, this.geofenceRadiusM, this.lastOnlineAt, this.model, this.cooldownSecondsRemaining, this.subscription}): super._();
  

@override final  int id;
@override final  String label;
@override final  String? serial;
@override final  String? imageUrl;
@override final  String? address;
@override@JsonKey() final  String status;
@override@JsonKey() final  String role;
@override@JsonKey() final  bool canOpen;
@override@JsonKey() final  String suspensionReason;
@override final  double? latitude;
@override final  double? longitude;
@override@JsonKey() final  bool geofenceEnabled;
@override final  int? geofenceRadiusM;
@override final  DateTime? lastOnlineAt;
@override final  DeviceModel? model;
@override final  int? cooldownSecondsRemaining;
@override final  DeviceSubscription? subscription;

/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeviceCopyWith<_Device> get copyWith => __$DeviceCopyWithImpl<_Device>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Device&&(identical(other.id, id) || other.id == id)&&(identical(other.label, label) || other.label == label)&&(identical(other.serial, serial) || other.serial == serial)&&(identical(other.imageUrl, imageUrl) || other.imageUrl == imageUrl)&&(identical(other.address, address) || other.address == address)&&(identical(other.status, status) || other.status == status)&&(identical(other.role, role) || other.role == role)&&(identical(other.canOpen, canOpen) || other.canOpen == canOpen)&&(identical(other.suspensionReason, suspensionReason) || other.suspensionReason == suspensionReason)&&(identical(other.latitude, latitude) || other.latitude == latitude)&&(identical(other.longitude, longitude) || other.longitude == longitude)&&(identical(other.geofenceEnabled, geofenceEnabled) || other.geofenceEnabled == geofenceEnabled)&&(identical(other.geofenceRadiusM, geofenceRadiusM) || other.geofenceRadiusM == geofenceRadiusM)&&(identical(other.lastOnlineAt, lastOnlineAt) || other.lastOnlineAt == lastOnlineAt)&&(identical(other.model, model) || other.model == model)&&(identical(other.cooldownSecondsRemaining, cooldownSecondsRemaining) || other.cooldownSecondsRemaining == cooldownSecondsRemaining)&&(identical(other.subscription, subscription) || other.subscription == subscription));
}


@override
int get hashCode => Object.hash(runtimeType,id,label,serial,imageUrl,address,status,role,canOpen,suspensionReason,latitude,longitude,geofenceEnabled,geofenceRadiusM,lastOnlineAt,model,cooldownSecondsRemaining,subscription);

@override
String toString() {
  return 'Device(id: $id, label: $label, serial: $serial, imageUrl: $imageUrl, address: $address, status: $status, role: $role, canOpen: $canOpen, suspensionReason: $suspensionReason, latitude: $latitude, longitude: $longitude, geofenceEnabled: $geofenceEnabled, geofenceRadiusM: $geofenceRadiusM, lastOnlineAt: $lastOnlineAt, model: $model, cooldownSecondsRemaining: $cooldownSecondsRemaining, subscription: $subscription)';
}


}

/// @nodoc
abstract mixin class _$DeviceCopyWith<$Res> implements $DeviceCopyWith<$Res> {
  factory _$DeviceCopyWith(_Device value, $Res Function(_Device) _then) = __$DeviceCopyWithImpl;
@override @useResult
$Res call({
 int id, String label, String? serial, String? imageUrl, String? address, String status, String role, bool canOpen, String suspensionReason, double? latitude, double? longitude, bool geofenceEnabled, int? geofenceRadiusM, DateTime? lastOnlineAt, DeviceModel? model, int? cooldownSecondsRemaining, DeviceSubscription? subscription
});


@override $DeviceModelCopyWith<$Res>? get model;@override $DeviceSubscriptionCopyWith<$Res>? get subscription;

}
/// @nodoc
class __$DeviceCopyWithImpl<$Res>
    implements _$DeviceCopyWith<$Res> {
  __$DeviceCopyWithImpl(this._self, this._then);

  final _Device _self;
  final $Res Function(_Device) _then;

/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? label = null,Object? serial = freezed,Object? imageUrl = freezed,Object? address = freezed,Object? status = null,Object? role = null,Object? canOpen = null,Object? suspensionReason = null,Object? latitude = freezed,Object? longitude = freezed,Object? geofenceEnabled = null,Object? geofenceRadiusM = freezed,Object? lastOnlineAt = freezed,Object? model = freezed,Object? cooldownSecondsRemaining = freezed,Object? subscription = freezed,}) {
  return _then(_Device(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,label: null == label ? _self.label : label // ignore: cast_nullable_to_non_nullable
as String,serial: freezed == serial ? _self.serial : serial // ignore: cast_nullable_to_non_nullable
as String?,imageUrl: freezed == imageUrl ? _self.imageUrl : imageUrl // ignore: cast_nullable_to_non_nullable
as String?,address: freezed == address ? _self.address : address // ignore: cast_nullable_to_non_nullable
as String?,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,role: null == role ? _self.role : role // ignore: cast_nullable_to_non_nullable
as String,canOpen: null == canOpen ? _self.canOpen : canOpen // ignore: cast_nullable_to_non_nullable
as bool,suspensionReason: null == suspensionReason ? _self.suspensionReason : suspensionReason // ignore: cast_nullable_to_non_nullable
as String,latitude: freezed == latitude ? _self.latitude : latitude // ignore: cast_nullable_to_non_nullable
as double?,longitude: freezed == longitude ? _self.longitude : longitude // ignore: cast_nullable_to_non_nullable
as double?,geofenceEnabled: null == geofenceEnabled ? _self.geofenceEnabled : geofenceEnabled // ignore: cast_nullable_to_non_nullable
as bool,geofenceRadiusM: freezed == geofenceRadiusM ? _self.geofenceRadiusM : geofenceRadiusM // ignore: cast_nullable_to_non_nullable
as int?,lastOnlineAt: freezed == lastOnlineAt ? _self.lastOnlineAt : lastOnlineAt // ignore: cast_nullable_to_non_nullable
as DateTime?,model: freezed == model ? _self.model : model // ignore: cast_nullable_to_non_nullable
as DeviceModel?,cooldownSecondsRemaining: freezed == cooldownSecondsRemaining ? _self.cooldownSecondsRemaining : cooldownSecondsRemaining // ignore: cast_nullable_to_non_nullable
as int?,subscription: freezed == subscription ? _self.subscription : subscription // ignore: cast_nullable_to_non_nullable
as DeviceSubscription?,
  ));
}

/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceModelCopyWith<$Res>? get model {
    if (_self.model == null) {
    return null;
  }

  return $DeviceModelCopyWith<$Res>(_self.model!, (value) {
    return _then(_self.copyWith(model: value));
  });
}/// Create a copy of Device
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$DeviceSubscriptionCopyWith<$Res>? get subscription {
    if (_self.subscription == null) {
    return null;
  }

  return $DeviceSubscriptionCopyWith<$Res>(_self.subscription!, (value) {
    return _then(_self.copyWith(subscription: value));
  });
}
}

/// @nodoc
mixin _$DeviceModel {

 String? get vendor; String? get modelCode;
/// Create a copy of DeviceModel
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeviceModelCopyWith<DeviceModel> get copyWith => _$DeviceModelCopyWithImpl<DeviceModel>(this as DeviceModel, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DeviceModel&&(identical(other.vendor, vendor) || other.vendor == vendor)&&(identical(other.modelCode, modelCode) || other.modelCode == modelCode));
}


@override
int get hashCode => Object.hash(runtimeType,vendor,modelCode);

@override
String toString() {
  return 'DeviceModel(vendor: $vendor, modelCode: $modelCode)';
}


}

/// @nodoc
abstract mixin class $DeviceModelCopyWith<$Res>  {
  factory $DeviceModelCopyWith(DeviceModel value, $Res Function(DeviceModel) _then) = _$DeviceModelCopyWithImpl;
@useResult
$Res call({
 String? vendor, String? modelCode
});




}
/// @nodoc
class _$DeviceModelCopyWithImpl<$Res>
    implements $DeviceModelCopyWith<$Res> {
  _$DeviceModelCopyWithImpl(this._self, this._then);

  final DeviceModel _self;
  final $Res Function(DeviceModel) _then;

/// Create a copy of DeviceModel
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? vendor = freezed,Object? modelCode = freezed,}) {
  return _then(_self.copyWith(
vendor: freezed == vendor ? _self.vendor : vendor // ignore: cast_nullable_to_non_nullable
as String?,modelCode: freezed == modelCode ? _self.modelCode : modelCode // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [DeviceModel].
extension DeviceModelPatterns on DeviceModel {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DeviceModel value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DeviceModel() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DeviceModel value)  $default,){
final _that = this;
switch (_that) {
case _DeviceModel():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DeviceModel value)?  $default,){
final _that = this;
switch (_that) {
case _DeviceModel() when $default != null:
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
case _DeviceModel() when $default != null:
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
case _DeviceModel():
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
case _DeviceModel() when $default != null:
return $default(_that.vendor,_that.modelCode);case _:
  return null;

}
}

}

/// @nodoc


class _DeviceModel implements DeviceModel {
  const _DeviceModel({this.vendor, this.modelCode});
  

@override final  String? vendor;
@override final  String? modelCode;

/// Create a copy of DeviceModel
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeviceModelCopyWith<_DeviceModel> get copyWith => __$DeviceModelCopyWithImpl<_DeviceModel>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DeviceModel&&(identical(other.vendor, vendor) || other.vendor == vendor)&&(identical(other.modelCode, modelCode) || other.modelCode == modelCode));
}


@override
int get hashCode => Object.hash(runtimeType,vendor,modelCode);

@override
String toString() {
  return 'DeviceModel(vendor: $vendor, modelCode: $modelCode)';
}


}

/// @nodoc
abstract mixin class _$DeviceModelCopyWith<$Res> implements $DeviceModelCopyWith<$Res> {
  factory _$DeviceModelCopyWith(_DeviceModel value, $Res Function(_DeviceModel) _then) = __$DeviceModelCopyWithImpl;
@override @useResult
$Res call({
 String? vendor, String? modelCode
});




}
/// @nodoc
class __$DeviceModelCopyWithImpl<$Res>
    implements _$DeviceModelCopyWith<$Res> {
  __$DeviceModelCopyWithImpl(this._self, this._then);

  final _DeviceModel _self;
  final $Res Function(_DeviceModel) _then;

/// Create a copy of DeviceModel
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? vendor = freezed,Object? modelCode = freezed,}) {
  return _then(_DeviceModel(
vendor: freezed == vendor ? _self.vendor : vendor // ignore: cast_nullable_to_non_nullable
as String?,modelCode: freezed == modelCode ? _self.modelCode : modelCode // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

/// @nodoc
mixin _$DeviceSubscription {

 int? get id; String? get tier; String? get status; DateTime? get endsAt; int? get daysRemaining; bool get autoRenew;
/// Create a copy of DeviceSubscription
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DeviceSubscriptionCopyWith<DeviceSubscription> get copyWith => _$DeviceSubscriptionCopyWithImpl<DeviceSubscription>(this as DeviceSubscription, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DeviceSubscription&&(identical(other.id, id) || other.id == id)&&(identical(other.tier, tier) || other.tier == tier)&&(identical(other.status, status) || other.status == status)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.daysRemaining, daysRemaining) || other.daysRemaining == daysRemaining)&&(identical(other.autoRenew, autoRenew) || other.autoRenew == autoRenew));
}


@override
int get hashCode => Object.hash(runtimeType,id,tier,status,endsAt,daysRemaining,autoRenew);

@override
String toString() {
  return 'DeviceSubscription(id: $id, tier: $tier, status: $status, endsAt: $endsAt, daysRemaining: $daysRemaining, autoRenew: $autoRenew)';
}


}

/// @nodoc
abstract mixin class $DeviceSubscriptionCopyWith<$Res>  {
  factory $DeviceSubscriptionCopyWith(DeviceSubscription value, $Res Function(DeviceSubscription) _then) = _$DeviceSubscriptionCopyWithImpl;
@useResult
$Res call({
 int? id, String? tier, String? status, DateTime? endsAt, int? daysRemaining, bool autoRenew
});




}
/// @nodoc
class _$DeviceSubscriptionCopyWithImpl<$Res>
    implements $DeviceSubscriptionCopyWith<$Res> {
  _$DeviceSubscriptionCopyWithImpl(this._self, this._then);

  final DeviceSubscription _self;
  final $Res Function(DeviceSubscription) _then;

/// Create a copy of DeviceSubscription
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = freezed,Object? tier = freezed,Object? status = freezed,Object? endsAt = freezed,Object? daysRemaining = freezed,Object? autoRenew = null,}) {
  return _then(_self.copyWith(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,tier: freezed == tier ? _self.tier : tier // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,endsAt: freezed == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime?,daysRemaining: freezed == daysRemaining ? _self.daysRemaining : daysRemaining // ignore: cast_nullable_to_non_nullable
as int?,autoRenew: null == autoRenew ? _self.autoRenew : autoRenew // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [DeviceSubscription].
extension DeviceSubscriptionPatterns on DeviceSubscription {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DeviceSubscription value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DeviceSubscription() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DeviceSubscription value)  $default,){
final _that = this;
switch (_that) {
case _DeviceSubscription():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DeviceSubscription value)?  $default,){
final _that = this;
switch (_that) {
case _DeviceSubscription() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int? id,  String? tier,  String? status,  DateTime? endsAt,  int? daysRemaining,  bool autoRenew)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DeviceSubscription() when $default != null:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int? id,  String? tier,  String? status,  DateTime? endsAt,  int? daysRemaining,  bool autoRenew)  $default,) {final _that = this;
switch (_that) {
case _DeviceSubscription():
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int? id,  String? tier,  String? status,  DateTime? endsAt,  int? daysRemaining,  bool autoRenew)?  $default,) {final _that = this;
switch (_that) {
case _DeviceSubscription() when $default != null:
return $default(_that.id,_that.tier,_that.status,_that.endsAt,_that.daysRemaining,_that.autoRenew);case _:
  return null;

}
}

}

/// @nodoc


class _DeviceSubscription implements DeviceSubscription {
  const _DeviceSubscription({this.id, this.tier, this.status, this.endsAt, this.daysRemaining, this.autoRenew = false});
  

@override final  int? id;
@override final  String? tier;
@override final  String? status;
@override final  DateTime? endsAt;
@override final  int? daysRemaining;
@override@JsonKey() final  bool autoRenew;

/// Create a copy of DeviceSubscription
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DeviceSubscriptionCopyWith<_DeviceSubscription> get copyWith => __$DeviceSubscriptionCopyWithImpl<_DeviceSubscription>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DeviceSubscription&&(identical(other.id, id) || other.id == id)&&(identical(other.tier, tier) || other.tier == tier)&&(identical(other.status, status) || other.status == status)&&(identical(other.endsAt, endsAt) || other.endsAt == endsAt)&&(identical(other.daysRemaining, daysRemaining) || other.daysRemaining == daysRemaining)&&(identical(other.autoRenew, autoRenew) || other.autoRenew == autoRenew));
}


@override
int get hashCode => Object.hash(runtimeType,id,tier,status,endsAt,daysRemaining,autoRenew);

@override
String toString() {
  return 'DeviceSubscription(id: $id, tier: $tier, status: $status, endsAt: $endsAt, daysRemaining: $daysRemaining, autoRenew: $autoRenew)';
}


}

/// @nodoc
abstract mixin class _$DeviceSubscriptionCopyWith<$Res> implements $DeviceSubscriptionCopyWith<$Res> {
  factory _$DeviceSubscriptionCopyWith(_DeviceSubscription value, $Res Function(_DeviceSubscription) _then) = __$DeviceSubscriptionCopyWithImpl;
@override @useResult
$Res call({
 int? id, String? tier, String? status, DateTime? endsAt, int? daysRemaining, bool autoRenew
});




}
/// @nodoc
class __$DeviceSubscriptionCopyWithImpl<$Res>
    implements _$DeviceSubscriptionCopyWith<$Res> {
  __$DeviceSubscriptionCopyWithImpl(this._self, this._then);

  final _DeviceSubscription _self;
  final $Res Function(_DeviceSubscription) _then;

/// Create a copy of DeviceSubscription
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = freezed,Object? tier = freezed,Object? status = freezed,Object? endsAt = freezed,Object? daysRemaining = freezed,Object? autoRenew = null,}) {
  return _then(_DeviceSubscription(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,tier: freezed == tier ? _self.tier : tier // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,endsAt: freezed == endsAt ? _self.endsAt : endsAt // ignore: cast_nullable_to_non_nullable
as DateTime?,daysRemaining: freezed == daysRemaining ? _self.daysRemaining : daysRemaining // ignore: cast_nullable_to_non_nullable
as int?,autoRenew: null == autoRenew ? _self.autoRenew : autoRenew // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

/// @nodoc
mixin _$DevicePage {

 List<Device> get devices; String? get nextCursor; bool get hasMore;
/// Create a copy of DevicePage
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$DevicePageCopyWith<DevicePage> get copyWith => _$DevicePageCopyWithImpl<DevicePage>(this as DevicePage, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is DevicePage&&const DeepCollectionEquality().equals(other.devices, devices)&&(identical(other.nextCursor, nextCursor) || other.nextCursor == nextCursor)&&(identical(other.hasMore, hasMore) || other.hasMore == hasMore));
}


@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(devices),nextCursor,hasMore);

@override
String toString() {
  return 'DevicePage(devices: $devices, nextCursor: $nextCursor, hasMore: $hasMore)';
}


}

/// @nodoc
abstract mixin class $DevicePageCopyWith<$Res>  {
  factory $DevicePageCopyWith(DevicePage value, $Res Function(DevicePage) _then) = _$DevicePageCopyWithImpl;
@useResult
$Res call({
 List<Device> devices, String? nextCursor, bool hasMore
});




}
/// @nodoc
class _$DevicePageCopyWithImpl<$Res>
    implements $DevicePageCopyWith<$Res> {
  _$DevicePageCopyWithImpl(this._self, this._then);

  final DevicePage _self;
  final $Res Function(DevicePage) _then;

/// Create a copy of DevicePage
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? devices = null,Object? nextCursor = freezed,Object? hasMore = null,}) {
  return _then(_self.copyWith(
devices: null == devices ? _self.devices : devices // ignore: cast_nullable_to_non_nullable
as List<Device>,nextCursor: freezed == nextCursor ? _self.nextCursor : nextCursor // ignore: cast_nullable_to_non_nullable
as String?,hasMore: null == hasMore ? _self.hasMore : hasMore // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}

}


/// Adds pattern-matching-related methods to [DevicePage].
extension DevicePagePatterns on DevicePage {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _DevicePage value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _DevicePage() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _DevicePage value)  $default,){
final _that = this;
switch (_that) {
case _DevicePage():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _DevicePage value)?  $default,){
final _that = this;
switch (_that) {
case _DevicePage() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( List<Device> devices,  String? nextCursor,  bool hasMore)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _DevicePage() when $default != null:
return $default(_that.devices,_that.nextCursor,_that.hasMore);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( List<Device> devices,  String? nextCursor,  bool hasMore)  $default,) {final _that = this;
switch (_that) {
case _DevicePage():
return $default(_that.devices,_that.nextCursor,_that.hasMore);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( List<Device> devices,  String? nextCursor,  bool hasMore)?  $default,) {final _that = this;
switch (_that) {
case _DevicePage() when $default != null:
return $default(_that.devices,_that.nextCursor,_that.hasMore);case _:
  return null;

}
}

}

/// @nodoc


class _DevicePage implements DevicePage {
  const _DevicePage({required final  List<Device> devices, this.nextCursor, this.hasMore = false}): _devices = devices;
  

 final  List<Device> _devices;
@override List<Device> get devices {
  if (_devices is EqualUnmodifiableListView) return _devices;
  // ignore: implicit_dynamic_type
  return EqualUnmodifiableListView(_devices);
}

@override final  String? nextCursor;
@override@JsonKey() final  bool hasMore;

/// Create a copy of DevicePage
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$DevicePageCopyWith<_DevicePage> get copyWith => __$DevicePageCopyWithImpl<_DevicePage>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _DevicePage&&const DeepCollectionEquality().equals(other._devices, _devices)&&(identical(other.nextCursor, nextCursor) || other.nextCursor == nextCursor)&&(identical(other.hasMore, hasMore) || other.hasMore == hasMore));
}


@override
int get hashCode => Object.hash(runtimeType,const DeepCollectionEquality().hash(_devices),nextCursor,hasMore);

@override
String toString() {
  return 'DevicePage(devices: $devices, nextCursor: $nextCursor, hasMore: $hasMore)';
}


}

/// @nodoc
abstract mixin class _$DevicePageCopyWith<$Res> implements $DevicePageCopyWith<$Res> {
  factory _$DevicePageCopyWith(_DevicePage value, $Res Function(_DevicePage) _then) = __$DevicePageCopyWithImpl;
@override @useResult
$Res call({
 List<Device> devices, String? nextCursor, bool hasMore
});




}
/// @nodoc
class __$DevicePageCopyWithImpl<$Res>
    implements _$DevicePageCopyWith<$Res> {
  __$DevicePageCopyWithImpl(this._self, this._then);

  final _DevicePage _self;
  final $Res Function(_DevicePage) _then;

/// Create a copy of DevicePage
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? devices = null,Object? nextCursor = freezed,Object? hasMore = null,}) {
  return _then(_DevicePage(
devices: null == devices ? _self._devices : devices // ignore: cast_nullable_to_non_nullable
as List<Device>,nextCursor: freezed == nextCursor ? _self.nextCursor : nextCursor // ignore: cast_nullable_to_non_nullable
as String?,hasMore: null == hasMore ? _self.hasMore : hasMore // ignore: cast_nullable_to_non_nullable
as bool,
  ));
}


}

// dart format on
