// GENERATED CODE - DO NOT MODIFY BY HAND
// coverage:ignore-file
// ignore_for_file: type=lint
// ignore_for_file: unused_element, deprecated_member_use, deprecated_member_use_from_same_package, use_function_type_syntax_for_parameters, unnecessary_const, avoid_init_to_null, invalid_override_different_default_values_named, prefer_expression_function_bodies, annotate_overrides, invalid_annotation_target, unnecessary_question_mark

part of 'visitor_dto.dart';

// **************************************************************************
// FreezedGenerator
// **************************************************************************

// dart format off
T _$identity<T>(T value) => value;

/// @nodoc
mixin _$CreateVisitorLinkResponseDto {

 VisitorLinkDto? get link; String? get token; String? get url;
/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$CreateVisitorLinkResponseDtoCopyWith<CreateVisitorLinkResponseDto> get copyWith => _$CreateVisitorLinkResponseDtoCopyWithImpl<CreateVisitorLinkResponseDto>(this as CreateVisitorLinkResponseDto, _$identity);

  /// Serializes this CreateVisitorLinkResponseDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is CreateVisitorLinkResponseDto&&(identical(other.link, link) || other.link == link)&&(identical(other.token, token) || other.token == token)&&(identical(other.url, url) || other.url == url));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,link,token,url);

@override
String toString() {
  return 'CreateVisitorLinkResponseDto(link: $link, token: $token, url: $url)';
}


}

/// @nodoc
abstract mixin class $CreateVisitorLinkResponseDtoCopyWith<$Res>  {
  factory $CreateVisitorLinkResponseDtoCopyWith(CreateVisitorLinkResponseDto value, $Res Function(CreateVisitorLinkResponseDto) _then) = _$CreateVisitorLinkResponseDtoCopyWithImpl;
@useResult
$Res call({
 VisitorLinkDto? link, String? token, String? url
});


$VisitorLinkDtoCopyWith<$Res>? get link;

}
/// @nodoc
class _$CreateVisitorLinkResponseDtoCopyWithImpl<$Res>
    implements $CreateVisitorLinkResponseDtoCopyWith<$Res> {
  _$CreateVisitorLinkResponseDtoCopyWithImpl(this._self, this._then);

  final CreateVisitorLinkResponseDto _self;
  final $Res Function(CreateVisitorLinkResponseDto) _then;

/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? link = freezed,Object? token = freezed,Object? url = freezed,}) {
  return _then(_self.copyWith(
link: freezed == link ? _self.link : link // ignore: cast_nullable_to_non_nullable
as VisitorLinkDto?,token: freezed == token ? _self.token : token // ignore: cast_nullable_to_non_nullable
as String?,url: freezed == url ? _self.url : url // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}
/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$VisitorLinkDtoCopyWith<$Res>? get link {
    if (_self.link == null) {
    return null;
  }

  return $VisitorLinkDtoCopyWith<$Res>(_self.link!, (value) {
    return _then(_self.copyWith(link: value));
  });
}
}


/// Adds pattern-matching-related methods to [CreateVisitorLinkResponseDto].
extension CreateVisitorLinkResponseDtoPatterns on CreateVisitorLinkResponseDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _CreateVisitorLinkResponseDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _CreateVisitorLinkResponseDto value)  $default,){
final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _CreateVisitorLinkResponseDto value)?  $default,){
final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( VisitorLinkDto? link,  String? token,  String? url)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto() when $default != null:
return $default(_that.link,_that.token,_that.url);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( VisitorLinkDto? link,  String? token,  String? url)  $default,) {final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto():
return $default(_that.link,_that.token,_that.url);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( VisitorLinkDto? link,  String? token,  String? url)?  $default,) {final _that = this;
switch (_that) {
case _CreateVisitorLinkResponseDto() when $default != null:
return $default(_that.link,_that.token,_that.url);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _CreateVisitorLinkResponseDto implements CreateVisitorLinkResponseDto {
  const _CreateVisitorLinkResponseDto({this.link, this.token, this.url});
  factory _CreateVisitorLinkResponseDto.fromJson(Map<String, dynamic> json) => _$CreateVisitorLinkResponseDtoFromJson(json);

@override final  VisitorLinkDto? link;
@override final  String? token;
@override final  String? url;

/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$CreateVisitorLinkResponseDtoCopyWith<_CreateVisitorLinkResponseDto> get copyWith => __$CreateVisitorLinkResponseDtoCopyWithImpl<_CreateVisitorLinkResponseDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$CreateVisitorLinkResponseDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _CreateVisitorLinkResponseDto&&(identical(other.link, link) || other.link == link)&&(identical(other.token, token) || other.token == token)&&(identical(other.url, url) || other.url == url));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,link,token,url);

@override
String toString() {
  return 'CreateVisitorLinkResponseDto(link: $link, token: $token, url: $url)';
}


}

/// @nodoc
abstract mixin class _$CreateVisitorLinkResponseDtoCopyWith<$Res> implements $CreateVisitorLinkResponseDtoCopyWith<$Res> {
  factory _$CreateVisitorLinkResponseDtoCopyWith(_CreateVisitorLinkResponseDto value, $Res Function(_CreateVisitorLinkResponseDto) _then) = __$CreateVisitorLinkResponseDtoCopyWithImpl;
@override @useResult
$Res call({
 VisitorLinkDto? link, String? token, String? url
});


@override $VisitorLinkDtoCopyWith<$Res>? get link;

}
/// @nodoc
class __$CreateVisitorLinkResponseDtoCopyWithImpl<$Res>
    implements _$CreateVisitorLinkResponseDtoCopyWith<$Res> {
  __$CreateVisitorLinkResponseDtoCopyWithImpl(this._self, this._then);

  final _CreateVisitorLinkResponseDto _self;
  final $Res Function(_CreateVisitorLinkResponseDto) _then;

/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? link = freezed,Object? token = freezed,Object? url = freezed,}) {
  return _then(_CreateVisitorLinkResponseDto(
link: freezed == link ? _self.link : link // ignore: cast_nullable_to_non_nullable
as VisitorLinkDto?,token: freezed == token ? _self.token : token // ignore: cast_nullable_to_non_nullable
as String?,url: freezed == url ? _self.url : url // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

/// Create a copy of CreateVisitorLinkResponseDto
/// with the given fields replaced by the non-null parameter values.
@override
@pragma('vm:prefer-inline')
$VisitorLinkDtoCopyWith<$Res>? get link {
    if (_self.link == null) {
    return null;
  }

  return $VisitorLinkDtoCopyWith<$Res>(_self.link!, (value) {
    return _then(_self.copyWith(link: value));
  });
}
}


/// @nodoc
mixin _$VisitorLinkDto {

 int? get id; String? get visitorName; String? get purpose; String? get accessType; String? get status; String? get expiresAt;
/// Create a copy of VisitorLinkDto
/// with the given fields replaced by the non-null parameter values.
@JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
$VisitorLinkDtoCopyWith<VisitorLinkDto> get copyWith => _$VisitorLinkDtoCopyWithImpl<VisitorLinkDto>(this as VisitorLinkDto, _$identity);

  /// Serializes this VisitorLinkDto to a JSON map.
  Map<String, dynamic> toJson();


@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is VisitorLinkDto&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt);

@override
String toString() {
  return 'VisitorLinkDto(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt)';
}


}

/// @nodoc
abstract mixin class $VisitorLinkDtoCopyWith<$Res>  {
  factory $VisitorLinkDtoCopyWith(VisitorLinkDto value, $Res Function(VisitorLinkDto) _then) = _$VisitorLinkDtoCopyWithImpl;
@useResult
$Res call({
 int? id, String? visitorName, String? purpose, String? accessType, String? status, String? expiresAt
});




}
/// @nodoc
class _$VisitorLinkDtoCopyWithImpl<$Res>
    implements $VisitorLinkDtoCopyWith<$Res> {
  _$VisitorLinkDtoCopyWithImpl(this._self, this._then);

  final VisitorLinkDto _self;
  final $Res Function(VisitorLinkDto) _then;

/// Create a copy of VisitorLinkDto
/// with the given fields replaced by the non-null parameter values.
@pragma('vm:prefer-inline') @override $Res call({Object? id = freezed,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = freezed,Object? status = freezed,Object? expiresAt = freezed,}) {
  return _then(_self.copyWith(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: freezed == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}

}


/// Adds pattern-matching-related methods to [VisitorLinkDto].
extension VisitorLinkDtoPatterns on VisitorLinkDto {
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

@optionalTypeArgs TResult maybeMap<TResult extends Object?>(TResult Function( _VisitorLinkDto value)?  $default,{required TResult orElse(),}){
final _that = this;
switch (_that) {
case _VisitorLinkDto() when $default != null:
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

@optionalTypeArgs TResult map<TResult extends Object?>(TResult Function( _VisitorLinkDto value)  $default,){
final _that = this;
switch (_that) {
case _VisitorLinkDto():
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

@optionalTypeArgs TResult? mapOrNull<TResult extends Object?>(TResult? Function( _VisitorLinkDto value)?  $default,){
final _that = this;
switch (_that) {
case _VisitorLinkDto() when $default != null:
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

@optionalTypeArgs TResult maybeWhen<TResult extends Object?>(TResult Function( int? id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt)?  $default,{required TResult orElse(),}) {final _that = this;
switch (_that) {
case _VisitorLinkDto() when $default != null:
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt);case _:
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

@optionalTypeArgs TResult when<TResult extends Object?>(TResult Function( int? id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt)  $default,) {final _that = this;
switch (_that) {
case _VisitorLinkDto():
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt);case _:
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

@optionalTypeArgs TResult? whenOrNull<TResult extends Object?>(TResult? Function( int? id,  String? visitorName,  String? purpose,  String? accessType,  String? status,  String? expiresAt)?  $default,) {final _that = this;
switch (_that) {
case _VisitorLinkDto() when $default != null:
return $default(_that.id,_that.visitorName,_that.purpose,_that.accessType,_that.status,_that.expiresAt);case _:
  return null;

}
}

}

/// @nodoc
@JsonSerializable()

class _VisitorLinkDto implements VisitorLinkDto {
  const _VisitorLinkDto({this.id, this.visitorName, this.purpose, this.accessType, this.status, this.expiresAt});
  factory _VisitorLinkDto.fromJson(Map<String, dynamic> json) => _$VisitorLinkDtoFromJson(json);

@override final  int? id;
@override final  String? visitorName;
@override final  String? purpose;
@override final  String? accessType;
@override final  String? status;
@override final  String? expiresAt;

/// Create a copy of VisitorLinkDto
/// with the given fields replaced by the non-null parameter values.
@override @JsonKey(includeFromJson: false, includeToJson: false)
@pragma('vm:prefer-inline')
_$VisitorLinkDtoCopyWith<_VisitorLinkDto> get copyWith => __$VisitorLinkDtoCopyWithImpl<_VisitorLinkDto>(this, _$identity);

@override
Map<String, dynamic> toJson() {
  return _$VisitorLinkDtoToJson(this, );
}

@override
bool operator ==(Object other) {
  return identical(this, other) || (other.runtimeType == runtimeType&&other is _VisitorLinkDto&&(identical(other.id, id) || other.id == id)&&(identical(other.visitorName, visitorName) || other.visitorName == visitorName)&&(identical(other.purpose, purpose) || other.purpose == purpose)&&(identical(other.accessType, accessType) || other.accessType == accessType)&&(identical(other.status, status) || other.status == status)&&(identical(other.expiresAt, expiresAt) || other.expiresAt == expiresAt));
}

@JsonKey(includeFromJson: false, includeToJson: false)
@override
int get hashCode => Object.hash(runtimeType,id,visitorName,purpose,accessType,status,expiresAt);

@override
String toString() {
  return 'VisitorLinkDto(id: $id, visitorName: $visitorName, purpose: $purpose, accessType: $accessType, status: $status, expiresAt: $expiresAt)';
}


}

/// @nodoc
abstract mixin class _$VisitorLinkDtoCopyWith<$Res> implements $VisitorLinkDtoCopyWith<$Res> {
  factory _$VisitorLinkDtoCopyWith(_VisitorLinkDto value, $Res Function(_VisitorLinkDto) _then) = __$VisitorLinkDtoCopyWithImpl;
@override @useResult
$Res call({
 int? id, String? visitorName, String? purpose, String? accessType, String? status, String? expiresAt
});




}
/// @nodoc
class __$VisitorLinkDtoCopyWithImpl<$Res>
    implements _$VisitorLinkDtoCopyWith<$Res> {
  __$VisitorLinkDtoCopyWithImpl(this._self, this._then);

  final _VisitorLinkDto _self;
  final $Res Function(_VisitorLinkDto) _then;

/// Create a copy of VisitorLinkDto
/// with the given fields replaced by the non-null parameter values.
@override @pragma('vm:prefer-inline') $Res call({Object? id = freezed,Object? visitorName = freezed,Object? purpose = freezed,Object? accessType = freezed,Object? status = freezed,Object? expiresAt = freezed,}) {
  return _then(_VisitorLinkDto(
id: freezed == id ? _self.id : id // ignore: cast_nullable_to_non_nullable
as int?,visitorName: freezed == visitorName ? _self.visitorName : visitorName // ignore: cast_nullable_to_non_nullable
as String?,purpose: freezed == purpose ? _self.purpose : purpose // ignore: cast_nullable_to_non_nullable
as String?,accessType: freezed == accessType ? _self.accessType : accessType // ignore: cast_nullable_to_non_nullable
as String?,status: freezed == status ? _self.status : status // ignore: cast_nullable_to_non_nullable
as String?,expiresAt: freezed == expiresAt ? _self.expiresAt : expiresAt // ignore: cast_nullable_to_non_nullable
as String?,
  ));
}


}

// dart format on
