import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    // Google Services (Firebase) — consumes app/google-services.json (Phase 4A, Android FCM).
    id("com.google.gms.google-services")
    // Crashlytics (1.0.1) — uploads the native symbol / mapping files for readable crash reports.
    id("com.google.firebase.crashlytics")
}

// Release signing: the Play upload key, read from android/key.properties (gitignored; the keystore itself lives
// outside the repo, in C:\Users\User\salam-release-keys).
val keystoreProperties = Properties().apply {
    val file = rootProject.file("key.properties")
    if (file.exists()) FileInputStream(file).use { load(it) }
}

android {
    namespace = "az.salam.sezam"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Required by flutter_local_notifications (java.time APIs on older API levels).
        isCoreLibraryDesugaringEnabled = true
    }

    defaultConfig {
        // Play package name — fixed for ever once published (2026-10-27).
        applicationId = "az.salam.sezam"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // firebase_messaging requires API 23+; never lower the Flutter default.
        minSdk = maxOf(flutter.minSdkVersion, 23)
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            if (keystoreProperties.isNotEmpty()) {
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
                storeFile = file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            // Play upload key when android/key.properties exists; otherwise the debug key (local only, not for Play).
            signingConfig = signingConfigs.getByName(if (keystoreProperties.isNotEmpty()) "release" else "debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

dependencies {
    // Core library desugaring runtime — required by flutter_local_notifications.
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
