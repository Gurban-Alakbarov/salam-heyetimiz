// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'invitation_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$InvitationDto {

 int get id; String? get visitorName; String? get purpose; String? get accessType; String? get status; String? get expiresAt; int? get maxUsage; int? get usageCount; String? get firstUsedAt; String? get lastUsedAt; String? get revokedAt; String? get createdAt;
/// Create a copy of InvitationDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$InvitationDtoCopyWith<InvitationDto> get copyWith => _$InvitationDtoCopyWithImpl<InvitationDto>(this as InvitationDto, _$identity);

  /// Serializes this InvitationDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is InvitationDto&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.maxUsage, maxUsage) || other.maxUsage == maxUsage)&&(identical(other.usageCount, usageCount) || other.usageCount == usageCount)&&(identical(other.firstUsedAt, firstUsedAt) || other.firstUsedAt == firstUsedAt)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.revokedAt, revokedAt) || other.revokedAt == revokedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt,maxUsage,usageCount,firstUsedAt,lastUsedAt,revokedAt,createdAt);

@override
String toString() {
  return 'InvitationDto(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt, maxUsage: $maxUsage, usageCount: $usageCount, firstUsedAt: $firstUsedAt, lastUsedAt: $lastUsedAt, revokedAt: $revokedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $InvitationDtoCopyWith<$Res>  {
  factory $InvitationDtoCopyWith(InvitationDto value, $Res Function(InvitationDto) _then) = _$InvitationDtoCopyWithImpl;
@useResult
$Res call({
 int id, String? visitorName, String? purpose, String? accessType, String? status, String? expiresAt, int? maxUsage, int? usageCount, String? firstUsedAt, String? lastUsedAt, String? revokedAt, String? createdAt
});




}
/// @nodoc
class _$InvitationDtoCopyWithImpl<$Res>
    implements $InvitationDtoCopyWith<$Res> {
  _$InvitationDtoCopyWithImpl(this._self, this._then);

  final InvitationDto _self;
  final $Res Function(InvitationDto) _then;

/// Create a copy of InvitationDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = freezed,Object? status = freezed,Object? expiresAt = freezed,Object? maxUsage = freezed,Object? usageCount = freezed,Object? firstUsedAt = freezed,Object? lastUsedAt = freezed,Object? revokedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: freezed == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as String?,maxUsage: freezed == maxUsage ? _self.maxUsage : maxUsage // ignore: cast_nullable_to_non_nullable
as int?,usageCount: freezed == usageCount ? _self.usageCount : usageCount // ignore: cast_nullable_to_non_nullable
as int?,firstUsedAt: freezed == firstUsedAt ? _self.firstUsedAt : firstUsedAt // ignore: cast_nullable_to_non_nullable
as String?,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as String?,revokedAt: freezed == revokedAt ? _self.revokedAt : revokedAt // ignore: cast_nullable_to_non_nullable
as String?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [InvitationDto].
extension InvitationDtoPatterns on InvitationDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _InvitationDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _InvitationDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _InvitationDto value)  $default,){
final _that = this;
switch (_that) {
case _InvitationDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _InvitationDto value)?  $default,){
final _that = this;
switch (_that) {
case _InvitationDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt,  int? maxUsage,  int? usageCount,  String? firstUsedAt,  String? lastUsedAt,  String? revokedAt,  String? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _InvitationDto() when $default != null:
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt,_that.maxUsage,_that.usageCount,_that.firstUsedAt,_that.lastUsedAt,_that.revokedAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt,  int? maxUsage,  int? usageCount,  String? firstUsedAt,  String? lastUsedAt,  String? revokedAt,  String? createdAt)  $default,) {final _that = this;
switch (_that) {
case _InvitationDto():
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt,_that.maxUsage,_that.usageCount,_that.firstUsedAt,_that.lastUsedAt,_that.revokedAt,_that.createdAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt,  int? maxUsage,  int? usageCount,  String? firstUsedAt,  String? lastUsedAt,  String? revokedAt,  String? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _InvitationDto() when $default != null:
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt,_that.maxUsage,_that.usageCount,_that.firstUsedAt,_that.lastUsedAt,_that.revokedAt,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _InvitationDto implements InvitationDto {
  const _InvitationDto({required this.id, this.visitorName, this.purpose, this.accessType, this.status, this.expiresAt, this.maxUsage, this.usageCount, this.firstUsedAt, this.lastUsedAt, this.revokedAt, this.createdAt});
  factory _InvitationDto.fromJson(Map<String, dynamic> json) => _$InvitationDtoFromJson(json);

@override final  int id;
@override final  String? visitorName;
@override final  String? purpose;
@override final  String? accessType;
@override final  String? status;
@override final  String? expiresAt;
@override final  int? maxUsage;
@override final  int? usageCount;
@override final  String? firstUsedAt;
@override final  String? lastUsedAt;
@override final  String? revokedAt;
@override final  String? createdAt;

/// Create a copy of InvitationDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$InvitationDtoCopyWith<_InvitationDto> get copyWith => __$InvitationDtoCopyWithImpl<_InvitationDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$InvitationDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _InvitationDto&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.maxUsage, maxUsage) || other.maxUsage == maxUsage)&&(identical(other.usageCount, usageCount) || other.usageCount == usageCount)&&(identical(other.firstUsedAt, firstUsedAt) || other.firstUsedAt == firstUsedAt)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.revokedAt, revokedAt) || other.revokedAt == revokedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt,maxUsage,usageCount,firstUsedAt,lastUsedAt,revokedAt,createdAt);

@override
String toString() {
  return 'InvitationDto(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt, maxUsage: $maxUsage, usageCount: $usageCount, firstUsedAt: $firstUsedAt, lastUsedAt: $lastUsedAt, revokedAt: $revokedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$InvitationDtoCopyWith<$Res> implements $InvitationDtoCopyWith<$Res> {
  factory _$InvitationDtoCopyWith(_InvitationDto value, $Res Function(_InvitationDto) _then) = __$InvitationDtoCopyWithImpl;
@override @useResult
$Res call({
 int id, String? visitorName, String? purpose, String? accessType, String? status, String? expiresAt, int? maxUsage, int? usageCount, String? firstUsedAt, String? lastUsedAt, String? revokedAt, String? createdAt
});




}
/// @nodoc
class __$InvitationDtoCopyWithImpl<$Res>
    implements _$InvitationDtoCopyWith<$Res> {
  __$InvitationDtoCopyWithImpl(this._self, this._then);

  final _InvitationDto _self;
  final $Res Function(_InvitationDto) _then;

/// Create a copy of InvitationDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = freezed,Object? status = freezed,Object? expiresAt = freezed,Object? maxUsage = freezed,Object? usageCount = freezed,Object? firstUsedAt = freezed,Object? lastUsedAt = freezed,Object? revokedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_InvitationDto(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: freezed == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as String?,maxUsage: freezed == maxUsage ? _self.maxUsage : maxUsage // ignore: cast_nullable_to_non_nullable
as int?,usageCount: freezed == usageCount ? _self.usageCount : usageCount // ignore: cast_nullable_to_non_nullable
as int?,firstUsedAt: freezed == firstUsedAt ? _self.firstUsedAt : firstUsedAt // ignore: cast_nullable_to_non_nullable
as String?,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as String?,revokedAt: freezed == revokedAt ? _self.revokedAt : revokedAt // ignore: cast_nullable_to_non_nullable
as String?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
