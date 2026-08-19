// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Azerbaijani (`az`).
class AppLocalizationsAz extends AppLocalizations {
  AppLocalizationsAz([String locale = 'az']) : super(locale);

  @override
  String get appTitle => 'Salam Həyətimiz';

  @override
  String get register => 'Qeydiyyat';

  @override
  String get login => 'Giriş';

  @override
  String get verifyOtp => 'OTP təsdiqi';

  @override
  String get continueLabel => 'Davam et';

  @override
  String get home => 'Ana səhifə';

  @override
  String get devices => 'Cihazlar';

  @override
  String get profile => 'Profil';

  @override
  String get settings => 'Parametrlər';

  @override
  String get theme => 'Tema';

  @override
  String get language => 'Dil';

  @override
  String get logout => 'Çıxış';

  @override
  String get welcomeTagline => 'Həyətiniz bir toxunuş uzaqlıqda';

  @override
  String get registerTitle => 'Hesab yarat';

  @override
  String get firstName => 'Ad';

  @override
  String get lastName => 'Soyad';

  @override
  String get phoneNumber => 'Telefon nömrəsi';

  @override
  String get emailAddress => 'Email';

  @override
  String get phoneHint => '+994XXXXXXXXX';

  @override
  String get emailHint => 'siz@example.com';

  @override
  String get registerSubmit => 'Qeydiyyatdan keç';

  @override
  String get alreadyHaveAccount => 'Hesabınız var? Giriş edin';

  @override
  String get dontHaveAccount => 'Hesabınız yoxdur? Qeydiyyatdan keçin';

  @override
  String get loginTitle => 'Giriş';

  @override
  String get loginSubmit => 'Giriş kodu göndər';

  @override
  String get otpTitle => 'Kodu daxil edin';

  @override
  String otpSentTo(String email) {
    return 'Kod $email ünvanına göndərildi';
  }

  @override
  String get otpFieldHint => '6 rəqəmli kod';

  @override
  String get verifySubmit => 'Təsdiqlə';

  @override
  String get resend => 'Yenidən göndər';

  @override
  String resendIn(int seconds) {
    return 'Yenidən göndər (${seconds}s)';
  }

  @override
  String get maintenanceTitle => 'Texniki işlər';

  @override
  String get maintenanceMessage =>
      'Tətbiq müvəqqəti olaraq əlçatan deyil. Bir azdan yenidən cəhd edin.';

  @override
  String get forceUpdateTitle => 'Yeniləmə tələb olunur';

  @override
  String get forceUpdateMessage => 'Davam etmək üçün tətbiqi yeniləyin.';

  @override
  String get updateNow => 'İndi yenilə';

  @override
  String get retry => 'Yenidən cəhd et';

  @override
  String get sessionExpired => 'Sessiya bitdi. Yenidən giriş edin.';

  @override
  String get errWrongCode => 'Təsdiq kodu yanlışdır.';

  @override
  String get errOtpExpired => 'Kodun vaxtı bitib. Yeni kod istəyin.';

  @override
  String get errOtpMaxAttempts => 'Çox sayda yanlış cəhd. Yeni kod istəyin.';

  @override
  String get errEmailAlreadyRegistered =>
      'Bu email artıq qeydiyyatdan keçib. Giriş edin.';

  @override
  String errRateLimited(int seconds) {
    return 'Çox sayda sorğu. $seconds saniyə sonra cəhd edin.';
  }

  @override
  String get errNetwork => 'İnternet bağlantısı yoxdur.';

  @override
  String get errTimeout => 'Bağlantı vaxtı bitdi.';

  @override
  String get errServer => 'Server xətası. Bir azdan yenidən cəhd edin.';

  @override
  String get errUnknown => 'Xəta baş verdi.';

  @override
  String get errValidation => 'Daxil edilən məlumatları yoxlayın.';

  @override
  String get vRequired => 'Bu sahə tələb olunur';

  @override
  String get vEmail => 'Düzgün email daxil edin';

  @override
  String get vPhone => 'Nömrə +994XXXXXXXXX formatında olmalıdır';

  @override
  String homeGreeting(String name) {
    return 'Salam, $name!';
  }

  @override
  String get homeUser => 'İstifadəçi';

  @override
  String get homeActiveDevices => 'Aktiv cihaz';

  @override
  String get homeActiveSubscriptions => 'Aktiv abunəlik';

  @override
  String get homeLastActivity => 'Son fəaliyyət';

  @override
  String get homeQuickOpen => 'Qapını aç';

  @override
  String get devicesTitle => 'Cihazlarım';

  @override
  String get devicesEmpty => 'Cihazınız yoxdur';

  @override
  String get deviceOnline => 'Online';

  @override
  String get deviceOffline => 'Offline';

  @override
  String get deviceUnknownStatus => 'Naməlum';

  @override
  String get deviceInfoTitle => 'Cihaz məlumatı';

  @override
  String get deviceAddress => 'Ünvan';

  @override
  String get deviceImei => 'IMEI';

  @override
  String get deviceLastOnlineLabel => 'Son online';

  @override
  String get notifications => 'Bildirişlər';

  @override
  String get notificationsEmpty => 'Hələ bildiriş yoxdur';

  @override
  String get notificationsEmptyHint => 'Yeni bildirişlər burada görünəcək.';

  @override
  String get notificationsMarkAllRead => 'Hamısını oxunmuş et';

  @override
  String get profilePersonalInfo => 'Şəxsi məlumatlar';

  @override
  String get profileResidence => 'Yaşayış kompleksi';

  @override
  String get profileAppSettings => 'Tətbiq ayarları';

  @override
  String get profileHelp => 'Yardım və dəstək';

  @override
  String get fieldFirstName => 'Ad';

  @override
  String get fieldLastName => 'Soyad';

  @override
  String get fieldPhone => 'Telefon';

  @override
  String get fieldEmail => 'E-mail';

  @override
  String get helpDescription =>
      'Suallar və dəstək üçün bizimlə əlaqə saxlayın.';

  @override
  String get residenceEmpty => 'Təyin olunmuş barrier yoxdur.';

  @override
  String appVersionLabel(String version) {
    return 'Versiya $version';
  }

  @override
  String get deviceAddressMissing => 'Ünvan daxil edilməyib.';

  @override
  String deviceLastOnline(String time) {
    return 'Son online: $time';
  }

  @override
  String get deviceStatus => 'Status';

  @override
  String get deviceRole => 'Rol';

  @override
  String get deviceRoleOwner => 'Sahib';

  @override
  String get deviceRoleUser => 'Sakin';

  @override
  String get deviceModel => 'Model';

  @override
  String get deviceSerial => 'Seriya nömrəsi';

  @override
  String get deviceSubscription => 'Abunəlik';

  @override
  String get deviceSubscriptionActive => 'Aktiv';

  @override
  String get activeSubscriptionsTitle => 'Aktiv abunəliklər';

  @override
  String get subscriptionsEmpty => 'Aktiv abunəliyiniz yoxdur';

  @override
  String get subscriptionTierMain => 'Əsas abunəlik';

  @override
  String get subscriptionTierAdditional => 'Əlavə abunəlik';

  @override
  String get subscriptionStart => 'Başlanğıc';

  @override
  String get subscriptionEnd => 'Bitmə';

  @override
  String subscriptionDaysLeft(int days) {
    return '$days gün qalıb';
  }

  @override
  String get subscriptionExpiresToday => 'Bu gün bitir';

  @override
  String get barrierOpen => 'Qapını Aç';

  @override
  String get barrierSending => 'Göndərilir…';

  @override
  String get barrierPending => 'Açılır…';

  @override
  String get barrierSuccessOpened => 'Qapı açıldı';

  @override
  String get barrierSuccessSent => 'Komanda göndərildi';

  @override
  String get barrierFailed => 'Qapı açıla bilmədi';

  @override
  String get barrierTimeout => 'Vaxt bitdi — cihazdan cavab yoxdur';

  @override
  String barrierCooldown(int seconds) {
    return 'Çox sayda cəhd. ${seconds}s sonra yenidən cəhd edin';
  }

  @override
  String get barrierGateMovedQuestion => 'Qapı açıldımı?';

  @override
  String get barrierClose => 'Qapını Bağla';

  @override
  String get barrierCloseSending => 'Göndərilir…';

  @override
  String get barrierClosePending => 'Bağlanır…';

  @override
  String get barrierCloseSuccessClosed => 'Qapı bağlandı';

  @override
  String get barrierCloseSuccessSent => 'Komanda göndərildi';

  @override
  String get barrierCloseFailed => 'Qapı bağlana bilmədi';

  @override
  String get yes => 'Bəli';

  @override
  String get no => 'Xeyr';

  @override
  String get errDeviceOffline => 'Cihaz offline — komanda göndərilə bilmədi';

  @override
  String get errAccessDenied => 'Bu cihaza icazəniz yoxdur';

  @override
  String get errSubscriptionRequired => 'Abunəliyiniz aktiv deyil';

  @override
  String get errDeviceDisabled => 'Cihaz deaktiv edilib';

  @override
  String get errWhitelist => 'Cihaz komandanı qəbul etmədi (whitelist)';

  @override
  String get errNotFound => 'Tapılmadı';

  @override
  String get barrierLocating => 'Məkan alınır…';

  @override
  String get errLocationRequired => 'Qapını açmaq üçün məkan lazımdır.';

  @override
  String get errOutsideGeofence => 'Qapıdan çox uzaqdasınız.';

  @override
  String get errLocationImprecise => 'Məkanınız kifayət qədər dəqiq deyil.';

  @override
  String get errLocationPermissionDenied =>
      'Bu qapını açmaq üçün məkan icazəsi lazımdır.';

  @override
  String get errLocationPermissionPermanent =>
      'Məkan icazəsi bağlıdır. Ayarlardan aktivləşdirin.';

  @override
  String get errLocationServiceDisabled =>
      'Məkan (GPS) bağlıdır. Açmaq üçün onu yandırın.';

  @override
  String get errLocationTimeout => 'Məkanınız alınmadı. Yenidən cəhd edin.';

  @override
  String get locationOpenSettings => 'Ayarları aç';

  @override
  String get directions => 'Yol göstər';

  @override
  String get inviteVisitor => 'Dəvət et';

  @override
  String get directionsNoLocation => 'Bu barrier üçün məkan təyin edilməyib';

  @override
  String get directionsNoApp => 'Naviqasiya tətbiqi tapılmadı';

  @override
  String get directionsChooseApp => 'Tətbiq seçin';

  @override
  String get directionsFailed => 'Naviqasiya açıla bilmədi';

  @override
  String get azNavRequiredTitle => 'AzNav tələb olunur';

  @override
  String get azNavRequiredMessage =>
      'Yol göstər funksiyasından istifadə etmək üçün AzNav tətbiqini quraşdırın.';

  @override
  String get azNavInstall => 'AzNav-ı yüklə';

  @override
  String get cancel => 'İmtina';

  @override
  String get visitorInviteTitle => 'Qonaq dəvət et';

  @override
  String get visitorAccessOneTime => 'Birdəfəlik';

  @override
  String get visitorAccessTimeLimited => 'Müddətli';

  @override
  String get visitorDurationLabel => 'Müddət';

  @override
  String get visitorNameLabel => 'Qonağın adı (istəyə bağlı)';

  @override
  String get visitorPurposeLabel => 'Məqsəd (istəyə bağlı)';

  @override
  String get visitorPurposeGuest => 'Qonaq';

  @override
  String get visitorPurposeDelivery => 'Çatdırılma';

  @override
  String get visitorPurposeCourier => 'Kuryer';

  @override
  String get visitorPurposeService => 'Xidmət';

  @override
  String get visitorPurposeCleaning => 'Təmizlik';

  @override
  String get visitorPurposeTaxi => 'Taksi';

  @override
  String get visitorPurposeOther => 'Digər';

  @override
  String get visitorGenerate => 'Link yarat';

  @override
  String get visitorLinkReady => 'Link hazırdır';

  @override
  String get visitorShare => 'Paylaş';

  @override
  String get visitorCopy => 'Kopyala';

  @override
  String get visitorCopied => 'Kopyalandı';

  @override
  String get visitorDone => 'Bağla';

  @override
  String visitorMinutesShort(int count) {
    return '$count dəq';
  }

  @override
  String visitorHoursShort(int count) {
    return '$count saat';
  }

  @override
  String get doorWidgetTitle => 'Ana ekran vidceti';

  @override
  String get doorWidgetIntro =>
      'Ana ekran vidceti üçün qapı seçin. Seçdiyiniz qapının adı vidcetdə görünəcək.';

  @override
  String doorWidgetSelected(String label) {
    return '$label vidcetə təyin edildi';
  }

  @override
  String get doorWidgetClear => 'Vidcet təyinatını sil';

  @override
  String get doorWidgetCleared => 'Vidcet təyinatı silindi';

  @override
  String get doorWidgetUnconfigured => 'Qapı seçilməyib';

  @override
  String get doorWidgetAddHint =>
      'Ana ekrana \"Qapını aç\" vidceti əlavə edin, sonra qapı seçin.';

  @override
  String doorWidgetInstance(int id) {
    return 'Vidcet #$id';
  }
}
