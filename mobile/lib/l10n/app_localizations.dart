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

  /// No description provided for @homeInvitations.
  ///
  /// In en, this message translates to:
  /// **'My Invitations'**
  String get homeInvitations;

  /// No description provided for @invitationsTitle.
  ///
  /// In en, this message translates to:
  /// **'My Invitations'**
  String get invitationsTitle;

  /// No description provided for @invitationsEmpty.
  ///
  /// In en, this message translates to:
  /// **'You haven\'t sent any invitations yet.'**
  String get invitationsEmpty;

  /// No description provided for @invitationFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get invitationFilterAll;

  /// No description provided for @invitationFilterActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get invitationFilterActive;

  /// No description provided for @invitationFilterUsed.
  ///
  /// In en, this message translates to:
  /// **'Used'**
  String get invitationFilterUsed;

  /// No description provided for @invitationFilterExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get invitationFilterExpired;

  /// No description provided for @invitationFilterRevoked.
  ///
  /// In en, this message translates to:
  /// **'Revoked'**
  String get invitationFilterRevoked;

  /// No description provided for @invitationStatusActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get invitationStatusActive;

  /// No description provided for @invitationStatusUsed.
  ///
  /// In en, this message translates to:
  /// **'Used'**
  String get invitationStatusUsed;

  /// No description provided for @invitationStatusExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get invitationStatusExpired;

  /// No description provided for @invitationStatusRevoked.
  ///
  /// In en, this message translates to:
  /// **'Revoked'**
  String get invitationStatusRevoked;

  /// No description provided for @invitationSentAt.
  ///
  /// In en, this message translates to:
  /// **'Sent'**
  String get invitationSentAt;

  /// No description provided for @invitationDuration.
  ///
  /// In en, this message translates to:
  /// **'Duration'**
  String get invitationDuration;

  /// No description provided for @invitationExpiresAt.
  ///
  /// In en, this message translates to:
  /// **'Expires'**
  String get invitationExpiresAt;

  /// No description provided for @invitationRemaining.
  ///
  /// In en, this message translates to:
  /// **'{time} left'**
  String invitationRemaining(String time);

  /// No description provided for @invitationUsedAt.
  ///
  /// In en, this message translates to:
  /// **'Used'**
  String get invitationUsedAt;

  /// No description provided for @invitationFirstUsedAt.
  ///
  /// In en, this message translates to:
  /// **'First used'**
  String get invitationFirstUsedAt;

  /// No description provided for @invitationLastUsedAt.
  ///
  /// In en, this message translates to:
  /// **'Last used'**
  String get invitationLastUsedAt;

  /// No description provided for @invitationUsageCount.
  ///
  /// In en, this message translates to:
  /// **'Uses'**
  String get invitationUsageCount;

  /// No description provided for @invitationUsageValue.
  ///
  /// In en, this message translates to:
  /// **'{count} times'**
  String invitationUsageValue(int count);

  /// No description provided for @invitationUnlimited.
  ///
  /// In en, this message translates to:
  /// **'Unlimited'**
  String get invitationUnlimited;

  /// No description provided for @invitationDefaultTitle.
  ///
  /// In en, this message translates to:
  /// **'Invitation'**
  String get invitationDefaultTitle;

  /// No description provided for @checkoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get checkoutTitle;

  /// No description provided for @paymentTestBanner.
  ///
  /// In en, this message translates to:
  /// **'TEST PAYMENT'**
  String get paymentTestBanner;

  /// No description provided for @paymentResultTitle.
  ///
  /// In en, this message translates to:
  /// **'Payment result'**
  String get paymentResultTitle;

  /// No description provided for @paymentSuccess.
  ///
  /// In en, this message translates to:
  /// **'Payment successful'**
  String get paymentSuccess;

  /// No description provided for @paymentFailed.
  ///
  /// In en, this message translates to:
  /// **'Payment failed'**
  String get paymentFailed;

  /// No description provided for @paymentCancelled.
  ///
  /// In en, this message translates to:
  /// **'Payment cancelled'**
  String get paymentCancelled;

  /// No description provided for @paymentExpired.
  ///
  /// In en, this message translates to:
  /// **'Payment time expired'**
  String get paymentExpired;

  /// No description provided for @paymentPending.
  ///
  /// In en, this message translates to:
  /// **'Payment is being processed'**
  String get paymentPending;

  /// No description provided for @paymentChecking.
  ///
  /// In en, this message translates to:
  /// **'Confirming with the bank…'**
  String get paymentChecking;

  /// No description provided for @paymentRecheck.
  ///
  /// In en, this message translates to:
  /// **'Check again'**
  String get paymentRecheck;

  /// No description provided for @paymentDone.
  ///
  /// In en, this message translates to:
  /// **'Back to home'**
  String get paymentDone;

  /// No description provided for @paymentOrderNotFound.
  ///
  /// In en, this message translates to:
  /// **'Order not found'**
  String get paymentOrderNotFound;

  /// No description provided for @subscriptionRenew.
  ///
  /// In en, this message translates to:
  /// **'Renew (12 AZN / 30 days)'**
  String get subscriptionRenew;

  /// No description provided for @subscriptionRenewNotEligible.
  ///
  /// In en, this message translates to:
  /// **'This subscription cannot be renewed right now.'**
  String get subscriptionRenewNotEligible;

  /// No description provided for @regTypeTitle.
  ///
  /// In en, this message translates to:
  /// **'Registration type'**
  String get regTypeTitle;

  /// No description provided for @regTypePhysical.
  ///
  /// In en, this message translates to:
  /// **'Individual'**
  String get regTypePhysical;

  /// No description provided for @regTypePhysicalBody.
  ///
  /// In en, this message translates to:
  /// **'I want a barrier device for my own private yard.'**
  String get regTypePhysicalBody;

  /// No description provided for @regTypeLegal.
  ///
  /// In en, this message translates to:
  /// **'Legal entity'**
  String get regTypeLegal;

  /// No description provided for @regTypeLegalBody.
  ///
  /// In en, this message translates to:
  /// **'Residential complex, building or company.'**
  String get regTypeLegalBody;

  /// No description provided for @appMineTitle.
  ///
  /// In en, this message translates to:
  /// **'My applications'**
  String get appMineTitle;

  /// No description provided for @appNone.
  ///
  /// In en, this message translates to:
  /// **'You have no applications yet'**
  String get appNone;

  /// No description provided for @appNewPhysical.
  ///
  /// In en, this message translates to:
  /// **'New application (individual)'**
  String get appNewPhysical;

  /// No description provided for @appNewLegal.
  ///
  /// In en, this message translates to:
  /// **'New application (legal entity)'**
  String get appNewLegal;

  /// No description provided for @appSectionPhysical.
  ///
  /// In en, this message translates to:
  /// **'Individual'**
  String get appSectionPhysical;

  /// No description provided for @appSectionLegal.
  ///
  /// In en, this message translates to:
  /// **'Legal entity'**
  String get appSectionLegal;

  /// No description provided for @appPhysicalTitle.
  ///
  /// In en, this message translates to:
  /// **'Individual application'**
  String get appPhysicalTitle;

  /// No description provided for @appPhysicalIntro.
  ///
  /// In en, this message translates to:
  /// **'Tell us where your yard is — our team will contact you about installation.'**
  String get appPhysicalIntro;

  /// No description provided for @appLegalTitle.
  ///
  /// In en, this message translates to:
  /// **'Legal entity application'**
  String get appLegalTitle;

  /// No description provided for @appLegalIntro.
  ///
  /// In en, this message translates to:
  /// **'Complex details and location. An administrator reviews the application.'**
  String get appLegalIntro;

  /// No description provided for @appFullName.
  ///
  /// In en, this message translates to:
  /// **'Full name'**
  String get appFullName;

  /// No description provided for @appPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get appPhone;

  /// No description provided for @appEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get appEmail;

  /// No description provided for @appAddress.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get appAddress;

  /// No description provided for @appNote.
  ///
  /// In en, this message translates to:
  /// **'Note (optional)'**
  String get appNote;

  /// No description provided for @appComplexName.
  ///
  /// In en, this message translates to:
  /// **'Complex name'**
  String get appComplexName;

  /// No description provided for @appLegalName.
  ///
  /// In en, this message translates to:
  /// **'Legal name'**
  String get appLegalName;

  /// No description provided for @appVoen.
  ///
  /// In en, this message translates to:
  /// **'TIN (VÖEN)'**
  String get appVoen;

  /// No description provided for @appLegalAddress.
  ///
  /// In en, this message translates to:
  /// **'Legal address'**
  String get appLegalAddress;

  /// No description provided for @appContactName.
  ///
  /// In en, this message translates to:
  /// **'Contact person'**
  String get appContactName;

  /// No description provided for @appComplexAddress.
  ///
  /// In en, this message translates to:
  /// **'Complex address'**
  String get appComplexAddress;

  /// No description provided for @appApartments.
  ///
  /// In en, this message translates to:
  /// **'Number of apartments (optional)'**
  String get appApartments;

  /// No description provided for @appLocationLabel.
  ///
  /// In en, this message translates to:
  /// **'Location on the map'**
  String get appLocationLabel;

  /// No description provided for @appLocationHint.
  ///
  /// In en, this message translates to:
  /// **'Tap the map to place the pin'**
  String get appLocationHint;

  /// No description provided for @appUseMyLocation.
  ///
  /// In en, this message translates to:
  /// **'My location'**
  String get appUseMyLocation;

  /// No description provided for @appLocationRequired.
  ///
  /// In en, this message translates to:
  /// **'Choose the location on the map'**
  String get appLocationRequired;

  /// No description provided for @appLocationOutside.
  ///
  /// In en, this message translates to:
  /// **'The location must be inside Azerbaijan'**
  String get appLocationOutside;

  /// No description provided for @appVoenInvalid.
  ///
  /// In en, this message translates to:
  /// **'TIN must be 10 digits'**
  String get appVoenInvalid;

  /// No description provided for @appApartmentsInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid number'**
  String get appApartmentsInvalid;

  /// No description provided for @appSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit application'**
  String get appSubmit;

  /// No description provided for @appLater.
  ///
  /// In en, this message translates to:
  /// **'I will fill it in later'**
  String get appLater;

  /// No description provided for @appSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Application submitted'**
  String get appSubmitted;

  /// No description provided for @appStatusNew.
  ///
  /// In en, this message translates to:
  /// **'New'**
  String get appStatusNew;

  /// No description provided for @appStatusContacted.
  ///
  /// In en, this message translates to:
  /// **'Contacted'**
  String get appStatusContacted;

  /// No description provided for @appStatusInProgress.
  ///
  /// In en, this message translates to:
  /// **'In progress'**
  String get appStatusInProgress;

  /// No description provided for @appStatusInstalled.
  ///
  /// In en, this message translates to:
  /// **'Installed'**
  String get appStatusInstalled;

  /// No description provided for @appStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Under review'**
  String get appStatusPending;

  /// No description provided for @appStatusApproved.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get appStatusApproved;

  /// No description provided for @appStatusRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get appStatusRejected;

  /// No description provided for @appRejectReason.
  ///
  /// In en, this message translates to:
  /// **'Reason'**
  String get appRejectReason;

  /// No description provided for @appErrTypeMismatch.
  ///
  /// In en, this message translates to:
  /// **'Your account type does not match this application type.'**
  String get appErrTypeMismatch;

  /// No description provided for @appErrAlreadyOpen.
  ///
  /// In en, this message translates to:
  /// **'You already have an open application.'**
  String get appErrAlreadyOpen;

  /// No description provided for @appErrDuplicateVoen.
  ///
  /// In en, this message translates to:
  /// **'An application with this TIN is already under review.'**
  String get appErrDuplicateVoen;

  /// No description provided for @kmTitle.
  ///
  /// In en, this message translates to:
  /// **'My complex (Komendant)'**
  String get kmTitle;

  /// No description provided for @kmEntrySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Residents, invitations and devices'**
  String get kmEntrySubtitle;

  /// No description provided for @kmStatDevices.
  ///
  /// In en, this message translates to:
  /// **'Devices'**
  String get kmStatDevices;

  /// No description provided for @kmStatResidents.
  ///
  /// In en, this message translates to:
  /// **'Residents'**
  String get kmStatResidents;

  /// No description provided for @kmStatPending.
  ///
  /// In en, this message translates to:
  /// **'Pending invites'**
  String get kmStatPending;

  /// No description provided for @kmDevicesTitle.
  ///
  /// In en, this message translates to:
  /// **'Complex devices'**
  String get kmDevicesTitle;

  /// No description provided for @kmNoDevices.
  ///
  /// In en, this message translates to:
  /// **'No devices are linked to the complex yet.'**
  String get kmNoDevices;

  /// No description provided for @kmOnline.
  ///
  /// In en, this message translates to:
  /// **'Online'**
  String get kmOnline;

  /// No description provided for @kmOffline.
  ///
  /// In en, this message translates to:
  /// **'Offline'**
  String get kmOffline;

  /// No description provided for @kmDevicePrice.
  ///
  /// In en, this message translates to:
  /// **'Subscription: {price} / {days} days'**
  String kmDevicePrice(String price, int days);

  /// No description provided for @kmInvite.
  ///
  /// In en, this message translates to:
  /// **'Invite resident'**
  String get kmInvite;

  /// No description provided for @kmInviteIntro.
  ///
  /// In en, this message translates to:
  /// **'The resident will receive an invitation link by email, valid for 7 days.'**
  String get kmInviteIntro;

  /// No description provided for @kmFirstName.
  ///
  /// In en, this message translates to:
  /// **'First name'**
  String get kmFirstName;

  /// No description provided for @kmLastName.
  ///
  /// In en, this message translates to:
  /// **'Last name'**
  String get kmLastName;

  /// No description provided for @kmEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get kmEmail;

  /// No description provided for @kmInviteSend.
  ///
  /// In en, this message translates to:
  /// **'Send invitation'**
  String get kmInviteSend;

  /// No description provided for @kmInviteSent.
  ///
  /// In en, this message translates to:
  /// **'Invitation sent.'**
  String get kmInviteSent;

  /// No description provided for @kmInvitations.
  ///
  /// In en, this message translates to:
  /// **'Invitations'**
  String get kmInvitations;

  /// No description provided for @kmTabPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get kmTabPending;

  /// No description provided for @kmTabAccepted.
  ///
  /// In en, this message translates to:
  /// **'Accepted'**
  String get kmTabAccepted;

  /// No description provided for @kmTabExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get kmTabExpired;

  /// No description provided for @kmTabClosed.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get kmTabClosed;

  /// No description provided for @kmStatusDeclined.
  ///
  /// In en, this message translates to:
  /// **'Declined'**
  String get kmStatusDeclined;

  /// No description provided for @kmNoInvitations.
  ///
  /// In en, this message translates to:
  /// **'No invitations here.'**
  String get kmNoInvitations;

  /// No description provided for @kmExpiresAt.
  ///
  /// In en, this message translates to:
  /// **'Expires: {date}'**
  String kmExpiresAt(String date);

  /// No description provided for @kmAcceptedAt.
  ///
  /// In en, this message translates to:
  /// **'Accepted: {date}'**
  String kmAcceptedAt(String date);

  /// No description provided for @kmSendCount.
  ///
  /// In en, this message translates to:
  /// **'Sent {count} times'**
  String kmSendCount(int count);

  /// No description provided for @kmResend.
  ///
  /// In en, this message translates to:
  /// **'Resend'**
  String get kmResend;

  /// No description provided for @kmRevoke.
  ///
  /// In en, this message translates to:
  /// **'Revoke'**
  String get kmRevoke;

  /// No description provided for @kmResent.
  ///
  /// In en, this message translates to:
  /// **'Invitation resent.'**
  String get kmResent;

  /// No description provided for @kmRevoked.
  ///
  /// In en, this message translates to:
  /// **'Invitation revoked.'**
  String get kmRevoked;

  /// No description provided for @kmRevokeTitle.
  ///
  /// In en, this message translates to:
  /// **'Revoke invitation?'**
  String get kmRevokeTitle;

  /// No description provided for @kmRevokeBody.
  ///
  /// In en, this message translates to:
  /// **'The invitation link will stop working.'**
  String get kmRevokeBody;

  /// No description provided for @kmResidentsTitle.
  ///
  /// In en, this message translates to:
  /// **'Residents'**
  String get kmResidentsTitle;

  /// No description provided for @kmNoResidents.
  ///
  /// In en, this message translates to:
  /// **'The complex has no residents yet.'**
  String get kmNoResidents;

  /// No description provided for @kmActiveSubs.
  ///
  /// In en, this message translates to:
  /// **'Active subscriptions: {count}'**
  String kmActiveSubs(int count);

  /// No description provided for @kmNoActiveSub.
  ///
  /// In en, this message translates to:
  /// **'No active subscription'**
  String get kmNoActiveSub;

  /// No description provided for @kmJoinedAt.
  ///
  /// In en, this message translates to:
  /// **'Joined: {date}'**
  String kmJoinedAt(String date);

  /// No description provided for @kmRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get kmRemove;

  /// No description provided for @kmRemoveTitle.
  ///
  /// In en, this message translates to:
  /// **'Remove resident from complex'**
  String get kmRemoveTitle;

  /// No description provided for @kmRemoveBody.
  ///
  /// In en, this message translates to:
  /// **'{name} will be removed from the complex. Access to the complex devices stops immediately and active subscriptions are cancelled. No automatic refund is made.'**
  String kmRemoveBody(String name);

  /// No description provided for @kmRemoved.
  ///
  /// In en, this message translates to:
  /// **'Resident removed from the complex.'**
  String get kmRemoved;

  /// No description provided for @kmErrNotKomendant.
  ///
  /// In en, this message translates to:
  /// **'This section is only for the complex manager.'**
  String get kmErrNotKomendant;

  /// No description provided for @kmErrForbidden.
  ///
  /// In en, this message translates to:
  /// **'You are not allowed to do this.'**
  String get kmErrForbidden;

  /// No description provided for @kmErrAlreadyResident.
  ///
  /// In en, this message translates to:
  /// **'This email already belongs to a resident of the complex.'**
  String get kmErrAlreadyResident;

  /// No description provided for @kmErrAlreadyPending.
  ///
  /// In en, this message translates to:
  /// **'An active invitation already exists for this email.'**
  String get kmErrAlreadyPending;

  /// No description provided for @kmErrNotResendable.
  ///
  /// In en, this message translates to:
  /// **'This invitation cannot be resent.'**
  String get kmErrNotResendable;

  /// No description provided for @kmErrNotRevocable.
  ///
  /// In en, this message translates to:
  /// **'This invitation cannot be revoked.'**
  String get kmErrNotRevocable;

  /// No description provided for @kmErrRateLimited.
  ///
  /// In en, this message translates to:
  /// **'Sent too often. Please try again a little later.'**
  String get kmErrRateLimited;

  /// No description provided for @kmErrNotFound.
  ///
  /// In en, this message translates to:
  /// **'Not found. The list has been refreshed.'**
  String get kmErrNotFound;

  /// No description provided for @invTitle.
  ///
  /// In en, this message translates to:
  /// **'Invitation'**
  String get invTitle;

  /// No description provided for @invComplexKind.
  ///
  /// In en, this message translates to:
  /// **'Residential complex invitation'**
  String get invComplexKind;

  /// No description provided for @invFamilyKind.
  ///
  /// In en, this message translates to:
  /// **'Family member invitation'**
  String get invFamilyKind;

  /// No description provided for @invComplexBody.
  ///
  /// In en, this message translates to:
  /// **'You are invited to {complex} as a resident. After accepting you can pick a device and subscribe.'**
  String invComplexBody(String complex);

  /// No description provided for @invFamilyBody.
  ///
  /// In en, this message translates to:
  /// **'{inviter} invited you as a family member to use a device. Access activates once your own subscription is paid.'**
  String invFamilyBody(String inviter);

  /// No description provided for @invFor.
  ///
  /// In en, this message translates to:
  /// **'Invitee: {name}'**
  String invFor(String name);

  /// No description provided for @invSentTo.
  ///
  /// In en, this message translates to:
  /// **'The invitation was sent to {email}. Continue with that email.'**
  String invSentTo(String email);

  /// No description provided for @invExpires.
  ///
  /// In en, this message translates to:
  /// **'Valid until {date}'**
  String invExpires(String date);

  /// No description provided for @invRegister.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get invRegister;

  /// No description provided for @invLogin.
  ///
  /// In en, this message translates to:
  /// **'I have an account — log in'**
  String get invLogin;

  /// No description provided for @invAccept.
  ///
  /// In en, this message translates to:
  /// **'Accept invitation'**
  String get invAccept;

  /// No description provided for @invDecline.
  ///
  /// In en, this message translates to:
  /// **'Decline'**
  String get invDecline;

  /// No description provided for @invDeclineTitle.
  ///
  /// In en, this message translates to:
  /// **'Decline the invitation?'**
  String get invDeclineTitle;

  /// No description provided for @invDeclineBody.
  ///
  /// In en, this message translates to:
  /// **'This invitation link will stop working.'**
  String get invDeclineBody;

  /// No description provided for @invDeclined.
  ///
  /// In en, this message translates to:
  /// **'Invitation declined.'**
  String get invDeclined;

  /// No description provided for @invAcceptedComplex.
  ///
  /// In en, this message translates to:
  /// **'Invitation accepted. Welcome to {complex}!'**
  String invAcceptedComplex(String complex);

  /// No description provided for @invAcceptedFamily.
  ///
  /// In en, this message translates to:
  /// **'Invitation accepted. Pay the subscription to activate access.'**
  String get invAcceptedFamily;

  /// No description provided for @invGoneTitle.
  ///
  /// In en, this message translates to:
  /// **'Invitation is not valid'**
  String get invGoneTitle;

  /// No description provided for @invGoneBody.
  ///
  /// In en, this message translates to:
  /// **'This invitation link has expired, been revoked or already used. Ask the sender for a new invitation.'**
  String get invGoneBody;

  /// No description provided for @invClose.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get invClose;

  /// No description provided for @invErrEmailMismatch.
  ///
  /// In en, this message translates to:
  /// **'This invitation is for another email address. Sign in with the invited email.'**
  String get invErrEmailMismatch;

  /// No description provided for @invErrAlreadyHasAccess.
  ///
  /// In en, this message translates to:
  /// **'You already have access to this device.'**
  String get invErrAlreadyHasAccess;

  /// No description provided for @invErrInvalidTarget.
  ///
  /// In en, this message translates to:
  /// **'You cannot accept your own invitation.'**
  String get invErrInvalidTarget;

  /// No description provided for @invErrUnsupported.
  ///
  /// In en, this message translates to:
  /// **'This invitation type is not supported.'**
  String get invErrUnsupported;

  /// No description provided for @invRegisterHint.
  ///
  /// In en, this message translates to:
  /// **'Register with the invited email; the invitation is accepted once your account is verified.'**
  String get invRegisterHint;

  /// No description provided for @invPendingCard.
  ///
  /// In en, this message translates to:
  /// **'You have an invitation'**
  String get invPendingCard;

  /// No description provided for @invPendingCardBody.
  ///
  /// In en, this message translates to:
  /// **'Open and accept it.'**
  String get invPendingCardBody;

  /// No description provided for @cxTitle.
  ///
  /// In en, this message translates to:
  /// **'My complex'**
  String get cxTitle;

  /// No description provided for @cxMyComplexes.
  ///
  /// In en, this message translates to:
  /// **'My complexes'**
  String get cxMyComplexes;

  /// No description provided for @cxEntrySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Pick a device and subscribe'**
  String get cxEntrySubtitle;

  /// No description provided for @cxNoComplexes.
  ///
  /// In en, this message translates to:
  /// **'You are not a resident of any complex yet.'**
  String get cxNoComplexes;

  /// No description provided for @cxDevicesTitle.
  ///
  /// In en, this message translates to:
  /// **'Complex devices'**
  String get cxDevicesTitle;

  /// No description provided for @cxNoDevices.
  ///
  /// In en, this message translates to:
  /// **'The complex has no devices yet.'**
  String get cxNoDevices;

  /// No description provided for @cxStatusNone.
  ///
  /// In en, this message translates to:
  /// **'Not subscribed'**
  String get cxStatusNone;

  /// No description provided for @cxStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Awaiting payment'**
  String get cxStatusPending;

  /// No description provided for @cxStatusActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get cxStatusActive;

  /// No description provided for @cxStatusExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get cxStatusExpired;

  /// No description provided for @cxPrice.
  ///
  /// In en, this message translates to:
  /// **'{price} / {days} days'**
  String cxPrice(String price, int days);

  /// No description provided for @cxPriceNote.
  ///
  /// In en, this message translates to:
  /// **'A subscription activates your access to this device.'**
  String get cxPriceNote;

  /// No description provided for @cxSubscriptionBlock.
  ///
  /// In en, this message translates to:
  /// **'Monthly subscription'**
  String get cxSubscriptionBlock;

  /// No description provided for @cxSubscribe.
  ///
  /// In en, this message translates to:
  /// **'Subscribe'**
  String get cxSubscribe;

  /// No description provided for @cxFinishPayment.
  ///
  /// In en, this message translates to:
  /// **'Complete payment'**
  String get cxFinishPayment;

  /// No description provided for @cxRenew.
  ///
  /// In en, this message translates to:
  /// **'Renew'**
  String get cxRenew;

  /// No description provided for @cxOpenInDevices.
  ///
  /// In en, this message translates to:
  /// **'Open in Devices'**
  String get cxOpenInDevices;

  /// No description provided for @cxDeviceTitle.
  ///
  /// In en, this message translates to:
  /// **'Device'**
  String get cxDeviceTitle;

  /// No description provided for @cxErrAlreadyActive.
  ///
  /// In en, this message translates to:
  /// **'Your subscription for this device is already active.'**
  String get cxErrAlreadyActive;

  /// No description provided for @payPendingTitle.
  ///
  /// In en, this message translates to:
  /// **'Awaiting payment'**
  String get payPendingTitle;

  /// No description provided for @payPendingCard.
  ///
  /// In en, this message translates to:
  /// **'Subscriptions awaiting payment: {count}'**
  String payPendingCard(int count);

  /// No description provided for @payPendingNone.
  ///
  /// In en, this message translates to:
  /// **'Nothing is awaiting payment.'**
  String get payPendingNone;

  /// No description provided for @payPendingMain.
  ///
  /// In en, this message translates to:
  /// **'Resident subscription'**
  String get payPendingMain;

  /// No description provided for @payPendingAdditional.
  ///
  /// In en, this message translates to:
  /// **'Family member subscription'**
  String get payPendingAdditional;

  /// No description provided for @payNow.
  ///
  /// In en, this message translates to:
  /// **'Pay'**
  String get payNow;

  /// No description provided for @payDeviceFallback.
  ///
  /// In en, this message translates to:
  /// **'Device #{id}'**
  String payDeviceFallback(int id);

  /// No description provided for @payErrForbidden.
  ///
  /// In en, this message translates to:
  /// **'You are not allowed to do this.'**
  String get payErrForbidden;

  /// No description provided for @famTitle.
  ///
  /// In en, this message translates to:
  /// **'Family members'**
  String get famTitle;

  /// No description provided for @famEntrySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Invitations, access and payments'**
  String get famEntrySubtitle;

  /// No description provided for @famTabMembers.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get famTabMembers;

  /// No description provided for @famTabInvitations.
  ///
  /// In en, this message translates to:
  /// **'Invitations'**
  String get famTabInvitations;

  /// No description provided for @famInvite.
  ///
  /// In en, this message translates to:
  /// **'Invite a family member'**
  String get famInvite;

  /// No description provided for @famNotHead.
  ///
  /// In en, this message translates to:
  /// **'To invite family members you need active access of your own on a device. This is not available on devices you were added to as a family member.'**
  String get famNotHead;

  /// No description provided for @famErrNotHead.
  ///
  /// In en, this message translates to:
  /// **'You cannot manage family members on this device.'**
  String get famErrNotHead;

  /// No description provided for @famErrInvalidTarget.
  ///
  /// In en, this message translates to:
  /// **'This person cannot be invited (yourself or someone already on this device).'**
  String get famErrInvalidTarget;

  /// No description provided for @famNoMembers.
  ///
  /// In en, this message translates to:
  /// **'No family members yet.'**
  String get famNoMembers;

  /// No description provided for @famNoInvitations.
  ///
  /// In en, this message translates to:
  /// **'No family invitations yet.'**
  String get famNoInvitations;

  /// No description provided for @famDeviceLabel.
  ///
  /// In en, this message translates to:
  /// **'Device'**
  String get famDeviceLabel;

  /// No description provided for @famInviteIntro.
  ///
  /// In en, this message translates to:
  /// **'An invitation valid for 7 days will be emailed for the selected device. Each family member has their own monthly subscription, paid by you or by the member.'**
  String get famInviteIntro;

  /// No description provided for @famGranted.
  ///
  /// In en, this message translates to:
  /// **'Access to this device was granted to the family member. The subscription awaits payment.'**
  String get famGranted;

  /// No description provided for @famSubNone.
  ///
  /// In en, this message translates to:
  /// **'No subscription'**
  String get famSubNone;

  /// No description provided for @famSubActiveUntil.
  ///
  /// In en, this message translates to:
  /// **'Active until {date}'**
  String famSubActiveUntil(String date);

  /// No description provided for @famPayFor.
  ///
  /// In en, this message translates to:
  /// **'Pay for member'**
  String get famPayFor;

  /// No description provided for @famRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get famRemove;

  /// No description provided for @famRemoveTitle.
  ///
  /// In en, this message translates to:
  /// **'Remove family member'**
  String get famRemoveTitle;

  /// No description provided for @famRemoveBody.
  ///
  /// In en, this message translates to:
  /// **'{name} will be removed from your family. Access to the devices you granted stops immediately and their subscriptions are cancelled. No automatic refund is made.'**
  String famRemoveBody(String name);

  /// No description provided for @famRemoved.
  ///
  /// In en, this message translates to:
  /// **'Family member removed.'**
  String get famRemoved;
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
