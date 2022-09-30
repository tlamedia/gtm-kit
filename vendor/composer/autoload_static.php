<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3b585b55966a016a3d1b071eb261592a
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
        'TLA_Media\\GTM_Kit\\Admin\\AdminNotice' => __DIR__ . '/../..' . '/src/Admin/AdminNotice.php',
        'TLA_Media\\GTM_Kit\\Admin\\GeneralOptionsPage' => __DIR__ . '/../..' . '/src/Admin/GeneralOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\IntegrationsOptionsPage' => __DIR__ . '/../..' . '/src/Admin/IntegrationsOptionsPage.php',
        'TLA_Media\\GTM_Kit\\Admin\\OptionTab' => __DIR__ . '/../..' . '/src/Admin/OptionTab.php',
        'TLA_Media\\GTM_Kit\\Admin\\OptionTabs' => __DIR__ . '/../..' . '/src/Admin/OptionTabs.php',
        'TLA_Media\\GTM_Kit\\Admin\\OptionsForm' => __DIR__ . '/../..' . '/src/Admin/OptionsForm.php',
        'TLA_Media\\GTM_Kit\\Frontend\\BasicDatalayerData' => __DIR__ . '/../..' . '/src/Frontend/BasicDatalayerData.php',
        'TLA_Media\\GTM_Kit\\Frontend\\Frontend' => __DIR__ . '/../..' . '/src/Frontend/Frontend.php',
        'TLA_Media\\GTM_Kit\\Installation\\Installation' => __DIR__ . '/../..' . '/src/Installation/Installation.php',
        'TLA_Media\\GTM_Kit\\Installation\\Upgrade' => __DIR__ . '/../..' . '/src/Installation/Upgrade.php',
        'TLA_Media\\GTM_Kit\\Integration\\ContactForm7' => __DIR__ . '/../..' . '/src/Integration/ContactForm7.php',
        'TLA_Media\\GTM_Kit\\Integration\\WooCommerce' => __DIR__ . '/../..' . '/src/Integration/WooCommerce.php',
        'TLA_Media\\GTM_Kit\\Options' => __DIR__ . '/../..' . '/src/Options.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3b585b55966a016a3d1b071eb261592a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3b585b55966a016a3d1b071eb261592a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3b585b55966a016a3d1b071eb261592a::$classMap;

        }, null, ClassLoader::class);
    }
}
