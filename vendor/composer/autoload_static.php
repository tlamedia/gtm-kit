<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit12fa396dcd6fc263a33fd78c6d8551b8
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'TLA_Media\\GTM_Kit\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'TLA_Media\\GTM_Kit\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'TLA_Media\\GTM_Kit\\Admin\\AbstractOptionsPage' => __DIR__ . '/../..' . '/src/Admin/AbstractOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\AdminAPI' => __DIR__ . '/../..' . '/src/Admin/AdminAPI.php',
        'TLA_Media\\GTM_Kit\\Admin\\Analytics' => __DIR__ . '/../..' . '/src/Admin/Analytics.php',
        'TLA_Media\\GTM_Kit\\Admin\\AssetsTrait' => __DIR__ . '/../..' . '/src/Admin/AssetsTrait.php',
        'TLA_Media\\GTM_Kit\\Admin\\GeneralOptionsPage' => __DIR__ . '/../..' . '/src/Admin/GeneralOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\HelpOptionsPage' => __DIR__ . '/../..' . '/src/Admin/HelpOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\Integrations' => __DIR__ . '/../..' . '/src/Admin/Integrations.php',
        'TLA_Media\\GTM_Kit\\Admin\\IntegrationsOptionsPage' => __DIR__ . '/../..' . '/src/Admin/IntegrationsOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\MetaBox' => __DIR__ . '/../..' . '/src/Admin/MetaBox.php',
        'TLA_Media\\GTM_Kit\\Admin\\Notification' => __DIR__ . '/../..' . '/src/Admin/Notification.php',
        'TLA_Media\\GTM_Kit\\Admin\\NotificationsHandler' => __DIR__ . '/../..' . '/src/Admin/NotificationsHandler.php',
        'TLA_Media\\GTM_Kit\\Admin\\PluginAvailability' => __DIR__ . '/../..' . '/src/Admin/PluginAvailability.php',
        'TLA_Media\\GTM_Kit\\Admin\\SetupWizard' => __DIR__ . '/../..' . '/src/Admin/SetupWizard.php',
        'TLA_Media\\GTM_Kit\\Admin\\Suggestions' => __DIR__ . '/../..' . '/src/Admin/Suggestions.php',
        'TLA_Media\\GTM_Kit\\Admin\\TemplatesOptionsPage' => __DIR__ . '/../..' . '/src/Admin/TemplatesOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\UpgradesOptionsPage' => __DIR__ . '/../..' . '/src/Admin/UpgradesOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\BricksConditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/BricksConditional.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\Conditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/Conditional.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\ContactForm7Conditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/ContactForm7Conditional.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\EasyDigitalDownloadsConditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/EasyDigitalDownloadsConditional.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\PremiumConditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/PremiumConditional.php',
        'TLA_Media\\GTM_Kit\\Common\\Conditionals\\WooCommerceConditional' => __DIR__ . '/../..' . '/src/Common/Conditionals/WooCommerceConditional.php',
        'TLA_Media\\GTM_Kit\\Common\\RestAPIServer' => __DIR__ . '/../..' . '/src/Common/RestAPIServer.php',
        'TLA_Media\\GTM_Kit\\Common\\Util' => __DIR__ . '/../..' . '/src/Common/Util.php',
        'TLA_Media\\GTM_Kit\\Frontend\\BasicDatalayerData' => __DIR__ . '/../..' . '/src/Frontend/BasicDatalayerData.php',
        'TLA_Media\\GTM_Kit\\Frontend\\Frontend' => __DIR__ . '/../..' . '/src/Frontend/Frontend.php',
        'TLA_Media\\GTM_Kit\\Frontend\\Stape' => __DIR__ . '/../..' . '/src/Frontend/Stape.php',
        'TLA_Media\\GTM_Kit\\Frontend\\UserData' => __DIR__ . '/../..' . '/src/Frontend/UserData.php',
        'TLA_Media\\GTM_Kit\\Installation\\Activation' => __DIR__ . '/../..' . '/src/Installation/Activation.php',
        'TLA_Media\\GTM_Kit\\Installation\\AutomaticUpdates' => __DIR__ . '/../..' . '/src/Installation/AutomaticUpdates.php',
        'TLA_Media\\GTM_Kit\\Installation\\PluginDataImport' => __DIR__ . '/../..' . '/src/Installation/PluginDataImport.php',
        'TLA_Media\\GTM_Kit\\Installation\\Upgrade' => __DIR__ . '/../..' . '/src/Installation/Upgrade.php',
        'TLA_Media\\GTM_Kit\\Integration\\AbstractEcommerce' => __DIR__ . '/../..' . '/src/Integration/AbstractEcommerce.php',
        'TLA_Media\\GTM_Kit\\Integration\\AbstractIntegration' => __DIR__ . '/../..' . '/src/Integration/AbstractIntegration.php',
        'TLA_Media\\GTM_Kit\\Integration\\ContactForm7' => __DIR__ . '/../..' . '/src/Integration/ContactForm7.php',
        'TLA_Media\\GTM_Kit\\Integration\\EasyDigitalDownloads' => __DIR__ . '/../..' . '/src/Integration/EasyDigitalDownloads.php',
        'TLA_Media\\GTM_Kit\\Integration\\WooCommerce' => __DIR__ . '/../..' . '/src/Integration/WooCommerce.php',
        'TLA_Media\\GTM_Kit\\Options' => __DIR__ . '/../..' . '/src/Options.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit12fa396dcd6fc263a33fd78c6d8551b8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit12fa396dcd6fc263a33fd78c6d8551b8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit12fa396dcd6fc263a33fd78c6d8551b8::$classMap;

        }, null, ClassLoader::class);
    }
}
