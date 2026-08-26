// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'invitation_entities.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$Invitation {

 int get id; String? get visitorName; String? get purpose; String get accessType; String get status; DateTime? get expiresAt; int? get maxUsage; int get usageCount; DateTime? get firstUsedAt; DateTime? get lastUsedAt; DateTime? get revokedAt; DateTime? get createdAt;
/// Create a copy of Invitation
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$InvitationCopyWith<Invitation> get copyWith => _$InvitationCopyWithImpl<Invitation>(this as Invitation, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is Invitation&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.maxUsage, maxUsage) || other.maxUsage == maxUsage)&&(identical(other.usageCount, usageCount) || other.usageCount == usageCount)&&(identical(other.firstUsedAt, firstUsedAt) || other.firstUsedAt == firstUsedAt)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.revokedAt, revokedAt) || other.revokedAt == revokedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}


@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt,maxUsage,usageCount,firstUsedAt,lastUsedAt,revokedAt,createdAt);

@override
String toString() {
  return 'Invitation(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt, maxUsage: $maxUsage, usageCount: $usageCount, firstUsedAt: $firstUsedAt, lastUsedAt: $lastUsedAt, revokedAt: $revokedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class $InvitationCopyWith<$Res>  {
  factory $InvitationCopyWith(Invitation value, $Res Function(Invitation) _then) = _$InvitationCopyWithImpl;
@useResult
$Res call({
 int id, String? visitorName, String? purpose, String accessType, String status, DateTime? expiresAt, int? maxUsage, int usageCount, DateTime? firstUsedAt, DateTime? lastUsedAt, DateTime? revokedAt, DateTime? createdAt
});




}
/// @nodoc
class _$InvitationCopyWithImpl<$Res>
    implements $InvitationCopyWith<$Res> {
  _$InvitationCopyWithImpl(this._self, this._then);

  final Invitation _self;
  final $Res Function(Invitation) _then;

/// Create a copy of Invitation
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = null,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = null,Object? status = null,Object? expiresAt = freezed,Object? maxUsage = freezed,Object? usageCount = null,Object? firstUsedAt = freezed,Object? lastUsedAt = freezed,Object? revokedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_self.copyWith(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: null == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime?,maxUsage: freezed == maxUsage ? _self.maxUsage : maxUsage // ignore: cast_nullable_to_non_nullable
as int?,usageCount: null == usageCount ? _self.usageCount : usageCount // ignore: cast_nullable_to_non_nullable
as int,firstUsedAt: freezed == firstUsedAt ? _self.firstUsedAt : firstUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,revokedAt: freezed == revokedAt ? _self.revokedAt : revokedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

}


/// Adds pattern-matching-related methods to [Invitation].
extension InvitationPatterns on Invitation {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _Invitation value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _Invitation() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _Invitation value)  $default,){
final _that = this;
switch (_that) {
case _Invitation():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _Invitation value)?  $default,){
final _that = this;
switch (_that) {
case _Invitation() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int id,  String? visitorName,  String? purpose,  String accessType,  String status,  DateTime? expiresAt,  int? maxUsage,  int usageCount,  DateTime? firstUsedAt,  DateTime? lastUsedAt,  DateTime? revokedAt,  DateTime? createdAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _Invitation() when $default != null:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int id,  String? visitorName,  String? purpose,  String accessType,  String status,  DateTime? expiresAt,  int? maxUsage,  int usageCount,  DateTime? firstUsedAt,  DateTime? lastUsedAt,  DateTime? revokedAt,  DateTime? createdAt)  $default,) {final _that = this;
switch (_that) {
case _Invitation():
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int id,  String? visitorName,  String? purpose,  String accessType,  String status,  DateTime? expiresAt,  int? maxUsage,  int usageCount,  DateTime? firstUsedAt,  DateTime? lastUsedAt,  DateTime? revokedAt,  DateTime? createdAt)?  $default,) {final _that = this;
switch (_that) {
case _Invitation() when $default != null:
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt,_that.maxUsage,_that.usageCount,_that.firstUsedAt,_that.lastUsedAt,_that.revokedAt,_that.createdAt);case _:
  return null;

}
}

}

/// @nodoc


class _Invitation extends Invitation {
  const _Invitation({required this.id, this.visitorName, this.purpose, this.accessType = 'time_limited', this.status = 'active', this.expiresAt, this.maxUsage, this.usageCount = 0, this.firstUsedAt, this.lastUsedAt, this.revokedAt, this.createdAt}): super._();
  

@override final  int id;
@override final  String? visitorName;
@override final  String? purpose;
@override@JsonKey() final  String accessType;
@override@JsonKey() final  String status;
@override final  DateTime? expiresAt;
@override final  int? maxUsage;
@override@JsonKey() final  int usageCount;
@override final  DateTime? firstUsedAt;
@override final  DateTime? lastUsedAt;
@override final  DateTime? revokedAt;
@override final  DateTime? createdAt;

/// Create a copy of Invitation
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$InvitationCopyWith<_Invitation> get copyWith => __$InvitationCopyWithImpl<_Invitation>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _Invitation&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt)&&(identical(other.maxUsage, maxUsage) || other.maxUsage == maxUsage)&&(identical(other.usageCount, usageCount) || other.usageCount == usageCount)&&(identical(other.firstUsedAt, firstUsedAt) || other.firstUsedAt == firstUsedAt)&&(identical(other.lastUsedAt, lastUsedAt) || other.lastUsedAt == lastUsedAt)&&(identical(other.revokedAt, revokedAt) || other.revokedAt == revokedAt)&&(identical(other.createdAt, createdAt) || other.createdAt == createdAt));
}


@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt,maxUsage,usageCount,firstUsedAt,lastUsedAt,revokedAt,createdAt);

@override
String toString() {
  return 'Invitation(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt, maxUsage: $maxUsage, usageCount: $usageCount, firstUsedAt: $firstUsedAt, lastUsedAt: $lastUsedAt, revokedAt: $revokedAt, createdAt: $createdAt)';
}


}

/// @nodoc
abstract mixin class _$InvitationCopyWith<$Res> implements $InvitationCopyWith<$Res> {
  factory _$InvitationCopyWith(_Invitation value, $Res Function(_Invitation) _then) = __$InvitationCopyWithImpl;
@override @useResult
$Res call({
 int id, String? visitorName, String? purpose, String accessType, String status, DateTime? expiresAt, int? maxUsage, int usageCount, DateTime? firstUsedAt, DateTime? lastUsedAt, DateTime? revokedAt, DateTime? createdAt
});




}
/// @nodoc
class __$InvitationCopyWithImpl<$Res>
    implements _$InvitationCopyWith<$Res> {
  __$InvitationCopyWithImpl(this._self, this._then);

  final _Invitation _self;
  final $Res Function(_Invitation) _then;

/// Create a copy of Invitation
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = null,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = null,Object? status = null,Object? expiresAt = freezed,Object? maxUsage = freezed,Object? usageCount = null,Object? firstUsedAt = freezed,Object? lastUsedAt = freezed,Object? revokedAt = freezed,Object? createdAt = freezed,}) {
  return _then(_Invitation(
id: null == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: null == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String,status: null == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as DateTime?,maxUsage: freezed == maxUsage ? _self.maxUsage : maxUsage // ignore: cast_nullable_to_non_nullable
as int?,usageCount: null == usageCount ? _self.usageCount : usageCount // ignore: cast_nullable_to_non_nullable
as int,firstUsedAt: freezed == firstUsedAt ? _self.firstUsedAt : firstUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,lastUsedAt: freezed == lastUsedAt ? _self.lastUsedAt : lastUsedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,revokedAt: freezed == revokedAt ? _self.revokedAt : revokedAt // ignore: cast_nullable_to_non_nullable
as DateTime?,createdAt: freezed == createdAt ? _self.createdAt : createdAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}


}

// dart format on
