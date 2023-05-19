CONTENTS OF THIS FILE
 ---------------------
  * Introduction
  * Requirements
  * Installation
  * Configuration

 INTRODUCTION
 ------------
 This module attempts to block Drupal configuration changes for configuration
 managed by a Feature.

 The main use case is to lock particular configuration on a production site
 that allows site administrators to edit some configuration, but not
 that which is managed by a Feature.
 
 This module was heavily influenced by the "Configuration Read-only mode"
 module which served as a starting point:
 https://www.drupal.org/project/config_readonly

 REQUIREMENTS
 ------------
 No special requirements.

 INSTALLATION
 ------------
 Install as you would normally install a contributed Drupal module. Visit:
 https://www.drupal.org/docs/extending-drupal/installing-modules
 for further information.

 CONFIGURATION
 -------------
 To disable this module's functionality, while leaving the module enabled,
 add this to settings.php or settings.local.php:

   $settings['features_readonly_disable'] = TRUE;
