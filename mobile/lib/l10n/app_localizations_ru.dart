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

  @override
  String get homeInvitations => 'Мои приглашения';

  @override
  String get invitationsTitle => 'Мои приглашения';

  @override
  String get invitationsEmpty => 'Вы ещё не отправили ни одного приглашения.';

  @override
  String get invitationFilterAll => 'Все';

  @override
  String get invitationFilterActive => 'Активные';

  @override
  String get invitationFilterUsed => 'Использованные';

  @override
  String get invitationFilterExpired => 'Истёкшие';

  @override
  String get invitationFilterRevoked => 'Отозванные';

  @override
  String get invitationStatusActive => 'Активно';

  @override
  String get invitationStatusUsed => 'Использовано';

  @override
  String get invitationStatusExpired => 'Истекло';

  @override
  String get invitationStatusRevoked => 'Отозвано';

  @override
  String get invitationSentAt => 'Отправлено';

  @override
  String get invitationDuration => 'Срок';

  @override
  String get invitationExpiresAt => 'Истекает';

  @override
  String invitationRemaining(String time) {
    return 'Осталось $time';
  }

  @override
  String get invitationUsedAt => 'Использовано';

  @override
  String get invitationFirstUsedAt => 'Первое использование';

  @override
  String get invitationLastUsedAt => 'Последнее использование';

  @override
  String get invitationUsageCount => 'Использований';

  @override
  String invitationUsageValue(int count) {
    return '$count раз';
  }

  @override
  String get invitationUnlimited => 'Без лимита';

  @override
  String get invitationDefaultTitle => 'Приглашение';

  @override
  String get checkoutTitle => 'Оплата';

  @override
  String get paymentTestBanner => 'ТЕСТОВАЯ ОПЛАТА';

  @override
  String get paymentResultTitle => 'Результат оплаты';

  @override
  String get paymentSuccess => 'Оплата прошла успешно';

  @override
  String get paymentFailed => 'Оплата не прошла';

  @override
  String get paymentCancelled => 'Оплата отменена';

  @override
  String get paymentExpired => 'Время оплаты истекло';

  @override
  String get paymentPending => 'Оплата обрабатывается';

  @override
  String get paymentChecking => 'Подтверждаем в банке…';

  @override
  String get paymentRecheck => 'Проверить снова';

  @override
  String get paymentDone => 'На главную';

  @override
  String get paymentOrderNotFound => 'Заказ не найден';

  @override
  String get subscriptionRenew => 'Продлить (12 AZN / 30 дней)';

  @override
  String get subscriptionRenewNotEligible =>
      'Эту подписку сейчас нельзя продлить.';

  @override
  String get regTypeTitle => 'Тип регистрации';

  @override
  String get regTypePhysical => 'Физическое лицо';

  @override
  String get regTypePhysicalBody =>
      'Хочу подключить устройство для своего частного двора.';

  @override
  String get regTypeLegal => 'Юридическое лицо';

  @override
  String get regTypeLegalBody => 'Жилой комплекс, здание или компания.';

  @override
  String get appMineTitle => 'Мои заявки';

  @override
  String get appNone => 'У вас пока нет заявок';

  @override
  String get appNewPhysical => 'Новая заявка (физ. лицо)';

  @override
  String get appNewLegal => 'Новая заявка (юр. лицо)';

  @override
  String get appSectionPhysical => 'Физическое лицо';

  @override
  String get appSectionLegal => 'Юридическое лицо';

  @override
  String get appPhysicalTitle => 'Заявка физического лица';

  @override
  String get appPhysicalIntro =>
      'Укажите, где находится ваш двор — мы свяжемся с вами по установке.';

  @override
  String get appLegalTitle => 'Заявка юридического лица';

  @override
  String get appLegalIntro =>
      'Данные комплекса и местоположение. Заявку рассматривает администратор.';

  @override
  String get appFullName => 'Имя и фамилия';

  @override
  String get appPhone => 'Телефон';

  @override
  String get appEmail => 'Email';

  @override
  String get appAddress => 'Адрес';

  @override
  String get appNote => 'Примечание (необязательно)';

  @override
  String get appComplexName => 'Название комплекса';

  @override
  String get appLegalName => 'Юридическое название';

  @override
  String get appVoen => 'ИНН (VÖEN)';

  @override
  String get appLegalAddress => 'Юридический адрес';

  @override
  String get appContactName => 'Контактное лицо';

  @override
  String get appComplexAddress => 'Адрес комплекса';

  @override
  String get appApartments => 'Количество квартир (необязательно)';

  @override
  String get appLocationLabel => 'Местоположение на карте';

  @override
  String get appLocationHint => 'Коснитесь карты, чтобы поставить метку';

  @override
  String get appUseMyLocation => 'Моё местоположение';

  @override
  String get appLocationRequired => 'Выберите местоположение на карте';

  @override
  String get appLocationOutside =>
      'Местоположение должно быть на территории Азербайджана';

  @override
  String get appVoenInvalid => 'ИНН должен состоять из 10 цифр';

  @override
  String get appApartmentsInvalid => 'Введите корректное число';

  @override
  String get appSubmit => 'Отправить заявку';

  @override
  String get appLater => 'Заполню позже';

  @override
  String get appSubmitted => 'Заявка отправлена';

  @override
  String get appStatusNew => 'Новая';

  @override
  String get appStatusContacted => 'Связались';

  @override
  String get appStatusInProgress => 'В работе';

  @override
  String get appStatusInstalled => 'Установлено';

  @override
  String get appStatusPending => 'На рассмотрении';

  @override
  String get appStatusApproved => 'Одобрена';

  @override
  String get appStatusRejected => 'Отклонена';

  @override
  String get appRejectReason => 'Причина';

  @override
  String get appErrTypeMismatch =>
      'Тип вашего аккаунта не соответствует типу заявки.';

  @override
  String get appErrAlreadyOpen => 'У вас уже есть открытая заявка.';

  @override
  String get appErrDuplicateVoen => 'Заявка с этим ИНН уже рассматривается.';

  @override
  String get kmTitle => 'Мой комплекс (Комендант)';

  @override
  String get kmEntrySubtitle => 'Жильцы, приглашения и устройства';

  @override
  String get kmStatDevices => 'Устройства';

  @override
  String get kmStatResidents => 'Жильцы';

  @override
  String get kmStatPending => 'Ожидающие';

  @override
  String get kmDevicesTitle => 'Устройства комплекса';

  @override
  String get kmNoDevices => 'К комплексу ещё не привязаны устройства.';

  @override
  String get kmOnline => 'Онлайн';

  @override
  String get kmOffline => 'Офлайн';

  @override
  String kmDevicePrice(String price, int days) {
    return 'Подписка: $price / $days дн.';
  }

  @override
  String get kmInvite => 'Пригласить жильца';

  @override
  String get kmInviteIntro =>
      'Жилец получит по email ссылку-приглашение, действующую 7 дней.';

  @override
  String get kmFirstName => 'Имя';

  @override
  String get kmLastName => 'Фамилия';

  @override
  String get kmEmail => 'Email';

  @override
  String get kmInviteSend => 'Отправить приглашение';

  @override
  String get kmInviteSent => 'Приглашение отправлено.';

  @override
  String get kmInvitations => 'Приглашения';

  @override
  String get kmTabPending => 'Ожидают';

  @override
  String get kmTabAccepted => 'Приняты';

  @override
  String get kmTabExpired => 'Истекли';

  @override
  String get kmTabClosed => 'Отменены';

  @override
  String get kmStatusDeclined => 'Отклонено';

  @override
  String get kmNoInvitations => 'Здесь нет приглашений.';

  @override
  String kmExpiresAt(String date) {
    return 'Истекает: $date';
  }

  @override
  String kmAcceptedAt(String date) {
    return 'Принято: $date';
  }

  @override
  String kmSendCount(int count) {
    return 'Отправлено: $count раз';
  }

  @override
  String get kmResend => 'Отправить снова';

  @override
  String get kmRevoke => 'Отменить';

  @override
  String get kmResent => 'Приглашение отправлено повторно.';

  @override
  String get kmRevoked => 'Приглашение отменено.';

  @override
  String get kmRevokeTitle => 'Отменить приглашение?';

  @override
  String get kmRevokeBody => 'Ссылка-приглашение перестанет работать.';

  @override
  String get kmResidentsTitle => 'Жильцы';

  @override
  String get kmNoResidents => 'В комплексе пока нет жильцов.';

  @override
  String kmActiveSubs(int count) {
    return 'Активные подписки: $count';
  }

  @override
  String get kmNoActiveSub => 'Нет активной подписки';

  @override
  String kmJoinedAt(String date) {
    return 'Присоединился: $date';
  }

  @override
  String get kmRemove => 'Удалить';

  @override
  String get kmRemoveTitle => 'Удалить жильца из комплекса';

  @override
  String kmRemoveBody(String name) {
    return '$name будет удалён(а) из комплекса. Доступ к устройствам комплекса сразу прекратится, активные подписки будут отменены. Автоматический возврат средств не производится.';
  }

  @override
  String get kmRemoved => 'Жилец удалён из комплекса.';

  @override
  String get kmErrNotKomendant =>
      'Этот раздел только для коменданта комплекса.';

  @override
  String get kmErrForbidden => 'У вас нет прав на это действие.';

  @override
  String get kmErrAlreadyResident =>
      'Этот email уже принадлежит жильцу комплекса.';

  @override
  String get kmErrAlreadyPending =>
      'Для этого email уже есть активное приглашение.';

  @override
  String get kmErrNotResendable => 'Это приглашение нельзя отправить повторно.';

  @override
  String get kmErrNotRevocable => 'Это приглашение нельзя отменить.';

  @override
  String get kmErrRateLimited => 'Слишком часто. Попробуйте чуть позже.';

  @override
  String get kmErrNotFound => 'Не найдено. Список обновлён.';

  @override
  String get invTitle => 'Приглашение';

  @override
  String get invComplexKind => 'Приглашение в жилой комплекс';

  @override
  String get invFamilyKind => 'Приглашение члена семьи';

  @override
  String invComplexBody(String complex) {
    return 'Вас пригласили в $complex как жильца. После принятия вы сможете выбрать устройство и оформить подписку.';
  }

  @override
  String invFamilyBody(String inviter) {
    return '$inviter пригласил(а) вас как члена семьи для доступа к устройству. Доступ активируется после оплаты вашей подписки.';
  }

  @override
  String invFor(String name) {
    return 'Приглашённый: $name';
  }

  @override
  String invSentTo(String email) {
    return 'Приглашение отправлено на $email. Продолжайте с этим email.';
  }

  @override
  String invExpires(String date) {
    return 'Действительно до $date';
  }

  @override
  String get invRegister => 'Зарегистрироваться';

  @override
  String get invLogin => 'У меня есть аккаунт — войти';

  @override
  String get invAccept => 'Принять приглашение';

  @override
  String get invDecline => 'Отклонить';

  @override
  String get invDeclineTitle => 'Отклонить приглашение?';

  @override
  String get invDeclineBody => 'Эта ссылка-приглашение перестанет работать.';

  @override
  String get invDeclined => 'Приглашение отклонено.';

  @override
  String invAcceptedComplex(String complex) {
    return 'Приглашение принято. Добро пожаловать в $complex!';
  }

  @override
  String get invAcceptedFamily =>
      'Приглашение принято. Оплатите подписку, чтобы активировать доступ.';

  @override
  String get invGoneTitle => 'Приглашение недействительно';

  @override
  String get invGoneBody =>
      'Срок действия ссылки истёк, она отозвана или уже использована. Попросите отправителя о новом приглашении.';

  @override
  String get invClose => 'Закрыть';

  @override
  String get invErrEmailMismatch =>
      'Это приглашение для другого email. Войдите с приглашённым email.';

  @override
  String get invErrAlreadyHasAccess =>
      'У вас уже есть доступ к этому устройству.';

  @override
  String get invErrInvalidTarget => 'Нельзя принять собственное приглашение.';

  @override
  String get invErrUnsupported => 'Этот тип приглашения не поддерживается.';

  @override
  String get invRegisterHint =>
      'Зарегистрируйтесь с приглашённым email; приглашение будет принято после подтверждения аккаунта.';

  @override
  String get invPendingCard => 'У вас есть приглашение';

  @override
  String get invPendingCardBody => 'Откройте и примите его.';

  @override
  String get cxTitle => 'Мой комплекс';

  @override
  String get cxMyComplexes => 'Мои комплексы';

  @override
  String get cxEntrySubtitle => 'Выберите устройство и оформите подписку';

  @override
  String get cxNoComplexes =>
      'Вы пока не являетесь жильцом ни одного комплекса.';

  @override
  String get cxDevicesTitle => 'Устройства комплекса';

  @override
  String get cxNoDevices => 'В комплексе пока нет устройств.';

  @override
  String get cxStatusNone => 'Нет подписки';

  @override
  String get cxStatusPending => 'Ожидает оплаты';

  @override
  String get cxStatusActive => 'Активна';

  @override
  String get cxStatusExpired => 'Истекла';

  @override
  String cxPrice(String price, int days) {
    return '$price / $days дн.';
  }

  @override
  String get cxPriceNote =>
      'Подписка активирует ваш доступ к этому устройству.';

  @override
  String get cxSubscriptionBlock => 'Ежемесячная подписка';

  @override
  String get cxSubscribe => 'Оформить подписку';

  @override
  String get cxFinishPayment => 'Завершить оплату';

  @override
  String get cxRenew => 'Продлить';

  @override
  String get cxOpenInDevices => 'Открыть в разделе «Устройства»';

  @override
  String get cxDeviceTitle => 'Устройство';

  @override
  String get cxErrAlreadyActive =>
      'Ваша подписка на это устройство уже активна.';

  @override
  String get payPendingTitle => 'Ожидают оплаты';

  @override
  String payPendingCard(int count) {
    return 'Подписок ожидают оплаты: $count';
  }

  @override
  String get payPendingNone => 'Нет подписок, ожидающих оплаты.';

  @override
  String get payPendingMain => 'Подписка жильца';

  @override
  String get payPendingAdditional => 'Подписка члена семьи';

  @override
  String get payNow => 'Оплатить';

  @override
  String payDeviceFallback(int id) {
    return 'Устройство #$id';
  }

  @override
  String get payErrForbidden => 'У вас нет прав на это действие.';

  @override
  String get famTitle => 'Члены семьи';

  @override
  String get famEntrySubtitle => 'Приглашения, доступ и оплаты';

  @override
  String get famTabMembers => 'Участники';

  @override
  String get famTabInvitations => 'Приглашения';

  @override
  String get famInvite => 'Пригласить члена семьи';

  @override
  String get famNotHead =>
      'Чтобы приглашать членов семьи, нужен собственный активный доступ к устройству. На устройствах, куда вас добавили как члена семьи, это недоступно.';

  @override
  String get famErrNotHead =>
      'Вы не можете управлять членами семьи на этом устройстве.';

  @override
  String get famErrInvalidTarget =>
      'Этого человека нельзя пригласить (вы сами или он уже есть на устройстве).';

  @override
  String get famNoMembers => 'Пока нет членов семьи.';

  @override
  String get famNoInvitations => 'Пока нет семейных приглашений.';

  @override
  String get famDeviceLabel => 'Устройство';

  @override
  String get famInviteIntro =>
      'Для выбранного устройства будет отправлено приглашение на 7 дней. У каждого члена семьи своя ежемесячная подписка — её оплачиваете вы или сам участник.';

  @override
  String get famGranted =>
      'Члену семьи выдан доступ к устройству. Подписка ожидает оплаты.';

  @override
  String get famSubNone => 'Нет подписки';

  @override
  String famSubActiveUntil(String date) {
    return 'Активна до $date';
  }

  @override
  String get famPayFor => 'Оплатить за участника';

  @override
  String get famRemove => 'Удалить';

  @override
  String get famRemoveTitle => 'Удалить члена семьи';

  @override
  String famRemoveBody(String name) {
    return '$name будет удалён(а) из семьи. Доступ к выданным устройствам сразу прекратится, подписки будут отменены. Автоматический возврат не производится.';
  }

  @override
  String get famRemoved => 'Член семьи удалён.';
}
