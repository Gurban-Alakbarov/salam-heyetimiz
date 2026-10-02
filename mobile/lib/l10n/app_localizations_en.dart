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

  @override
  String get homeInvitations => 'My Invitations';

  @override
  String get invitationsTitle => 'My Invitations';

  @override
  String get invitationsEmpty => 'You haven\'t sent any invitations yet.';

  @override
  String get invitationFilterAll => 'All';

  @override
  String get invitationFilterActive => 'Active';

  @override
  String get invitationFilterUsed => 'Used';

  @override
  String get invitationFilterExpired => 'Expired';

  @override
  String get invitationFilterRevoked => 'Revoked';

  @override
  String get invitationStatusActive => 'Active';

  @override
  String get invitationStatusUsed => 'Used';

  @override
  String get invitationStatusExpired => 'Expired';

  @override
  String get invitationStatusRevoked => 'Revoked';

  @override
  String get invitationSentAt => 'Sent';

  @override
  String get invitationDuration => 'Duration';

  @override
  String get invitationExpiresAt => 'Expires';

  @override
  String invitationRemaining(String time) {
    return '$time left';
  }

  @override
  String get invitationUsedAt => 'Used';

  @override
  String get invitationFirstUsedAt => 'First used';

  @override
  String get invitationLastUsedAt => 'Last used';

  @override
  String get invitationUsageCount => 'Uses';

  @override
  String invitationUsageValue(int count) {
    return '$count times';
  }

  @override
  String get invitationUnlimited => 'Unlimited';

  @override
  String get invitationDefaultTitle => 'Invitation';

  @override
  String get checkoutTitle => 'Payment';

  @override
  String get paymentTestBanner => 'TEST PAYMENT';

  @override
  String get paymentResultTitle => 'Payment result';

  @override
  String get paymentSuccess => 'Payment successful';

  @override
  String get paymentFailed => 'Payment failed';

  @override
  String get paymentCancelled => 'Payment cancelled';

  @override
  String get paymentExpired => 'Payment time expired';

  @override
  String get paymentPending => 'Payment is being processed';

  @override
  String get paymentChecking => 'Confirming with the bank…';

  @override
  String get paymentRecheck => 'Check again';

  @override
  String get paymentDone => 'Back to home';

  @override
  String get paymentOrderNotFound => 'Order not found';

  @override
  String get subscriptionRenew => 'Renew (12 AZN / 30 days)';

  @override
  String get subscriptionRenewNotEligible =>
      'This subscription cannot be renewed right now.';

  @override
  String get regTypeTitle => 'Registration type';

  @override
  String get regTypePhysical => 'Individual';

  @override
  String get regTypePhysicalBody =>
      'I want a barrier device for my own private yard.';

  @override
  String get regTypeLegal => 'Legal entity';

  @override
  String get regTypeLegalBody => 'Residential complex, building or company.';

  @override
  String get appMineTitle => 'My applications';

  @override
  String get appNone => 'You have no applications yet';

  @override
  String get appNewPhysical => 'New application (individual)';

  @override
  String get appNewLegal => 'New application (legal entity)';

  @override
  String get appSectionPhysical => 'Individual';

  @override
  String get appSectionLegal => 'Legal entity';

  @override
  String get appPhysicalTitle => 'Individual application';

  @override
  String get appPhysicalIntro =>
      'Tell us where your yard is — our team will contact you about installation.';

  @override
  String get appLegalTitle => 'Legal entity application';

  @override
  String get appLegalIntro =>
      'Complex details and location. An administrator reviews the application.';

  @override
  String get appFullName => 'Full name';

  @override
  String get appPhone => 'Phone';

  @override
  String get appEmail => 'Email';

  @override
  String get appAddress => 'Address';

  @override
  String get appNote => 'Note (optional)';

  @override
  String get appComplexName => 'Complex name';

  @override
  String get appLegalName => 'Legal name';

  @override
  String get appVoen => 'TIN (VÖEN)';

  @override
  String get appLegalAddress => 'Legal address';

  @override
  String get appContactName => 'Contact person';

  @override
  String get appComplexAddress => 'Complex address';

  @override
  String get appApartments => 'Number of apartments (optional)';

  @override
  String get appLocationLabel => 'Location on the map';

  @override
  String get appLocationHint => 'Tap the map to place the pin';

  @override
  String get appUseMyLocation => 'My location';

  @override
  String get appLocationRequired => 'Choose the location on the map';

  @override
  String get appLocationOutside => 'The location must be inside Azerbaijan';

  @override
  String get appVoenInvalid => 'TIN must be 10 digits';

  @override
  String get appApartmentsInvalid => 'Enter a valid number';

  @override
  String get appSubmit => 'Submit application';

  @override
  String get appLater => 'I will fill it in later';

  @override
  String get appSubmitted => 'Application submitted';

  @override
  String get appStatusNew => 'New';

  @override
  String get appStatusContacted => 'Contacted';

  @override
  String get appStatusInProgress => 'In progress';

  @override
  String get appStatusInstalled => 'Installed';

  @override
  String get appStatusPending => 'Under review';

  @override
  String get appStatusApproved => 'Approved';

  @override
  String get appStatusRejected => 'Rejected';

  @override
  String get appRejectReason => 'Reason';

  @override
  String get appErrTypeMismatch =>
      'Your account type does not match this application type.';

  @override
  String get appErrAlreadyOpen => 'You already have an open application.';

  @override
  String get appErrDuplicateVoen =>
      'An application with this TIN is already under review.';

  @override
  String get kmTitle => 'My complex (Komendant)';

  @override
  String get kmEntrySubtitle => 'Residents, invitations and devices';

  @override
  String get kmStatDevices => 'Devices';

  @override
  String get kmStatResidents => 'Residents';

  @override
  String get kmStatPending => 'Pending invites';

  @override
  String get kmDevicesTitle => 'Complex devices';

  @override
  String get kmNoDevices => 'No devices are linked to the complex yet.';

  @override
  String get kmOnline => 'Online';

  @override
  String get kmOffline => 'Offline';

  @override
  String kmDevicePrice(String price, int days) {
    return 'Subscription: $price / $days days';
  }

  @override
  String get kmInvite => 'Invite resident';

  @override
  String get kmInviteIntro =>
      'The resident will receive an invitation link by email, valid for 7 days.';

  @override
  String get kmFirstName => 'First name';

  @override
  String get kmLastName => 'Last name';

  @override
  String get kmEmail => 'Email';

  @override
  String get kmInviteSend => 'Send invitation';

  @override
  String get kmInviteSent => 'Invitation sent.';

  @override
  String get kmInvitations => 'Invitations';

  @override
  String get kmTabPending => 'Pending';

  @override
  String get kmTabAccepted => 'Accepted';

  @override
  String get kmTabExpired => 'Expired';

  @override
  String get kmTabClosed => 'Cancelled';

  @override
  String get kmStatusDeclined => 'Declined';

  @override
  String get kmNoInvitations => 'No invitations here.';

  @override
  String kmExpiresAt(String date) {
    return 'Expires: $date';
  }

  @override
  String kmAcceptedAt(String date) {
    return 'Accepted: $date';
  }

  @override
  String kmSendCount(int count) {
    return 'Sent $count times';
  }

  @override
  String get kmResend => 'Resend';

  @override
  String get kmRevoke => 'Revoke';

  @override
  String get kmResent => 'Invitation resent.';

  @override
  String get kmRevoked => 'Invitation revoked.';

  @override
  String get kmRevokeTitle => 'Revoke invitation?';

  @override
  String get kmRevokeBody => 'The invitation link will stop working.';

  @override
  String get kmResidentsTitle => 'Residents';

  @override
  String get kmNoResidents => 'The complex has no residents yet.';

  @override
  String kmActiveSubs(int count) {
    return 'Active subscriptions: $count';
  }

  @override
  String get kmNoActiveSub => 'No active subscription';

  @override
  String kmJoinedAt(String date) {
    return 'Joined: $date';
  }

  @override
  String get kmRemove => 'Remove';

  @override
  String get kmRemoveTitle => 'Remove resident from complex';

  @override
  String kmRemoveBody(String name) {
    return '$name will be removed from the complex. Access to the complex devices stops immediately and active subscriptions are cancelled. No automatic refund is made.';
  }

  @override
  String get kmRemoved => 'Resident removed from the complex.';

  @override
  String get kmErrNotKomendant =>
      'This section is only for the complex manager.';

  @override
  String get kmErrForbidden => 'You are not allowed to do this.';

  @override
  String get kmErrAlreadyResident =>
      'This email already belongs to a resident of the complex.';

  @override
  String get kmErrAlreadyPending =>
      'An active invitation already exists for this email.';

  @override
  String get kmErrNotResendable => 'This invitation cannot be resent.';

  @override
  String get kmErrNotRevocable => 'This invitation cannot be revoked.';

  @override
  String get kmErrRateLimited =>
      'Sent too often. Please try again a little later.';

  @override
  String get kmErrNotFound => 'Not found. The list has been refreshed.';

  @override
  String get invTitle => 'Invitation';

  @override
  String get invComplexKind => 'Residential complex invitation';

  @override
  String get invFamilyKind => 'Family member invitation';

  @override
  String invComplexBody(String complex) {
    return 'You are invited to $complex as a resident. After accepting you can pick a device and subscribe.';
  }

  @override
  String invFamilyBody(String inviter) {
    return '$inviter invited you as a family member to use a device. Access activates once your own subscription is paid.';
  }

  @override
  String invFor(String name) {
    return 'Invitee: $name';
  }

  @override
  String invSentTo(String email) {
    return 'The invitation was sent to $email. Continue with that email.';
  }

  @override
  String invExpires(String date) {
    return 'Valid until $date';
  }

  @override
  String get invRegister => 'Create account';

  @override
  String get invLogin => 'I have an account — log in';

  @override
  String get invAccept => 'Accept invitation';

  @override
  String get invDecline => 'Decline';

  @override
  String get invDeclineTitle => 'Decline the invitation?';

  @override
  String get invDeclineBody => 'This invitation link will stop working.';

  @override
  String get invDeclined => 'Invitation declined.';

  @override
  String invAcceptedComplex(String complex) {
    return 'Invitation accepted. Welcome to $complex!';
  }

  @override
  String get invAcceptedFamily =>
      'Invitation accepted. Pay the subscription to activate access.';

  @override
  String get invGoneTitle => 'Invitation is not valid';

  @override
  String get invGoneBody =>
      'This invitation link has expired, been revoked or already used. Ask the sender for a new invitation.';

  @override
  String get invClose => 'Close';

  @override
  String get invErrEmailMismatch =>
      'This invitation is for another email address. Sign in with the invited email.';

  @override
  String get invErrAlreadyHasAccess =>
      'You already have access to this device.';

  @override
  String get invErrInvalidTarget => 'You cannot accept your own invitation.';

  @override
  String get invErrUnsupported => 'This invitation type is not supported.';

  @override
  String get invRegisterHint =>
      'Register with the invited email; the invitation is accepted once your account is verified.';

  @override
  String get invPendingCard => 'You have an invitation';

  @override
  String get invPendingCardBody => 'Open and accept it.';

  @override
  String get cxTitle => 'My complex';

  @override
  String get cxMyComplexes => 'My complexes';

  @override
  String get cxEntrySubtitle => 'Pick a device and subscribe';

  @override
  String get cxNoComplexes => 'You are not a resident of any complex yet.';

  @override
  String get cxDevicesTitle => 'Complex devices';

  @override
  String get cxNoDevices => 'The complex has no devices yet.';

  @override
  String get cxStatusNone => 'Not subscribed';

  @override
  String get cxStatusPending => 'Awaiting payment';

  @override
  String get cxStatusActive => 'Active';

  @override
  String get cxStatusExpired => 'Expired';

  @override
  String cxPrice(String price, int days) {
    return '$price / $days days';
  }

  @override
  String get cxPriceNote =>
      'A subscription activates your access to this device.';

  @override
  String get cxSubscriptionBlock => 'Monthly subscription';

  @override
  String get cxSubscribe => 'Subscribe';

  @override
  String get cxFinishPayment => 'Complete payment';

  @override
  String get cxRenew => 'Renew';

  @override
  String get cxOpenInDevices => 'Open in Devices';

  @override
  String get cxDeviceTitle => 'Device';

  @override
  String get cxErrAlreadyActive =>
      'Your subscription for this device is already active.';

  @override
  String get payPendingTitle => 'Awaiting payment';

  @override
  String payPendingCard(int count) {
    return 'Subscriptions awaiting payment: $count';
  }

  @override
  String get payPendingNone => 'Nothing is awaiting payment.';

  @override
  String get payPendingMain => 'Resident subscription';

  @override
  String get payPendingAdditional => 'Family member subscription';

  @override
  String get payNow => 'Pay';

  @override
  String payDeviceFallback(int id) {
    return 'Device #$id';
  }

  @override
  String get payErrForbidden => 'You are not allowed to do this.';

  @override
  String get famTitle => 'Family members';

  @override
  String get famEntrySubtitle => 'Invitations, access and payments';

  @override
  String get famTabMembers => 'Members';

  @override
  String get famTabInvitations => 'Invitations';

  @override
  String get famInvite => 'Invite a family member';

  @override
  String get famNotHead =>
      'To invite family members you need active access of your own on a device. This is not available on devices you were added to as a family member.';

  @override
  String get famErrNotHead =>
      'You cannot manage family members on this device.';

  @override
  String get famErrInvalidTarget =>
      'This person cannot be invited (yourself or someone already on this device).';

  @override
  String get famNoMembers => 'No family members yet.';

  @override
  String get famNoInvitations => 'No family invitations yet.';

  @override
  String get famDeviceLabel => 'Device';

  @override
  String get famInviteIntro =>
      'An invitation valid for 7 days will be emailed for the selected device. Each family member has their own monthly subscription, paid by you or by the member.';

  @override
  String get famGranted =>
      'Access to this device was granted to the family member. The subscription awaits payment.';

  @override
  String get famSubNone => 'No subscription';

  @override
  String famSubActiveUntil(String date) {
    return 'Active until $date';
  }

  @override
  String get famPayFor => 'Pay for member';

  @override
  String get famRemove => 'Remove';

  @override
  String get famRemoveTitle => 'Remove family member';

  @override
  String famRemoveBody(String name) {
    return '$name will be removed from your family. Access to the devices you granted stops immediately and their subscriptions are cancelled. No automatic refund is made.';
  }

  @override
  String get famRemoved => 'Family member removed.';
}
