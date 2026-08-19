// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Russian (`ru`).
class AppLocalizationsRu extends AppLocalizations {
  AppLocalizationsRu([String locale = 'ru']) : super(locale);

  @override
  String get appTitle => 'Salam Həyətimiz';

  @override
  String get register => 'Регистрация';

  @override
  String get login => 'Вход';

  @override
  String get verifyOtp => 'Подтверждение OTP';

  @override
  String get continueLabel => 'Продолжить';

  @override
  String get home => 'Главная';

  @override
  String get devices => 'Устройства';

  @override
  String get profile => 'Профиль';

  @override
  String get settings => 'Настройки';

  @override
  String get theme => 'Тема';

  @override
  String get language => 'Язык';

  @override
  String get logout => 'Выход';

  @override
  String get welcomeTagline => 'Ваш двор в одно касание';

  @override
  String get registerTitle => 'Создать аккаунт';

  @override
  String get firstName => 'Имя';

  @override
  String get lastName => 'Фамилия';

  @override
  String get phoneNumber => 'Номер телефона';

  @override
  String get emailAddress => 'Эл. почта';

  @override
  String get phoneHint => '+994XXXXXXXXX';

  @override
  String get emailHint => 'you@example.com';

  @override
  String get registerSubmit => 'Зарегистрироваться';

  @override
  String get alreadyHaveAccount => 'Уже есть аккаунт? Войти';

  @override
  String get dontHaveAccount => 'Нет аккаунта? Зарегистрироваться';

  @override
  String get loginTitle => 'Вход';

  @override
  String get loginSubmit => 'Отправить код входа';

  @override
  String get otpTitle => 'Введите код';

  @override
  String otpSentTo(String email) {
    return 'Код отправлен на $email';
  }

  @override
  String get otpFieldHint => '6-значный код';

  @override
  String get verifySubmit => 'Подтвердить';

  @override
  String get resend => 'Отправить снова';

  @override
  String resendIn(int seconds) {
    return 'Отправить снова ($secondsс)';
  }

  @override
  String get maintenanceTitle => 'Технические работы';

  @override
  String get maintenanceMessage =>
      'Приложение временно недоступно. Повторите попытку позже.';

  @override
  String get forceUpdateTitle => 'Требуется обновление';

  @override
  String get forceUpdateMessage => 'Обновите приложение, чтобы продолжить.';

  @override
  String get updateNow => 'Обновить';

  @override
  String get retry => 'Повторить';

  @override
  String get sessionExpired => 'Сессия истекла. Войдите снова.';

  @override
  String get errWrongCode => 'Неверный код подтверждения.';

  @override
  String get errOtpExpired => 'Срок действия кода истёк. Запросите новый.';

  @override
  String get errOtpMaxAttempts =>
      'Слишком много неверных попыток. Запросите новый код.';

  @override
  String get errEmailAlreadyRegistered =>
      'Эта почта уже зарегистрирована. Войдите.';

  @override
  String errRateLimited(int seconds) {
    return 'Слишком много запросов. Повторите через $secondsс.';
  }

  @override
  String get errNetwork => 'Нет подключения к интернету.';

  @override
  String get errTimeout => 'Истекло время ожидания.';

  @override
  String get errServer => 'Ошибка сервера. Повторите попытку позже.';

  @override
  String get errUnknown => 'Что-то пошло не так.';

  @override
  String get errValidation => 'Проверьте введённые данные.';

  @override
  String get vRequired => 'Обязательное поле';

  @override
  String get vEmail => 'Введите корректную почту';

  @override
  String get vPhone => 'Телефон в формате +994XXXXXXXXX';

  @override
  String homeGreeting(String name) {
    return 'Привет, $name!';
  }

  @override
  String get homeUser => 'Пользователь';

  @override
  String get homeActiveDevices => 'Активные устройства';

  @override
  String get homeActiveSubscriptions => 'Активные подписки';

  @override
  String get homeLastActivity => 'Последняя активность';

  @override
  String get homeQuickOpen => 'Открыть';

  @override
  String get devicesTitle => 'Мои устройства';

  @override
  String get devicesEmpty => 'У вас нет устройств';

  @override
  String get deviceOnline => 'В сети';

  @override
  String get deviceOffline => 'Не в сети';

  @override
  String get deviceUnknownStatus => 'Неизвестно';

  @override
  String get deviceInfoTitle => 'Информация об устройстве';

  @override
  String get deviceAddress => 'Адрес';

  @override
  String get deviceImei => 'IMEI';

  @override
  String get deviceLastOnlineLabel => 'Последний онлайн';

  @override
  String get notifications => 'Уведомления';

  @override
  String get notificationsEmpty => 'Уведомлений пока нет';

  @override
  String get notificationsEmptyHint => 'Новые уведомления появятся здесь.';

  @override
  String get notificationsMarkAllRead => 'Отметить все как прочитанные';

  @override
  String get profilePersonalInfo => 'Личные данные';

  @override
  String get profileResidence => 'Жилой комплекс';

  @override
  String get profileAppSettings => 'Настройки приложения';

  @override
  String get profileHelp => 'Помощь и поддержка';

  @override
  String get fieldFirstName => 'Имя';

  @override
  String get fieldLastName => 'Фамилия';

  @override
  String get fieldPhone => 'Телефон';

  @override
  String get fieldEmail => 'Эл. почта';

  @override
  String get helpDescription => 'Свяжитесь с нами по вопросам и поддержке.';

  @override
  String get residenceEmpty => 'Нет назначенных барьеров.';

  @override
  String appVersionLabel(String version) {
    return 'Версия $version';
  }

  @override
  String get deviceAddressMissing => 'Адрес не указан.';

  @override
  String deviceLastOnline(String time) {
    return 'Был в сети: $time';
  }

  @override
  String get deviceStatus => 'Статус';

  @override
  String get deviceRole => 'Роль';

  @override
  String get deviceRoleOwner => 'Владелец';

  @override
  String get deviceRoleUser => 'Житель';

  @override
  String get deviceModel => 'Модель';

  @override
  String get deviceSerial => 'Серийный номер';

  @override
  String get deviceSubscription => 'Подписка';

  @override
  String get deviceSubscriptionActive => 'Активна';

  @override
  String get activeSubscriptionsTitle => 'Активные подписки';

  @override
  String get subscriptionsEmpty => 'У вас нет активных подписок';

  @override
  String get subscriptionTierMain => 'Основная подписка';

  @override
  String get subscriptionTierAdditional => 'Дополнительная подписка';

  @override
  String get subscriptionStart => 'Начало';

  @override
  String get subscriptionEnd => 'Окончание';

  @override
  String subscriptionDaysLeft(int days) {
    return 'Осталось $days дн.';
  }

  @override
  String get subscriptionExpiresToday => 'Истекает сегодня';

  @override
  String get barrierOpen => 'Открыть';

  @override
  String get barrierSending => 'Отправка…';

  @override
  String get barrierPending => 'Открывается…';

  @override
  String get barrierSuccessOpened => 'Ворота открыты';

  @override
  String get barrierSuccessSent => 'Команда отправлена';

  @override
  String get barrierFailed => 'Не удалось открыть';

  @override
  String get barrierTimeout => 'Время вышло — нет ответа от устройства';

  @override
  String barrierCooldown(int seconds) {
    return 'Слишком много попыток. Повторите через $secondsс';
  }

  @override
  String get barrierGateMovedQuestion => 'Ворота открылись?';

  @override
  String get barrierClose => 'Закрыть';

  @override
  String get barrierCloseSending => 'Отправка…';

  @override
  String get barrierClosePending => 'Закрывается…';

  @override
  String get barrierCloseSuccessClosed => 'Ворота закрыты';

  @override
  String get barrierCloseSuccessSent => 'Команда отправлена';

  @override
  String get barrierCloseFailed => 'Не удалось закрыть';

  @override
  String get yes => 'Да';

  @override
  String get no => 'Нет';

  @override
  String get errDeviceOffline => 'Устройство не в сети — команда не отправлена';

  @override
  String get errAccessDenied => 'Нет доступа к этому устройству';

  @override
  String get errSubscriptionRequired => 'Ваша подписка не активна';

  @override
  String get errDeviceDisabled => 'Устройство отключено';

  @override
  String get errWhitelist => 'Устройство отклонило команду (whitelist)';

  @override
  String get errNotFound => 'Не найдено';

  @override
  String get barrierLocating => 'Определение местоположения…';

  @override
  String get errLocationRequired => 'Для открытия нужно местоположение.';

  @override
  String get errOutsideGeofence => 'Вы слишком далеко от шлагбаума.';

  @override
  String get errLocationImprecise => 'Местоположение недостаточно точное.';

  @override
  String get errLocationPermissionDenied =>
      'Для открытия нужен доступ к местоположению.';

  @override
  String get errLocationPermissionPermanent =>
      'Доступ к местоположению отключён. Включите его в настройках.';

  @override
  String get errLocationServiceDisabled =>
      'Геолокация (GPS) отключена. Включите её, чтобы открыть.';

  @override
  String get errLocationTimeout =>
      'Не удалось определить местоположение. Повторите попытку.';

  @override
  String get locationOpenSettings => 'Открыть настройки';

  @override
  String get directions => 'Маршрут';

  @override
  String get inviteVisitor => 'Пригласить';

  @override
  String get directionsNoLocation =>
      'Для этого шлагбаума не задано местоположение';

  @override
  String get directionsNoApp => 'Приложение навигации не найдено';

  @override
  String get directionsChooseApp => 'Выберите приложение';

  @override
  String get directionsFailed => 'Не удалось открыть навигацию';

  @override
  String get azNavRequiredTitle => 'Требуется AzNav';

  @override
  String get azNavRequiredMessage =>
      'Установите приложение AzNav, чтобы пользоваться маршрутами.';

  @override
  String get azNavInstall => 'Установить AzNav';

  @override
  String get cancel => 'Отмена';

  @override
  String get visitorInviteTitle => 'Пригласить гостя';

  @override
  String get visitorAccessOneTime => 'Разовый';

  @override
  String get visitorAccessTimeLimited => 'На время';

  @override
  String get visitorDurationLabel => 'Срок';

  @override
  String get visitorNameLabel => 'Имя гостя (необязательно)';

  @override
  String get visitorPurposeLabel => 'Цель (необязательно)';

  @override
  String get visitorPurposeGuest => 'Гость';

  @override
  String get visitorPurposeDelivery => 'Доставка';

  @override
  String get visitorPurposeCourier => 'Курьер';

  @override
  String get visitorPurposeService => 'Сервис';

  @override
  String get visitorPurposeCleaning => 'Уборка';

  @override
  String get visitorPurposeTaxi => 'Такси';

  @override
  String get visitorPurposeOther => 'Другое';

  @override
  String get visitorGenerate => 'Создать ссылку';

  @override
  String get visitorLinkReady => 'Ссылка готова';

  @override
  String get visitorShare => 'Поделиться';

  @override
  String get visitorCopy => 'Копировать';

  @override
  String get visitorCopied => 'Скопировано';

  @override
  String get visitorDone => 'Закрыть';

  @override
  String visitorMinutesShort(int count) {
    return '$count мин';
  }

  @override
  String visitorHoursShort(int count) {
    return '$count ч';
  }

  @override
  String get doorWidgetTitle => 'Виджет на главном экране';

  @override
  String get doorWidgetIntro =>
      'Выберите шлагбаум для виджета на главном экране. Название выбранного шлагбаума появится на виджете.';

  @override
  String doorWidgetSelected(String label) {
    return '$label назначен для виджета';
  }

  @override
  String get doorWidgetClear => 'Удалить выбор виджета';

  @override
  String get doorWidgetCleared => 'Выбор виджета удалён';

  @override
  String get doorWidgetUnconfigured => 'Дверь не выбрана';

  @override
  String get doorWidgetAddHint =>
      'Добавьте виджет «Открыть дверь» на главный экран, затем выберите дверь.';

  @override
  String doorWidgetInstance(int id) {
    return 'Виджет #$id';
  }
}
