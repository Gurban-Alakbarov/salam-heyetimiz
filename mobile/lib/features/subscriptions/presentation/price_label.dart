import '../../../l10n/app_localizations.dart';
import '../../complex/domain/complex_entities.dart';

/// "15.00 AZN / il" for a yearly term, "12.00 AZN / 30 gün" otherwise. The price and the term always come from
/// the server, so a price change never needs an app update.
String termPriceLabel(
  AppLocalizations l,
  int minor,
  String? currency,
  int days,
) {
  final price = formatMinor(minor, currency);
  return days == 365 ? l.pricePerYear(price) : l.cxPrice(price, days);
}
