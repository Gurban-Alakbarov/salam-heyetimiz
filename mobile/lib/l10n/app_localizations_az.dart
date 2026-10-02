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

  @override
  String get homeInvitations => 'Dəvətlərim';

  @override
  String get invitationsTitle => 'Dəvətlərim';

  @override
  String get invitationsEmpty => 'Hazırda göndərilmiş dəvətiniz yoxdur.';

  @override
  String get invitationFilterAll => 'Hamısı';

  @override
  String get invitationFilterActive => 'Aktiv';

  @override
  String get invitationFilterUsed => 'İstifadə olunub';

  @override
  String get invitationFilterExpired => 'Vaxtı bitib';

  @override
  String get invitationFilterRevoked => 'Ləğv edilib';

  @override
  String get invitationStatusActive => 'Aktiv';

  @override
  String get invitationStatusUsed => 'İstifadə olunub';

  @override
  String get invitationStatusExpired => 'Vaxtı bitib';

  @override
  String get invitationStatusRevoked => 'Ləğv edilib';

  @override
  String get invitationSentAt => 'Göndərilib';

  @override
  String get invitationDuration => 'Müddət';

  @override
  String get invitationExpiresAt => 'Bitmə';

  @override
  String invitationRemaining(String time) {
    return '$time qalıb';
  }

  @override
  String get invitationUsedAt => 'İstifadə edildi';

  @override
  String get invitationFirstUsedAt => 'İlk istifadə';

  @override
  String get invitationLastUsedAt => 'Son istifadə';

  @override
  String get invitationUsageCount => 'İstifadə sayı';

  @override
  String invitationUsageValue(int count) {
    return '$count dəfə';
  }

  @override
  String get invitationUnlimited => 'Limitsiz';

  @override
  String get invitationDefaultTitle => 'Dəvət';

  @override
  String get checkoutTitle => 'Ödəniş';

  @override
  String get paymentTestBanner => 'TEST ÖDƏNİŞ';

  @override
  String get paymentResultTitle => 'Ödənişin nəticəsi';

  @override
  String get paymentSuccess => 'Ödəniş uğurlu oldu';

  @override
  String get paymentFailed => 'Ödəniş uğursuz oldu';

  @override
  String get paymentCancelled => 'Ödəniş ləğv edildi';

  @override
  String get paymentExpired => 'Ödəniş vaxtı bitdi';

  @override
  String get paymentPending => 'Ödəniş emal olunur';

  @override
  String get paymentChecking => 'Bankla təsdiqlənir…';

  @override
  String get paymentRecheck => 'Yenidən yoxla';

  @override
  String get paymentDone => 'Ana səhifəyə qayıt';

  @override
  String get paymentOrderNotFound => 'Sifariş tapılmadı';

  @override
  String get subscriptionRenew => 'Yenilə (12 AZN / 30 gün)';

  @override
  String get subscriptionRenewNotEligible =>
      'Bu abunəlik hazırda yenilənə bilməz.';

  @override
  String get regTypeTitle => 'Qeydiyyat növü';

  @override
  String get regTypePhysical => 'Fiziki şəxs';

  @override
  String get regTypePhysicalBody =>
      'Öz şəxsi həyətimə cihaz qoşdurmaq istəyirəm.';

  @override
  String get regTypeLegal => 'Hüquqi şəxs';

  @override
  String get regTypeLegalBody => 'Yaşayış kompleksi, bina və ya şirkət.';

  @override
  String get appMineTitle => 'Müraciətlərim';

  @override
  String get appNone => 'Hələ müraciətiniz yoxdur';

  @override
  String get appNewPhysical => 'Yeni müraciət (fiziki şəxs)';

  @override
  String get appNewLegal => 'Yeni müraciət (hüquqi şəxs)';

  @override
  String get appSectionPhysical => 'Fiziki şəxs';

  @override
  String get appSectionLegal => 'Hüquqi şəxs';

  @override
  String get appPhysicalTitle => 'Fiziki şəxs müraciəti';

  @override
  String get appPhysicalIntro =>
      'Həyətinizin yerini göstərin — komandamız quraşdırma üçün sizinlə əlaqə saxlayacaq.';

  @override
  String get appLegalTitle => 'Hüquqi şəxs müraciəti';

  @override
  String get appLegalIntro =>
      'Kompleks məlumatları və lokasiya. Müraciətə admin baxır.';

  @override
  String get appFullName => 'Ad, soyad';

  @override
  String get appPhone => 'Telefon';

  @override
  String get appEmail => 'Email';

  @override
  String get appAddress => 'Ünvan';

  @override
  String get appNote => 'Qeyd (istəyə bağlı)';

  @override
  String get appComplexName => 'Kompleksin adı';

  @override
  String get appLegalName => 'Hüquqi ad';

  @override
  String get appVoen => 'VÖEN';

  @override
  String get appLegalAddress => 'Hüquqi ünvan';

  @override
  String get appContactName => 'Əlaqə şəxsi';

  @override
  String get appComplexAddress => 'Kompleksin ünvanı';

  @override
  String get appApartments => 'Mənzil sayı (istəyə bağlı)';

  @override
  String get appLocationLabel => 'Xəritədə lokasiya';

  @override
  String get appLocationHint => 'Pin qoymaq üçün xəritəyə toxunun';

  @override
  String get appUseMyLocation => 'Mövqeyim';

  @override
  String get appLocationRequired => 'Xəritədə lokasiyanı seçin';

  @override
  String get appLocationOutside => 'Lokasiya Azərbaycan ərazisində olmalıdır';

  @override
  String get appVoenInvalid => 'VÖEN 10 rəqəm olmalıdır';

  @override
  String get appApartmentsInvalid => 'Düzgün say daxil edin';

  @override
  String get appSubmit => 'Müraciəti göndər';

  @override
  String get appLater => 'Sonra dolduraram';

  @override
  String get appSubmitted => 'Müraciət göndərildi';

  @override
  String get appStatusNew => 'Yeni';

  @override
  String get appStatusContacted => 'Əlaqə saxlanılıb';

  @override
  String get appStatusInProgress => 'İcradadır';

  @override
  String get appStatusInstalled => 'Quraşdırılıb';

  @override
  String get appStatusPending => 'Baxılır';

  @override
  String get appStatusApproved => 'Təsdiqlənib';

  @override
  String get appStatusRejected => 'Rədd edilib';

  @override
  String get appRejectReason => 'Səbəb';

  @override
  String get appErrTypeMismatch =>
      'Hesab növünüz bu müraciət növünə uyğun deyil.';

  @override
  String get appErrAlreadyOpen => 'Sizin artıq açıq müraciətiniz var.';

  @override
  String get appErrDuplicateVoen => 'Bu VÖEN ilə müraciət artıq baxılır.';

  @override
  String get kmTitle => 'Kompleksim (Komendant)';

  @override
  String get kmEntrySubtitle => 'Sakinlər, dəvətlər və cihazlar';

  @override
  String get kmStatDevices => 'Cihazlar';

  @override
  String get kmStatResidents => 'Sakinlər';

  @override
  String get kmStatPending => 'Gözləyən dəvət';

  @override
  String get kmDevicesTitle => 'Kompleks cihazları';

  @override
  String get kmNoDevices => 'Kompleksə hələ cihaz bağlanmayıb.';

  @override
  String get kmOnline => 'Onlayn';

  @override
  String get kmOffline => 'Oflayn';

  @override
  String kmDevicePrice(String price, int days) {
    return 'Abunəlik: $price / $days gün';
  }

  @override
  String get kmInvite => 'Sakin dəvət et';

  @override
  String get kmInviteIntro =>
      'Sakinə email ilə 7 gün etibarlı dəvət linki göndəriləcək.';

  @override
  String get kmFirstName => 'Ad';

  @override
  String get kmLastName => 'Soyad';

  @override
  String get kmEmail => 'Email';

  @override
  String get kmInviteSend => 'Dəvət göndər';

  @override
  String get kmInviteSent => 'Dəvət göndərildi.';

  @override
  String get kmInvitations => 'Dəvətlər';

  @override
  String get kmTabPending => 'Gözləyən';

  @override
  String get kmTabAccepted => 'Qəbul edilib';

  @override
  String get kmTabExpired => 'Vaxtı bitib';

  @override
  String get kmTabClosed => 'Ləğv edilib';

  @override
  String get kmStatusDeclined => 'İmtina edilib';

  @override
  String get kmNoInvitations => 'Bu bölmədə dəvət yoxdur.';

  @override
  String kmExpiresAt(String date) {
    return 'Bitmə vaxtı: $date';
  }

  @override
  String kmAcceptedAt(String date) {
    return 'Qəbul edilib: $date';
  }

  @override
  String kmSendCount(int count) {
    return 'Göndərilib: $count dəfə';
  }

  @override
  String get kmResend => 'Yenidən göndər';

  @override
  String get kmRevoke => 'Ləğv et';

  @override
  String get kmResent => 'Dəvət yenidən göndərildi.';

  @override
  String get kmRevoked => 'Dəvət ləğv edildi.';

  @override
  String get kmRevokeTitle => 'Dəvət ləğv edilsin?';

  @override
  String get kmRevokeBody => 'Dəvət linki artıq işləməyəcək.';

  @override
  String get kmResidentsTitle => 'Sakinlər';

  @override
  String get kmNoResidents => 'Kompleksdə hələ sakin yoxdur.';

  @override
  String kmActiveSubs(int count) {
    return 'Aktiv abunəlik: $count';
  }

  @override
  String get kmNoActiveSub => 'Aktiv abunəlik yoxdur';

  @override
  String kmJoinedAt(String date) {
    return 'Qoşulub: $date';
  }

  @override
  String get kmRemove => 'Çıxar';

  @override
  String get kmRemoveTitle => 'Sakini kompleksdən çıxar';

  @override
  String kmRemoveBody(String name) {
    return '$name kompleksdən çıxarılacaq. Kompleks cihazlarına girişi dərhal dayanacaq, aktiv abunəlikləri ləğv ediləcək. Avtomatik geri ödəniş edilmir.';
  }

  @override
  String get kmRemoved => 'Sakin kompleksdən çıxarıldı.';

  @override
  String get kmErrNotKomendant =>
      'Bu bölmə yalnız kompleks komendantı üçündür.';

  @override
  String get kmErrForbidden => 'Bu əməliyyat üçün icazəniz yoxdur.';

  @override
  String get kmErrAlreadyResident => 'Bu email artıq kompleksin sakinidir.';

  @override
  String get kmErrAlreadyPending =>
      'Bu email üçün aktiv dəvət artıq mövcuddur.';

  @override
  String get kmErrNotResendable => 'Bu dəvət yenidən göndərilə bilməz.';

  @override
  String get kmErrNotRevocable => 'Bu dəvət ləğv edilə bilməz.';

  @override
  String get kmErrRateLimited =>
      'Çox tez-tez göndərilir. Bir az sonra yenidən cəhd edin.';

  @override
  String get kmErrNotFound => 'Qeyd tapılmadı. Siyahı yeniləndi.';

  @override
  String get invTitle => 'Dəvət';

  @override
  String get invComplexKind => 'Yaşayış kompleksinə dəvət';

  @override
  String get invFamilyKind => 'Ailə üzvü dəvəti';

  @override
  String invComplexBody(String complex) {
    return '$complex kompleksinə sakin kimi dəvət olunmusunuz. Qəbul etdikdən sonra cihaz seçib abunə ola bilərsiniz.';
  }

  @override
  String invFamilyBody(String inviter) {
    return '$inviter sizi ailə üzvü kimi cihaza giriş üçün dəvət edib. Giriş öz abunəliyiniz ödənildikdən sonra aktivləşir.';
  }

  @override
  String invFor(String name) {
    return 'Dəvət olunan: $name';
  }

  @override
  String invSentTo(String email) {
    return 'Dəvət $email ünvanına göndərilib. Həmin email ilə davam edin.';
  }

  @override
  String invExpires(String date) {
    return 'Etibarlıdır: $date tarixinədək';
  }

  @override
  String get invRegister => 'Qeydiyyatdan keç';

  @override
  String get invLogin => 'Hesabım var — daxil ol';

  @override
  String get invAccept => 'Dəvəti qəbul et';

  @override
  String get invDecline => 'İmtina et';

  @override
  String get invDeclineTitle => 'Dəvətdən imtina edilsin?';

  @override
  String get invDeclineBody => 'Bu dəvət linki artıq işləməyəcək.';

  @override
  String get invDeclined => 'Dəvətdən imtina edildi.';

  @override
  String invAcceptedComplex(String complex) {
    return 'Dəvət qəbul edildi. $complex kompleksinə xoş gəldiniz!';
  }

  @override
  String get invAcceptedFamily =>
      'Dəvət qəbul edildi. Girişi aktivləşdirmək üçün abunəliyi ödəyin.';

  @override
  String get invGoneTitle => 'Dəvət etibarsızdır';

  @override
  String get invGoneBody =>
      'Bu dəvət linkinin vaxtı bitib, ləğv edilib və ya artıq istifadə olunub. Yeni dəvət üçün dəvət edən şəxsə müraciət edin.';

  @override
  String get invClose => 'Bağla';

  @override
  String get invErrEmailMismatch =>
      'Bu dəvət başqa email ünvanı üçündür. Dəvət göndərilən email ilə daxil olun.';

  @override
  String get invErrAlreadyHasAccess => 'Bu cihaza artıq girişiniz var.';

  @override
  String get invErrInvalidTarget =>
      'Öz göndərdiyiniz dəvəti qəbul edə bilməzsiniz.';

  @override
  String get invErrUnsupported => 'Bu dəvət növü dəstəklənmir.';

  @override
  String get invRegisterHint =>
      'Qeydiyyatı dəvət göndərilən email ilə tamamlayın; dəvət hesab təsdiqləndikdən sonra qəbul ediləcək.';

  @override
  String get invPendingCard => 'Sizə dəvət var';

  @override
  String get invPendingCardBody => 'Dəvəti açıb qəbul edin.';

  @override
  String get cxTitle => 'Kompleksim';

  @override
  String get cxMyComplexes => 'Komplekslərim';

  @override
  String get cxEntrySubtitle => 'Cihaz seçin və abunə olun';

  @override
  String get cxNoComplexes => 'Hələ heç bir kompleksin sakini deyilsiniz.';

  @override
  String get cxDevicesTitle => 'Kompleks cihazları';

  @override
  String get cxNoDevices => 'Kompleksdə hələ cihaz yoxdur.';

  @override
  String get cxStatusNone => 'Abunə deyil';

  @override
  String get cxStatusPending => 'Ödəniş gözləyir';

  @override
  String get cxStatusActive => 'Aktiv';

  @override
  String get cxStatusExpired => 'Bitib';

  @override
  String cxPrice(String price, int days) {
    return '$price / $days gün';
  }

  @override
  String get cxPriceNote => 'Abunəlik bu cihaza girişinizi aktivləşdirir.';

  @override
  String get cxSubscriptionBlock => 'Aylıq abunəlik';

  @override
  String get cxSubscribe => 'Abunə ol';

  @override
  String get cxFinishPayment => 'Ödənişi tamamla';

  @override
  String get cxRenew => 'Yenilə';

  @override
  String get cxOpenInDevices => 'Cihazlar bölməsində aç';

  @override
  String get cxDeviceTitle => 'Cihaz';

  @override
  String get cxErrAlreadyActive => 'Bu cihaz üçün abunəliyiniz artıq aktivdir.';

  @override
  String get payPendingTitle => 'Ödəniş gözləyənlər';

  @override
  String payPendingCard(int count) {
    return 'Ödəniş gözləyən abunəlik: $count';
  }

  @override
  String get payPendingNone => 'Ödəniş gözləyən abunəliyiniz yoxdur.';

  @override
  String get payPendingMain => 'Sakin abunəliyi';

  @override
  String get payPendingAdditional => 'Ailə üzvü abunəliyi';

  @override
  String get payNow => 'Ödə';

  @override
  String payDeviceFallback(int id) {
    return 'Cihaz #$id';
  }

  @override
  String get payErrForbidden => 'Bu əməliyyat üçün icazəniz yoxdur.';

  @override
  String get famTitle => 'Ailə üzvləri';

  @override
  String get famEntrySubtitle => 'Dəvət, giriş və ödənişlər';

  @override
  String get famTabMembers => 'Üzvlər';

  @override
  String get famTabInvitations => 'Dəvətlər';

  @override
  String get famInvite => 'Ailə üzvü dəvət et';

  @override
  String get famNotHead =>
      'Ailə üzvü dəvət etmək üçün öz cihazınızda aktiv girişiniz olmalıdır. Ailə üzvü kimi əlavə olunduğunuz cihazlarda bu bölmə əlçatan deyil.';

  @override
  String get famErrNotHead =>
      'Bu cihazda ailə üzvlərini idarə etmək hüququnuz yoxdur.';

  @override
  String get famErrInvalidTarget =>
      'Bu şəxsə dəvət göndərmək olmaz (özünüz və ya artıq bu cihazda olan şəxs).';

  @override
  String get famNoMembers => 'Hələ ailə üzvünüz yoxdur.';

  @override
  String get famNoInvitations => 'Hələ ailə dəvəti yoxdur.';

  @override
  String get famDeviceLabel => 'Cihaz';

  @override
  String get famInviteIntro =>
      'Seçdiyiniz cihaz üçün email ilə 7 gün etibarlı dəvət göndəriləcək. Hər ailə üzvünün öz aylıq abunəliyi olur; onu siz və ya üzvün özü ödəyə bilər.';

  @override
  String get famGranted =>
      'Ailə üzvünə bu cihaz üçün giriş verildi. Abunəlik ödənişi gözləyir.';

  @override
  String get famSubNone => 'Abunəlik yoxdur';

  @override
  String famSubActiveUntil(String date) {
    return 'Aktiv: $date tarixinədək';
  }

  @override
  String get famPayFor => 'Üzv üçün ödə';

  @override
  String get famRemove => 'Çıxar';

  @override
  String get famRemoveTitle => 'Ailə üzvünü çıxar';

  @override
  String famRemoveBody(String name) {
    return '$name ailə üzvlərindən çıxarılacaq. Verdiyiniz cihazlara girişi dərhal dayanacaq, abunəlikləri ləğv ediləcək. Avtomatik geri ödəniş edilmir.';
  }

  @override
  String get famRemoved => 'Ailə üzvü çıxarıldı.';
}
