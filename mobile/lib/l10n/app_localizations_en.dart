// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Salam Həyətimiz';

  @override
  String get register => 'Register';

  @override
  String get login => 'Login';

  @override
  String get verifyOtp => 'Verify OTP';

  @override
  String get continueLabel => 'Continue';

  @override
  String get home => 'Home';

  @override
  String get devices => 'Devices';

  @override
  String get profile => 'Profile';

  @override
  String get settings => 'Settings';

  @override
  String get theme => 'Theme';

  @override
  String get language => 'Language';

  @override
  String get logout => 'Logout';

  @override
  String get welcomeTagline => 'Your yard, one tap away';

  @override
  String get registerTitle => 'Create account';

  @override
  String get firstName => 'First name';

  @override
  String get lastName => 'Last name';

  @override
  String get phoneNumber => 'Phone number';

  @override
  String get emailAddress => 'Email';

  @override
  String get phoneHint => '+994XXXXXXXXX';

  @override
  String get emailHint => 'you@example.com';

  @override
  String get registerSubmit => 'Register';

  @override
  String get alreadyHaveAccount => 'Already have an account? Log in';

  @override
  String get dontHaveAccount => 'Don\'t have an account? Register';

  @override
  String get loginTitle => 'Log in';

  @override
  String get loginSubmit => 'Send login code';

  @override
  String get otpTitle => 'Enter the code';

  @override
  String otpSentTo(String email) {
    return 'We sent a code to $email';
  }

  @override
  String get otpFieldHint => '6-digit code';

  @override
  String get verifySubmit => 'Verify';

  @override
  String get resend => 'Resend code';

  @override
  String resendIn(int seconds) {
    return 'Resend in ${seconds}s';
  }

  @override
  String get maintenanceTitle => 'Under maintenance';

  @override
  String get maintenanceMessage =>
      'The app is temporarily unavailable. Please try again shortly.';

  @override
  String get forceUpdateTitle => 'Update required';

  @override
  String get forceUpdateMessage => 'Please update the app to continue.';

  @override
  String get updateNow => 'Update now';

  @override
  String get retry => 'Retry';

  @override
  String get sessionExpired => 'Your session has expired. Please log in again.';

  @override
  String get errWrongCode => 'The verification code is incorrect.';

  @override
  String get errOtpExpired => 'The code has expired. Request a new one.';

  @override
  String get errOtpMaxAttempts =>
      'Too many incorrect attempts. Request a new code.';

  @override
  String get errEmailAlreadyRegistered =>
      'This email is already registered. Please log in.';

  @override
  String errRateLimited(int seconds) {
    return 'Too many requests. Try again in ${seconds}s.';
  }

  @override
  String get errNetwork => 'No internet connection.';

  @override
  String get errTimeout => 'The connection timed out.';

  @override
  String get errServer => 'Server error. Please try again shortly.';

  @override
  String get errUnknown => 'Something went wrong.';

  @override
  String get errValidation => 'Please check the entered details.';

  @override
  String get vRequired => 'This field is required';

  @override
  String get vEmail => 'Enter a valid email';

  @override
  String get vPhone => 'Phone must be in +994XXXXXXXXX format';

  @override
  String homeGreeting(String name) {
    return 'Hello, $name!';
  }

  @override
  String get homeUser => 'User';

  @override
  String get homeActiveDevices => 'Active devices';

  @override
  String get homeActiveSubscriptions => 'Active subscriptions';

  @override
  String get homeLastActivity => 'Last activity';

  @override
  String get homeQuickOpen => 'Open gate';

  @override
  String get devicesTitle => 'My Devices';

  @override
  String get devicesEmpty => 'You have no devices';

  @override
  String get deviceOnline => 'Online';

  @override
  String get deviceOffline => 'Offline';

  @override
  String get deviceUnknownStatus => 'Unknown';

  @override
  String get deviceInfoTitle => 'Device info';

  @override
  String get deviceAddress => 'Address';

  @override
  String get deviceImei => 'IMEI';

  @override
  String get deviceLastOnlineLabel => 'Last online';

  @override
  String get notifications => 'Notifications';

  @override
  String get notificationsEmpty => 'No notifications yet';

  @override
  String get notificationsEmptyHint => 'New notifications will appear here.';

  @override
  String get notificationsMarkAllRead => 'Mark all as read';

  @override
  String get profilePersonalInfo => 'Personal information';

  @override
  String get profileResidence => 'Residential complex';

  @override
  String get profileAppSettings => 'App settings';

  @override
  String get profileHelp => 'Help & support';

  @override
  String get fieldFirstName => 'First name';

  @override
  String get fieldLastName => 'Last name';

  @override
  String get fieldPhone => 'Phone';

  @override
  String get fieldEmail => 'Email';

  @override
  String get helpDescription => 'Contact us for questions and support.';

  @override
  String get residenceEmpty => 'No barriers assigned.';

  @override
  String appVersionLabel(String version) {
    return 'Version $version';
  }

  @override
  String get deviceAddressMissing => 'No address provided.';

  @override
  String deviceLastOnline(String time) {
    return 'Last online: $time';
  }

  @override
  String get deviceStatus => 'Status';

  @override
  String get deviceRole => 'Role';

  @override
  String get deviceRoleOwner => 'Owner';

  @override
  String get deviceRoleUser => 'Resident';

  @override
  String get deviceModel => 'Model';

  @override
  String get deviceSerial => 'Serial';

  @override
  String get deviceSubscription => 'Subscription';

  @override
  String get deviceSubscriptionActive => 'Active';

  @override
  String get activeSubscriptionsTitle => 'Active subscriptions';

  @override
  String get subscriptionsEmpty => 'You have no active subscriptions';

  @override
  String get subscriptionTierMain => 'Main subscription';

  @override
  String get subscriptionTierAdditional => 'Additional subscription';

  @override
  String get subscriptionStart => 'Start';

  @override
  String get subscriptionEnd => 'Expiry';

  @override
  String subscriptionDaysLeft(int days) {
    return '$days days left';
  }

  @override
  String get subscriptionExpiresToday => 'Expires today';

  @override
  String get barrierOpen => 'Open the gate';

  @override
  String get barrierSending => 'Sending…';

  @override
  String get barrierPending => 'Opening…';

  @override
  String get barrierSuccessOpened => 'The gate opened';

  @override
  String get barrierSuccessSent => 'Command sent';

  @override
  String get barrierFailed => 'The gate could not be opened';

  @override
  String get barrierTimeout => 'Timed out — no response from the device';

  @override
  String barrierCooldown(int seconds) {
    return 'Too many attempts. Try again in ${seconds}s';
  }

  @override
  String get barrierGateMovedQuestion => 'Did the gate open?';

  @override
  String get barrierClose => 'Close the gate';

  @override
  String get barrierCloseSending => 'Sending…';

  @override
  String get barrierClosePending => 'Closing…';

  @override
  String get barrierCloseSuccessClosed => 'The gate closed';

  @override
  String get barrierCloseSuccessSent => 'Command sent';

  @override
  String get barrierCloseFailed => 'The gate could not be closed';

  @override
  String get yes => 'Yes';

  @override
  String get no => 'No';

  @override
  String get errDeviceOffline =>
      'Device offline — the command could not be sent';

  @override
  String get errAccessDenied => 'You don\'t have access to this device';

  @override
  String get errSubscriptionRequired => 'Your subscription is not active';

  @override
  String get errDeviceDisabled => 'The device is disabled';

  @override
  String get errWhitelist => 'The device rejected the command (whitelist)';

  @override
  String get errNotFound => 'Not found';

  @override
  String get barrierLocating => 'Getting location…';

  @override
  String get errLocationRequired => 'Location is required to open the gate.';

  @override
  String get errOutsideGeofence => 'You are too far from the gate.';

  @override
  String get errLocationImprecise => 'Your location is not accurate enough.';

  @override
  String get errLocationPermissionDenied =>
      'Location permission is required to open this gate.';

  @override
  String get errLocationPermissionPermanent =>
      'Location permission is turned off. Enable it in Settings.';

  @override
  String get errLocationServiceDisabled =>
      'Location (GPS) is turned off. Turn it on to open.';

  @override
  String get errLocationTimeout =>
      'Could not get your location. Please try again.';

  @override
  String get locationOpenSettings => 'Open Settings';

  @override
  String get directions => 'Directions';

  @override
  String get inviteVisitor => 'Invite';

  @override
  String get directionsNoLocation => 'No location set for this barrier';

  @override
  String get directionsNoApp => 'No navigation app found';

  @override
  String get directionsChooseApp => 'Choose an app';

  @override
  String get directionsFailed => 'Could not open navigation';

  @override
  String get azNavRequiredTitle => 'AzNav required';

  @override
  String get azNavRequiredMessage => 'Install the AzNav app to use directions.';

  @override
  String get azNavInstall => 'Install AzNav';

  @override
  String get cancel => 'Cancel';

  @override
  String get visitorInviteTitle => 'Invite a visitor';

  @override
  String get visitorAccessOneTime => 'One-time';

  @override
  String get visitorAccessTimeLimited => 'Time-limited';

  @override
  String get visitorDurationLabel => 'Duration';

  @override
  String get visitorNameLabel => 'Visitor name (optional)';

  @override
  String get visitorPurposeLabel => 'Purpose (optional)';

  @override
  String get visitorPurposeGuest => 'Guest';

  @override
  String get visitorPurposeDelivery => 'Delivery';

  @override
  String get visitorPurposeCourier => 'Courier';

  @override
  String get visitorPurposeService => 'Service';

  @override
  String get visitorPurposeCleaning => 'Cleaning';

  @override
  String get visitorPurposeTaxi => 'Taxi';

  @override
  String get visitorPurposeOther => 'Other';

  @override
  String get visitorGenerate => 'Generate link';

  @override
  String get visitorLinkReady => 'Link is ready';

  @override
  String get visitorShare => 'Share';

  @override
  String get visitorCopy => 'Copy';

  @override
  String get visitorCopied => 'Copied';

  @override
  String get visitorDone => 'Done';

  @override
  String visitorMinutesShort(int count) {
    return '$count min';
  }

  @override
  String visitorHoursShort(int count) {
    return '$count h';
  }

  @override
  String get doorWidgetTitle => 'Home screen widget';

  @override
  String get doorWidgetIntro =>
      'Choose the barrier for your home-screen widget. The selected barrier\'s name appears on the widget.';

  @override
  String doorWidgetSelected(String label) {
    return '$label set for the widget';
  }

  @override
  String get doorWidgetClear => 'Remove widget selection';

  @override
  String get doorWidgetCleared => 'Widget selection removed';

  @override
  String get doorWidgetUnconfigured => 'No door selected';

  @override
  String get doorWidgetAddHint =>
      'Add the \"Open door\" widget to your home screen, then choose a door.';

  @override
  String doorWidgetInstance(int id) {
    return 'Widget #$id';
  }
}
