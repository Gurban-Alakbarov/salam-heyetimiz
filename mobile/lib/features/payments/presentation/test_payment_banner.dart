import 'package:flutter/material.dart';

import '../../../design_system/tokens/tokens.dart';

/// Unmissable "TEST ÖDƏNİŞ" strip shown for fake-gateway orders (`order.is_test`, BR-16).
class TestPaymentBanner extends StatelessWidget {
  const TestPaymentBanner({required this.label, super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: label,
      child: Container(
        width: double.infinity,
        color: AppColors.danger,
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs, horizontal: AppSpacing.md),
        child: Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, letterSpacing: 1),
        ),
      ),
    );
  }
}
