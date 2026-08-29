# publishes/

JSON recipes for copying files from this plugin’s `vendor/<package>/` into the plugin (config + migration stubs).

Core runner (in **miran/mksine**): `Miran\Mksine\Core\Plugins\Publishing\PluginVendorPublishRunner`.

## Preset file: `publishes/{preset}.json`

```json
{
    "vendor_path": "vendor-name/package-name",
    "config": {
        "from": "config/package.php",
        "to": "config/package.php"
    },
    "migrations": [
        "create_example_table",
        "add_column_to_example_table"
    ]
}
```

- `vendor_path`: path under `plugins/{id}/vendor/`.
- `config.from`: path inside that package; `config.to`: path inside the plugin (created if needed).
- `migrations`: migration base names (`.php` or `.php.stub` under `database/migrations/` in the package).

Register a console command in your plugin’s `boot()` (see **mks-booking** `mks-booking:publish-vendor`) that calls `PluginVendorPublishRunner::publish()` for your plugin manifest.

Then from **app root**: `php artisan {your-plugin}:publish-vendor {preset}`.
