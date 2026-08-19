import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_az.dart';
import 'app_localizations_en.dart';
import 'app_localizations_ru.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('az'),
    Locale('en'),
    Locale('ru'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'Salam Həyətimiz'**
  String get appTitle;

  /// No description provided for @register.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get register;

  /// No description provided for @login.
  ///
  /// In en, this message translates to:
  /// **'Login'**
  String get login;

  /// No description provided for @verifyOtp.
  ///
  /// In en, this message translates to:
  /// **'Verify OTP'**
  String get verifyOtp;

  /// No description provided for @continueLabel.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get continueLabel;

  /// No description provided for @home.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get home;

  /// No description provided for @devices.
  ///
  /// In en, this message translates to:
  /// **'Devices'**
  String get devices;

  /// No description provided for @profile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profile;

  /// No description provided for @settings.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settings;

  /// No description provided for @theme.
  ///
  /// In en, this message translates to:
  /// **'Theme'**
  String get theme;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @logout.
  ///
  /// In en, this message translates to:
  /// **'Logout'**
  String get logout;

  /// No description provided for @welcomeTagline.
  ///
  /// In en, this message translates to:
  /// **'Your yard, one tap away'**
  String get welcomeTagline;

  /// No description provided for @registerTitle.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get registerTitle;

  /// No description provided for @firstName.
  ///
  /// In en, this message translates to:
  /// **'First name'**
  String get firstName;

  /// No description provided for @lastName.
  ///
  /// In en, this message translates to:
  /// **'Last name'**
  String get lastName;

  /// No description provided for @phoneNumber.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get phoneNumber;

  /// No description provided for @emailAddress.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get emailAddress;

  /// No description provided for @phoneHint.
  ///
  /// In en, this message translates to:
  /// **'+994XXXXXXXXX'**
  String get phoneHint;

  /// No description provided for @emailHint.
  ///
  /// In en, this message translates to:
  /// **'you@example.com'**
  String get emailHint;

  /// No description provided for @registerSubmit.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get registerSubmit;

  /// No description provided for @alreadyHaveAccount.
  ///
  /// In en, this message translates to:
  /// **'Already have an account? Log in'**
  String get alreadyHaveAccount;

  /// No description provided for @dontHaveAccount.
  ///
  /// In en, this message translates to:
  /// **'Don\'t have an account? Register'**
  String get dontHaveAccount;

  /// No description provided for @loginTitle.
  ///
  /// In en, this message translates to:
  /// **'Log in'**
  String get loginTitle;

  /// No description provided for @loginSubmit.
  ///
  /// In en, this message translates to:
  /// **'Send login code'**
  String get loginSubmit;

  /// No description provided for @otpTitle.
  ///
  /// In en, this message translates to:
  /// **'Enter the code'**
  String get otpTitle;

  /// No description provided for @otpSentTo.
  ///
  /// In en, this message translates to:
  /// **'We sent a code to {email}'**
  String otpSentTo(String email);

  /// No description provided for @otpFieldHint.
  ///
  /// In en, this message translates to:
  /// **'6-digit code'**
  String get otpFieldHint;

  /// No description provided for @verifySubmit.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verifySubmit;

  /// No description provided for @resend.
  ///
  /// In en, this message translates to:
  /// **'Resend code'**
  String get resend;

  /// No description provided for @resendIn.
  ///
  /// In en, this message translates to:
  /// **'Resend in {seconds}s'**
  String resendIn(int seconds);

  /// No description provided for @maintenanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Under maintenance'**
  String get maintenanceTitle;

  /// No description provided for @maintenanceMessage.
  ///
  /// In en, this message translates to:
  /// **'The app is temporarily unavailable. Please try again shortly.'**
  String get maintenanceMessage;

  /// No description provided for @forceUpdateTitle.
  ///
  /// In en, this message translates to:
  /// **'Update required'**
  String get forceUpdateTitle;

  /// No description provided for @forceUpdateMessage.
  ///
  /// In en, this message translates to:
  /// **'Please update the app to continue.'**
  String get forceUpdateMessage;

  /// No description provided for @updateNow.
  ///
  /// In en, this message translates to:
  /// **'Update now'**
  String get updateNow;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @sessionExpired.
  ///
  /// In en, this message translates to:
  /// **'Your session has expired. Please log in again.'**
  String get sessionExpired;

  /// No description provided for @errWrongCode.
  ///
  /// In en, this message translates to:
  /// **'The verification code is incorrect.'**
  String get errWrongCode;

  /// No description provided for @errOtpExpired.
  ///
  /// In en, this message translates to:
  /// **'The code has expired. Request a new one.'**
  String get errOtpExpired;

  /// No description provided for @errOtpMaxAttempts.
  ///
  /// In en, this message translates to:
  /// **'Too many incorrect attempts. Request a new code.'**
  String get errOtpMaxAttempts;

  /// No description provided for @errEmailAlreadyRegistered.
  ///
  /// In en, this message translates to:
  /// **'This email is already registered. Please log in.'**
  String get errEmailAlreadyRegistered;

  /// No description provided for @errRateLimited.
  ///
  /// In en, this message translates to:
  /// **'Too many requests. Try again in {seconds}s.'**
  String errRateLimited(int seconds);

  /// No description provided for @errNetwork.
  ///
  /// In en, this message translates to:
  /// **'No internet connection.'**
  String get errNetwork;

  /// No description provided for @errTimeout.
  ///
  /// In en, this message translates to:
  /// **'The connection timed out.'**
  String get errTimeout;

  /// No description provided for @errServer.
  ///
  /// In en, this message translates to:
  /// **'Server error. Please try again shortly.'**
  String get errServer;

  /// No description provided for @errUnknown.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong.'**
  String get errUnknown;

  /// No description provided for @errValidation.
  ///
  /// In en, this message translates to:
  /// **'Please check the entered details.'**
  String get errValidation;

  /// No description provided for @vRequired.
  ///
  /// In en, this message translates to:
  /// **'This field is required'**
  String get vRequired;

  /// No description provided for @vEmail.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email'**
  String get vEmail;

  /// No description provided for @vPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone must be in +994XXXXXXXXX format'**
  String get vPhone;

  /// No description provided for @homeGreeting.
  ///
  /// In en, this message translates to:
  /// **'Hello, {name}!'**
  String homeGreeting(String name);

  /// No description provided for @homeUser.
  ///
  /// In en, this message translates to:
  /// **'User'**
  String get homeUser;

  /// No description provided for @homeActiveDevices.
  ///
  /// In en, this message translates to:
  /// **'Active devices'**
  String get homeActiveDevices;

  /// No description provided for @homeActiveSubscriptions.
  ///
  /// In en, this message translates to:
  /// **'Active subscriptions'**
  String get homeActiveSubscriptions;

  /// No description provided for @homeLastActivity.
  ///
  /// In en, this message translates to:
  /// **'Last activity'**
  String get homeLastActivity;

  /// No description provided for @homeQuickOpen.
  ///
  /// In en, this message translates to:
  /// **'Open gate'**
  String get homeQuickOpen;

  /// No description provided for @devicesTitle.
  ///
  /// In en, this message translates to:
  /// **'My Devices'**
  String get devicesTitle;

  /// No description provided for @devicesEmpty.
  ///
  /// In en, this message translates to:
  /// **'You have no devices'**
  String get devicesEmpty;

  /// No description provided for @deviceOnline.
  ///
  /// In en, this message translates to:
  /// **'Online'**
  String get deviceOnline;

  /// No description provided for @deviceOffline.
  ///
  /// In en, this message translates to:
  /// **'Offline'**
  String get deviceOffline;

  /// No description provided for @deviceUnknownStatus.
  ///
  /// In en, this message translates to:
  /// **'Unknown'**
  String get deviceUnknownStatus;

  /// No description provided for @deviceInfoTitle.
  ///
  /// In en, this message translates to:
  /// **'Device info'**
  String get deviceInfoTitle;

  /// No description provided for @deviceAddress.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get deviceAddress;

  /// No description provided for @deviceImei.
  ///
  /// In en, this message translates to:
  /// **'IMEI'**
  String get deviceImei;

  /// No description provided for @deviceLastOnlineLabel.
  ///
  /// In en, this message translates to:
  /// **'Last online'**
  String get deviceLastOnlineLabel;

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @notificationsEmpty.
  ///
  /// In en, this message translates to:
  /// **'No notifications yet'**
  String get notificationsEmpty;

  /// No description provided for @notificationsEmptyHint.
  ///
  /// In en, this message translates to:
  /// **'New notifications will appear here.'**
  String get notificationsEmptyHint;

  /// No description provided for @notificationsMarkAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all as read'**
  String get notificationsMarkAllRead;

  /// No description provided for @profilePersonalInfo.
  ///
  /// In en, this message translates to:
  /// **'Personal information'**
  String get profilePersonalInfo;

  /// No description provided for @profileResidence.
  ///
  /// In en, this message translates to:
  /// **'Residential complex'**
  String get profileResidence;

  /// No description provided for @profileAppSettings.
  ///
  /// In en, this message translates to:
  /// **'App settings'**
  String get profileAppSettings;

  /// No description provided for @profileHelp.
  ///
  /// In en, this message translates to:
  /// **'Help & support'**
  String get profileHelp;

  /// No description provided for @fieldFirstName.
  ///
  /// In en, this message translates to:
  /// **'First name'**
  String get fieldFirstName;

  /// No description provided for @fieldLastName.
  ///
  /// In en, this message translates to:
  /// **'Last name'**
  String get fieldLastName;

  /// No description provided for @fieldPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get fieldPhone;

  /// No description provided for @fieldEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get fieldEmail;

  /// No description provided for @helpDescription.
  ///
  /// In en, this message translates to:
  /// **'Contact us for questions and support.'**
  String get helpDescription;

  /// No description provided for @residenceEmpty.
  ///
  /// In en, this message translates to:
  /// **'No barriers assigned.'**
  String get residenceEmpty;

  /// No description provided for @appVersionLabel.
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String appVersionLabel(String version);

  /// No description provided for @deviceAddressMissing.
  ///
  /// In en, this message translates to:
  /// **'No address provided.'**
  String get deviceAddressMissing;

  /// No description provided for @deviceLastOnline.
  ///
  /// In en, this message translates to:
  /// **'Last online: {time}'**
  String deviceLastOnline(String time);

  /// No description provided for @deviceStatus.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get deviceStatus;

  /// No description provided for @deviceRole.
  ///
  /// In en, this message translates to:
  /// **'Role'**
  String get deviceRole;

  /// No description provided for @deviceRoleOwner.
  ///
  /// In en, this message translates to:
  /// **'Owner'**
  String get deviceRoleOwner;

  /// No description provided for @deviceRoleUser.
  ///
  /// In en, this message translates to:
  /// **'Resident'**
  String get deviceRoleUser;

  /// No description provided for @deviceModel.
  ///
  /// In en, this message translates to:
  /// **'Model'**
  String get deviceModel;

  /// No description provided for @deviceSerial.
  ///
  /// In en, this message translates to:
  /// **'Serial'**
  String get deviceSerial;

  /// No description provided for @deviceSubscription.
  ///
  /// In en, this message translates to:
  /// **'Subscription'**
  String get deviceSubscription;

  /// No description provided for @deviceSubscriptionActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get deviceSubscriptionActive;

  /// No description provided for @activeSubscriptionsTitle.
  ///
  /// In en, this message translates to:
  /// **'Active subscriptions'**
  String get activeSubscriptionsTitle;

  /// No description provided for @subscriptionsEmpty.
  ///
  /// In en, this message translates to:
  /// **'You have no active subscriptions'**
  String get subscriptionsEmpty;

  /// No description provided for @subscriptionTierMain.
  ///
  /// In en, this message translates to:
  /// **'Main subscription'**
  String get subscriptionTierMain;

  /// No description provided for @subscriptionTierAdditional.
  ///
  /// In en, this message translates to:
  /// **'Additional subscription'**
  String get subscriptionTierAdditional;

  /// No description provided for @subscriptionStart.
  ///
  /// In en, this message translates to:
  /// **'Start'**
  String get subscriptionStart;

  /// No description provided for @subscriptionEnd.
  ///
  /// In en, this message translates to:
  /// **'Expiry'**
  String get subscriptionEnd;

  /// No description provided for @subscriptionDaysLeft.
  ///
  /// In en, this message translates to:
  /// **'{days} days left'**
  String subscriptionDaysLeft(int days);

  /// No description provided for @subscriptionExpiresToday.
  ///
  /// In en, this message translates to:
  /// **'Expires today'**
  String get subscriptionExpiresToday;

  /// No description provided for @barrierOpen.
  ///
  /// In en, this message translates to:
  /// **'Open the gate'**
  String get barrierOpen;

  /// No description provided for @barrierSending.
  ///
  /// In en, this message translates to:
  /// **'Sending…'**
  String get barrierSending;

  /// No description provided for @barrierPending.
  ///
  /// In en, this message translates to:
  /// **'Opening…'**
  String get barrierPending;

  /// No description provided for @barrierSuccessOpened.
  ///
  /// In en, this message translates to:
  /// **'The gate opened'**
  String get barrierSuccessOpened;

  /// No description provided for @barrierSuccessSent.
  ///
  /// In en, this message translates to:
  /// **'Command sent'**
  String get barrierSuccessSent;

  /// No description provided for @barrierFailed.
  ///
  /// In en, this message translates to:
  /// **'The gate could not be opened'**
  String get barrierFailed;

  /// No description provided for @barrierTimeout.
  ///
  /// In en, this message translates to:
  /// **'Timed out — no response from the device'**
  String get barrierTimeout;

  /// No description provided for @barrierCooldown.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Try again in {seconds}s'**
  String barrierCooldown(int seconds);

  /// No description provided for @barrierGateMovedQuestion.
  ///
  /// In en, this message translates to:
  /// **'Did the gate open?'**
  String get barrierGateMovedQuestion;

  /// No description provided for @barrierClose.
  ///
  /// In en, this message translates to:
  /// **'Close the gate'**
  String get barrierClose;

  /// No description provided for @barrierCloseSending.
  ///
  /// In en, this message translates to:
  /// **'Sending…'**
  String get barrierCloseSending;

  /// No description provided for @barrierClosePending.
  ///
  /// In en, this message translates to:
  /// **'Closing…'**
  String get barrierClosePending;

  /// No description provided for @barrierCloseSuccessClosed.
  ///
  /// In en, this message translates to:
  /// **'The gate closed'**
  String get barrierCloseSuccessClosed;

  /// No description provided for @barrierCloseSuccessSent.
  ///
  /// In en, this message translates to:
  /// **'Command sent'**
  String get barrierCloseSuccessSent;

  /// No description provided for @barrierCloseFailed.
  ///
  /// In en, this message translates to:
  /// **'The gate could not be closed'**
  String get barrierCloseFailed;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @no.
  ///
  /// In en, this message translates to:
  /// **'No'**
  String get no;

  /// No description provided for @errDeviceOffline.
  ///
  /// In en, this message translates to:
  /// **'Device offline — the command could not be sent'**
  String get errDeviceOffline;

  /// No description provided for @errAccessDenied.
  ///
  /// In en, this message translates to:
  /// **'You don\'t have access to this device'**
  String get errAccessDenied;

  /// No description provided for @errSubscriptionRequired.
  ///
  /// In en, this message translates to:
  /// **'Your subscription is not active'**
  String get errSubscriptionRequired;

  /// No description provided for @errDeviceDisabled.
  ///
  /// In en, this message translates to:
  /// **'The device is disabled'**
  String get errDeviceDisabled;

  /// No description provided for @errWhitelist.
  ///
  /// In en, this message translates to:
  /// **'The device rejected the command (whitelist)'**
  String get errWhitelist;

  /// No description provided for @errNotFound.
  ///
  /// In en, this message translates to:
  /// **'Not found'**
  String get errNotFound;

  /// No description provided for @barrierLocating.
  ///
  /// In en, this message translates to:
  /// **'Getting location…'**
  String get barrierLocating;

  /// No description provided for @errLocationRequired.
  ///
  /// In en, this message translates to:
  /// **'Location is required to open the gate.'**
  String get errLocationRequired;

  /// No description provided for @errOutsideGeofence.
  ///
  /// In en, this message translates to:
  /// **'You are too far from the gate.'**
  String get errOutsideGeofence;

  /// No description provided for @errLocationImprecise.
  ///
  /// In en, this message translates to:
  /// **'Your location is not accurate enough.'**
  String get errLocationImprecise;

  /// No description provided for @errLocationPermissionDenied.
  ///
  /// In en, this message translates to:
  /// **'Location permission is required to open this gate.'**
  String get errLocationPermissionDenied;

  /// No description provided for @errLocationPermissionPermanent.
  ///
  /// In en, this message translates to:
  /// **'Location permission is turned off. Enable it in Settings.'**
  String get errLocationPermissionPermanent;

  /// No description provided for @errLocationServiceDisabled.
  ///
  /// In en, this message translates to:
  /// **'Location (GPS) is turned off. Turn it on to open.'**
  String get errLocationServiceDisabled;

  /// No description provided for @errLocationTimeout.
  ///
  /// In en, this message translates to:
  /// **'Could not get your location. Please try again.'**
  String get errLocationTimeout;

  /// No description provided for @locationOpenSettings.
  ///
  /// In en, this message translates to:
  /// **'Open Settings'**
  String get locationOpenSettings;

  /// No description provided for @directions.
  ///
  /// In en, this message translates to:
  /// **'Directions'**
  String get directions;

  /// No description provided for @inviteVisitor.
  ///
  /// In en, this message translates to:
  /// **'Invite'**
  String get inviteVisitor;

  /// No description provided for @directionsNoLocation.
  ///
  /// In en, this message translates to:
  /// **'No location set for this barrier'**
  String get directionsNoLocation;

  /// No description provided for @directionsNoApp.
  ///
  /// In en, this message translates to:
  /// **'No navigation app found'**
  String get directionsNoApp;

  /// No description provided for @directionsChooseApp.
  ///
  /// In en, this message translates to:
  /// **'Choose an app'**
  String get directionsChooseApp;

  /// No description provided for @directionsFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not open navigation'**
  String get directionsFailed;

  /// No description provided for @azNavRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'AzNav required'**
  String get azNavRequiredTitle;

  /// No description provided for @azNavRequiredMessage.
  ///
  /// In en, this message translates to:
  /// **'Install the AzNav app to use directions.'**
  String get azNavRequiredMessage;

  /// No description provided for @azNavInstall.
  ///
  /// In en, this message translates to:
  /// **'Install AzNav'**
  String get azNavInstall;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @visitorInviteTitle.
  ///
  /// In en, this message translates to:
  /// **'Invite a visitor'**
  String get visitorInviteTitle;

  /// No description provided for @visitorAccessOneTime.
  ///
  /// In en, this message translates to:
  /// **'One-time'**
  String get visitorAccessOneTime;

  /// No description provided for @visitorAccessTimeLimited.
  ///
  /// In en, this message translates to:
  /// **'Time-limited'**
  String get visitorAccessTimeLimited;

  /// No description provided for @visitorDurationLabel.
  ///
  /// In en, this message translates to:
  /// **'Duration'**
  String get visitorDurationLabel;

  /// No description provided for @visitorNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Visitor name (optional)'**
  String get visitorNameLabel;

  /// No description provided for @visitorPurposeLabel.
  ///
  /// In en, this message translates to:
  /// **'Purpose (optional)'**
  String get visitorPurposeLabel;

  /// No description provided for @visitorPurposeGuest.
  ///
  /// In en, this message translates to:
  /// **'Guest'**
  String get visitorPurposeGuest;

  /// No description provided for @visitorPurposeDelivery.
  ///
  /// In en, this message translates to:
  /// **'Delivery'**
  String get visitorPurposeDelivery;

  /// No description provided for @visitorPurposeCourier.
  ///
  /// In en, this message translates to:
  /// **'Courier'**
  String get visitorPurposeCourier;

  /// No description provided for @visitorPurposeService.
  ///
  /// In en, this message translates to:
  /// **'Service'**
  String get visitorPurposeService;

  /// No description provided for @visitorPurposeCleaning.
  ///
  /// In en, this message translates to:
  /// **'Cleaning'**
  String get visitorPurposeCleaning;

  /// No description provided for @visitorPurposeTaxi.
  ///
  /// In en, this message translates to:
  /// **'Taxi'**
  String get visitorPurposeTaxi;

  /// No description provided for @visitorPurposeOther.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get visitorPurposeOther;

  /// No description provided for @visitorGenerate.
  ///
  /// In en, this message translates to:
  /// **'Generate link'**
  String get visitorGenerate;

  /// No description provided for @visitorLinkReady.
  ///
  /// In en, this message translates to:
  /// **'Link is ready'**
  String get visitorLinkReady;

  /// No description provided for @visitorShare.
  ///
  /// In en, this message translates to:
  /// **'Share'**
  String get visitorShare;

  /// No description provided for @visitorCopy.
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get visitorCopy;

  /// No description provided for @visitorCopied.
  ///
  /// In en, this message translates to:
  /// **'Copied'**
  String get visitorCopied;

  /// No description provided for @visitorDone.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get visitorDone;

  /// No description provided for @visitorMinutesShort.
  ///
  /// In en, this message translates to:
  /// **'{count} min'**
  String visitorMinutesShort(int count);

  /// No description provided for @visitorHoursShort.
  ///
  /// In en, this message translates to:
  /// **'{count} h'**
  String visitorHoursShort(int count);

  /// No description provided for @doorWidgetTitle.
  ///
  /// In en, this message translates to:
  /// **'Home screen widget'**
  String get doorWidgetTitle;

  /// No description provided for @doorWidgetIntro.
  ///
  /// In en, this message translates to:
  /// **'Choose the barrier for your home-screen widget. The selected barrier\'s name appears on the widget.'**
  String get doorWidgetIntro;

  /// No description provided for @doorWidgetSelected.
  ///
  /// In en, this message translates to:
  /// **'{label} set for the widget'**
  String doorWidgetSelected(String label);

  /// No description provided for @doorWidgetClear.
  ///
  /// In en, this message translates to:
  /// **'Remove widget selection'**
  String get doorWidgetClear;

  /// No description provided for @doorWidgetCleared.
  ///
  /// In en, this message translates to:
  /// **'Widget selection removed'**
  String get doorWidgetCleared;

  /// No description provided for @doorWidgetUnconfigured.
  ///
  /// In en, this message translates to:
  /// **'No door selected'**
  String get doorWidgetUnconfigured;

  /// No description provided for @doorWidgetAddHint.
  ///
  /// In en, this message translates to:
  /// **'Add the \"Open door\" widget to your home screen, then choose a door.'**
  String get doorWidgetAddHint;

  /// No description provided for @doorWidgetInstance.
  ///
  /// In en, this message translates to:
  /// **'Widget #{id}'**
  String doorWidgetInstance(int id);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['az', 'en', 'ru'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'az':
      return AppLocalizationsAz();
    case 'en':
      return AppLocalizationsEn();
    case 'ru':
      return AppLocalizationsRu();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
