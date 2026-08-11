import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Storage tiers (Constitution §16, OFFLINE_STRATEGY.md §1):
/// secure → tokens; prefs → flags; Hive → structured cache.

/// Encrypted storage for tokens + sensitive flags. NEVER store PII in plain tiers.
class SecureStore {
  const SecureStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  Future<String?> read(String key) => _storage.read(key: key);
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);
  Future<void> delete(String key) => _storage.delete(key: key);
  Future<void> clear() => _storage.deleteAll();
}

/// Small non-sensitive key/value (theme, locale, onboarding flag).
class KvStore {
  KvStore(this._prefs);

  final SharedPreferences _prefs;

  String? getString(String key) => _prefs.getString(key);
  Future<void> setString(String key, String value) =>
      _prefs.setString(key, value);
  bool getBool(String key, {bool fallback = false}) =>
      _prefs.getBool(key) ?? fallback;
  Future<void> setBool(String key, {required bool value}) =>
      _prefs.setBool(key, value);
}

/// Structured offline cache (Hive). Boxes opened lazily; cache only, never the
/// source of truth.
class CacheStore {
  CacheStore(this._box);

  final Box<dynamic> _box;

  Object? get(String key) => _box.get(key);
  Future<void> put(String key, Object value) => _box.put(key, value);
  Future<void> delete(String key) => _box.delete(key);

  static Future<CacheStore> open(String name) async {
    final box = await Hive.openBox<dynamic>(name);
    return CacheStore(box);
  }
}
