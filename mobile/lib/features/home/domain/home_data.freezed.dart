// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'home_data.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;
/// @nodoc
mixin _$HomeData {

 String? get fullName; String? get email; String? get phone; int get deviceCount; int get subscriptionCount; int get invitationCount; DateTime? get lastActivityAt;
/// Create a copy of HomeData
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$HomeDataCopyWith<HomeData> get copyWith => _$HomeDataCopyWithImpl<HomeData>(this as HomeData, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is HomeData&&(identical(other.fullName, fullName) || other.fullName == fullName)&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.deviceCount, deviceCount) || other.deviceCount == deviceCount)&&(identical(other.subscriptionCount, subscriptionCount) || other.subscriptionCount == subscriptionCount)&&(identical(other.invitationCount, invitationCount) || other.invitationCount == invitationCount)&&(identical(other.lastActivityAt, lastActivityAt) || other.lastActivityAt == lastActivityAt));
}


@override
int get hashCode => Object.hash(runtimeType,fullName,email,phone,deviceCount,subscriptionCount,invitationCount,lastActivityAt);

@override
String toString() {
  return 'HomeData(fullName: $fullName, email: $email, phone: $phone, deviceCount: $deviceCount, subscriptionCount: $subscriptionCount, invitationCount: $invitationCount, lastActivityAt: $lastActivityAt)';
}


}

/// @nodoc
abstract mixin class $HomeDataCopyWith<$Res>  {
  factory $HomeDataCopyWith(HomeData value, $Res Function(HomeData) _then) = _$HomeDataCopyWithImpl;
@useResult
$Res call({
 String? fullName, String? email, String? phone, int deviceCount, int subscriptionCount, int invitationCount, DateTime? lastActivityAt
});




}
/// @nodoc
class _$HomeDataCopyWithImpl<$Res>
    implements $HomeDataCopyWith<$Res> {
  _$HomeDataCopyWithImpl(this._self, this._then);

  final HomeData _self;
  final $Res Function(HomeData) _then;

/// Create a copy of HomeData
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? fullName = freezed,Object? email = freezed,Object? phone = freezed,Object? deviceCount = null,Object? subscriptionCount = null,Object? invitationCount = null,Object? lastActivityAt = freezed,}) {
  return _then(_self.copyWith(
fullName: freezed == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String?,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,deviceCount: null == deviceCount ? _self.deviceCount : deviceCount // ignore: cast_nullable_to_non_nullable
as int,subscriptionCount: null == subscriptionCount ? _self.subscriptionCount : subscriptionCount // ignore: cast_nullable_to_non_nullable
as int,invitationCount: null == invitationCount ? _self.invitationCount : invitationCount // ignore: cast_nullable_to_non_nullable
as int,lastActivityAt: freezed == lastActivityAt ? _self.lastActivityAt : lastActivityAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}

}


/// Adds pattern-matching-related methods to [HomeData].
extension HomeDataPatterns on HomeData {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _HomeData value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _HomeData() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _HomeData value)  $default,){
final _that = this;
switch (_that) {
case _HomeData():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _HomeData value)?  $default,){
final _that = this;
switch (_that) {
case _HomeData() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( String? fullName,  String? email,  String? phone,  int deviceCount,  int subscriptionCount,  int invitationCount,  DateTime? lastActivityAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _HomeData() when $default != null:
return $default(_that.fullName,_that.email,_that.phone,_that.deviceCount,_that.subscriptionCount,_that.invitationCount,_that.lastActivityAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( String? fullName,  String? email,  String? phone,  int deviceCount,  int subscriptionCount,  int invitationCount,  DateTime? lastActivityAt)  $default,) {final _that = this;
switch (_that) {
case _HomeData():
return $default(_that.fullName,_that.email,_that.phone,_that.deviceCount,_that.subscriptionCount,_that.invitationCount,_that.lastActivityAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( String? fullName,  String? email,  String? phone,  int deviceCount,  int subscriptionCount,  int invitationCount,  DateTime? lastActivityAt)?  $default,) {final _that = this;
switch (_that) {
case _HomeData() when $default != null:
return $default(_that.fullName,_that.email,_that.phone,_that.deviceCount,_that.subscriptionCount,_that.invitationCount,_that.lastActivityAt);case _:
  return null;

}
}

}

/// @nodoc


class _HomeData implements HomeData {
  const _HomeData({this.fullName, this.email, this.phone, this.deviceCount = 0, this.subscriptionCount = 0, this.invitationCount = 0, this.lastActivityAt});
  

@override final  String? fullName;
@override final  String? email;
@override final  String? phone;
@override@JsonKey() final  int deviceCount;
@override@JsonKey() final  int subscriptionCount;
@override@JsonKey() final  int invitationCount;
@override final  DateTime? lastActivityAt;

/// Create a copy of HomeData
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$HomeDataCopyWith<_HomeData> get copyWith => __$HomeDataCopyWithImpl<_HomeData>(this, _$identity);



@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _HomeData&&(identical(other.fullName, fullName) || other.fullName == fullName)&&(identical(other.email, email) || other.email == email)&&(identical(other.phone, phone) || other.phone == phone)&&(identical(other.deviceCount, deviceCount) || other.deviceCount == deviceCount)&&(identical(other.subscriptionCount, subscriptionCount) || other.subscriptionCount == subscriptionCount)&&(identical(other.invitationCount, invitationCount) || other.invitationCount == invitationCount)&&(identical(other.lastActivityAt, lastActivityAt) || other.lastActivityAt == lastActivityAt));
}


@override
int get hashCode => Object.hash(runtimeType,fullName,email,phone,deviceCount,subscriptionCount,invitationCount,lastActivityAt);

@override
String toString() {
  return 'HomeData(fullName: $fullName, email: $email, phone: $phone, deviceCount: $deviceCount, subscriptionCount: $subscriptionCount, invitationCount: $invitationCount, lastActivityAt: $lastActivityAt)';
}


}

/// @nodoc
abstract mixin class _$HomeDataCopyWith<$Res> implements $HomeDataCopyWith<$Res> {
  factory _$HomeDataCopyWith(_HomeData value, $Res Function(_HomeData) _then) = __$HomeDataCopyWithImpl;
@override @useResult
$Res call({
 String? fullName, String? email, String? phone, int deviceCount, int subscriptionCount, int invitationCount, DateTime? lastActivityAt
});




}
/// @nodoc
class __$HomeDataCopyWithImpl<$Res>
    implements _$HomeDataCopyWith<$Res> {
  __$HomeDataCopyWithImpl(this._self, this._then);

  final _HomeData _self;
  final $Res Function(_HomeData) _then;

/// Create a copy of HomeData
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? fullName = freezed,Object? email = freezed,Object? phone = freezed,Object? deviceCount = null,Object? subscriptionCount = null,Object? invitationCount = null,Object? lastActivityAt = freezed,}) {
  return _then(_HomeData(
fullName: freezed == fullName ? _self.fullName : fullName // ignore: cast_nullable_to_non_nullable
as String?,email: freezed == email ? _self.email : email // ignore: cast_nullable_to_non_nullable
as String?,phone: freezed == phone ? _self.phone : phone // ignore: cast_nullable_to_non_nullable
as String?,deviceCount: null == deviceCount ? _self.deviceCount : deviceCount // ignore: cast_nullable_to_non_nullable
as int,subscriptionCount: null == subscriptionCount ? _self.subscriptionCount : subscriptionCount // ignore: cast_nullable_to_non_nullable
as int,invitationCount: null == invitationCount ? _self.invitationCount : invitationCount // ignore: cast_nullable_to_non_nullable
as int,lastActivityAt: freezed == lastActivityAt ? _self.lastActivityAt : lastActivityAt // ignore: cast_nullable_to_non_nullable
as DateTime?,
  ));
}


}

// dart format on
