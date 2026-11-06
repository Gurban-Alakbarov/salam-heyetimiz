# Adds the SalamDoorWidget app extension to Runner.xcodeproj (the project file cannot be edited by hand).
#
#   ruby mobile/ios/scripts/add_widget_target.rb
#
# Needs the xcodeproj gem. Safe to run twice: it stops if the target is already there.
require 'xcodeproj'

NAME = 'SalamDoorWidget'
PROFILE = 'Salam Sesame Door Widget App Store'
TEAM = '2654395QYQ'

project = Xcodeproj::Project.open(File.expand_path('../Runner.xcodeproj', __dir__))
if project.targets.any? { |t| t.name == NAME }
  puts "#{NAME} is already in the project"
  exit 0
end
runner = project.targets.find { |t| t.name == 'Runner' }

target = project.new_target(:app_extension, NAME, :ios, '17.0')
target.add_build_configuration('Profile', :release) unless target.build_configurations.any? { |c| c.name == 'Profile' }

group = project.main_group.new_group(NAME, NAME)
%w[DoorAPI.swift DoorIntents.swift DoorStore.swift DoorStrings.swift SalamDoorWidget.swift].each do |file|
  target.source_build_phase.add_file_reference(group.new_reference(file))
end
target.resources_build_phase.add_file_reference(group.new_reference('PrivacyInfo.xcprivacy'))
group.new_reference('Info.plist')
group.new_reference('SalamDoorWidget.entitlements')
xcconfig = group.new_reference('SalamDoorWidget.xcconfig')

target.build_configurations.each do |config|
  config.base_configuration_reference = xcconfig
  settings = config.build_settings
  settings['PRODUCT_NAME'] = '$(TARGET_NAME)'
  settings['PRODUCT_BUNDLE_IDENTIFIER'] = 'az.salam.sezam.DoorWidget'
  settings['INFOPLIST_FILE'] = "#{NAME}/Info.plist"
  settings['CODE_SIGN_ENTITLEMENTS'] = "#{NAME}/#{NAME}.entitlements"
  settings['GENERATE_INFOPLIST_FILE'] = 'NO'
  settings['IPHONEOS_DEPLOYMENT_TARGET'] = '17.0'
  settings['TARGETED_DEVICE_FAMILY'] = '1'
  settings['SWIFT_VERSION'] = '5.0'
  settings['SKIP_INSTALL'] = 'YES'
  settings['DEVELOPMENT_TEAM'] = TEAM
  settings['LD_RUNPATH_SEARCH_PATHS'] = ['$(inherited)', '@executable_path/Frameworks', '@executable_path/../../Frameworks']
  next unless config.name == 'Release'

  # The same manual App Store signing as Runner: setup_widget_signing.py makes the profile.
  settings['CODE_SIGN_STYLE'] = 'Manual'
  settings['CODE_SIGN_IDENTITY[sdk=iphoneos*]'] = 'Apple Distribution'
  settings['PROVISIONING_PROFILE_SPECIFIER[sdk=iphoneos*]'] = PROFILE
end

runner.add_dependency(target)
embed = runner.new_copy_files_build_phase('Embed App Extensions')
embed.symbol_dst_subfolder_spec = :plug_ins
embed.add_file_reference(target.product_reference).settings = { 'ATTRIBUTES' => ['RemoveHeadersOnCopy'] }

# Before Flutter's "Thin Binary" script, otherwise Xcode reports a dependency cycle.
thin = runner.build_phases.index { |phase| phase.display_name.include?('Thin Binary') }
runner.build_phases.move(embed, thin) if thin

project.save
puts "#{NAME} added"
