/// Tiny semver-ish comparator for the force-update gate.
class AppVersion {
  AppVersion._();

  /// True if [current] is strictly below [minimum] (compares major.minor.patch;
  /// ignores build metadata after `+`).
  static bool isBelow(String current, String minimum) {
    final c = _parse(current);
    final m = _parse(minimum);
    for (var i = 0; i < 3; i++) {
      if (c[i] != m[i]) return c[i] < m[i];
    }
    return false;
  }

  static List<int> _parse(String v) {
    final core = v.split('+').first.split('-').first;
    final parts = core.split('.');
    return List<int>.generate(3, (i) {
      if (i >= parts.length) return 0;
      return int.tryParse(parts[i].replaceAll(RegExp('[^0-9]'), '')) ?? 0;
    });
  }
}
